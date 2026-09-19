<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\YardJob;
use Illuminate\Support\Facades\DB;

/**
 * The yard, as a party it can transact with.
 *
 * Most jobs have an external counterparty and that is also who holds the box.
 * A lease-in separates them: the shipping line is still the counterparty — it is
 * their container and their invoice — but for the length of the lease **the yard
 * is holding it**, and the yard needs to be nameable for that.
 *
 * Modelled as an ordinary `Customer` rather than a null or a flag. Null would
 * surface as a blank in every report that groups by party — `inYardSearch()`
 * already renders a missing customer as "Unknown", and a deliberate state
 * reading as "Unknown" is how support tickets start. A real record means the
 * yard appears by name on jobs, P&L and listings, which is what anyone reading
 * them expects.
 *
 * ## Which record
 *
 * Two answers, in order:
 *
 *   1. `company_settings.internal_customer_id` — the contact an operator chose.
 *      A yard that has been running for years usually already has one, used for
 *      internal storage or inter-company billing, and creating a second record
 *      for the same company splits its history across two ledgers.
 *   2. Failing that, a managed placeholder with the reserved code `SELF`,
 *      created on first use so nothing has to be configured before a lease can
 *      be recorded.
 *
 * The placeholder is found by **code, never by name**: `customers.code` is
 * unique, and the company name is editable in settings — matching on it would
 * silently create a second internal party the day somebody corrects a typo.
 */
class InternalPartyService
{
    /** Reserved, for the managed placeholder. Short enough for the 10-character code column. */
    public const CODE = 'SELF';

    /** The customer-type tag that marks a party as the yard itself. */
    public const TYPE = 'Internal';

    /**
     * The contact an operator mapped, or null if none is configured.
     *
     * Null both when nothing was chosen and when the chosen row has since been
     * deleted — the foreign key nulls the column — so callers never have to
     * handle a setting pointing at nothing.
     */
    public static function configuredId(): ?int
    {
        $id = CompanySetting::current()->internal_customer_id;

        return $id ? (int) $id : null;
    }

    /**
     * The yard's own party record.
     *
     * Idempotent, so it is safe from a seeder, a migration or a service call.
     * The placeholder's name follows company settings *on creation only* —
     * renaming the company later must not orphan jobs already pointing at it.
     */
    public static function customer(): Customer
    {
        if ($id = static::configuredId()) {
            if ($mapped = Customer::find($id)) {
                return static::tag($mapped);
            }
        }

        return static::tag(static::placeholder());
    }

    /** The id alone, for the common case of stamping it on a job. */
    public static function customerId(): int
    {
        return static::customer()->id;
    }

    /**
     * True when this party is the yard itself.
     *
     * Tests the configured id as well as the reserved code, because a mapped
     * contact is an ordinary customer with an ordinary code — asking only about
     * the code would report the yard's own record as external the moment
     * somebody mapped one.
     */
    public static function isInternal(?Customer $customer): bool
    {
        if (! $customer) {
            return false;
        }

        if ($customer->code === self::CODE) {
            return true;
        }

        $id = static::configuredId();

        return $id !== null && (int) $customer->id === $id;
    }

    /**
     * Whatever represents the yard right now, **without creating anything**.
     *
     * The settings screen needs this to answer "which record used to be the
     * yard" before it changes the mapping. Asking {@see customer()} would
     * answer correctly and leave a placeholder behind on a yard that has never
     * taken a container on hire — a record nobody asked for, in every report
     * that lists parties.
     */
    public static function existing(): ?Customer
    {
        if ($id = static::configuredId()) {
            if ($mapped = Customer::find($id)) {
                return $mapped;
            }
        }

        return Customer::where('code', self::CODE)->first();
    }

    /**
     * The managed placeholder, whether or not it is the one in use.
     *
     * Kept separate from {@see customer()} so the pickers can hide *this* record
     * without hiding a mapped contact: the placeholder exists for no other
     * purpose and nobody should choose it, whereas a contact an operator mapped
     * is one they already trade with and still need to select.
     */
    public static function placeholder(): Customer
    {
        return Customer::firstOrCreate(
            ['code' => self::CODE],
            [
                'name'     => CompanySetting::current()->company_name ?: 'This Yard',
                'status'   => 'active',
                'currency' => CompanySetting::baseCurrency(),
                'notes'    => 'The yard itself. Used where the yard is a party to a job — '
                    . 'for example while holding a container on hire from a shipping line. '
                    . 'Managed by InternalPartyService; do not repurpose or delete.',
            ],
        );
    }

    /**
     * Move the yard's identity from one contact to another.
     *
     * The case this exists for: a yard already running, with lease jobs held by
     * the placeholder, whose operator now maps the contact they had all along.
     * Without this the setting would change the answer for *future* leases and
     * leave every past one pointing at a record that is no longer the yard.
     *
     * **Only lease-in jobs are repointed**, matched on the job type as well as
     * the holder. That narrowing is not caution, it is correctness: a mapped
     * contact can be an ordinary customer that genuinely rents containers, and
     * a blanket `held_by_customer_id = $from → $to` would rewrite its real
     * rentals when the mapping was later cleared.
     *
     * @return int how many jobs moved
     */
    public static function remap(?Customer $from, Customer $to): int
    {
        if (! $from || $from->id === $to->id) {
            return 0;
        }

        return DB::transaction(function () use ($from, $to) {
            $moved = YardJob::where('job_type_code', 'LESSOR_ONHIRE')
                ->where('held_by_customer_id', $from->id)
                ->update(['held_by_customer_id' => $to->id]);

            static::tag($to);

            // The old contact is an ordinary customer again. Leaving the tag on
            // would have two records describing themselves as this company.
            if ($type = CustomerType::where('name', self::TYPE)->first()) {
                $from->types()->detach($type->id);
            }

            return $moved;
        });
    }

    /** Mark a contact as representing the yard, without disturbing its other types. */
    private static function tag(Customer $customer): Customer
    {
        if ($type = CustomerType::where('name', self::TYPE)->first()) {
            $customer->types()->syncWithoutDetaching([$type->id]);
        }

        return $customer;
    }
}
