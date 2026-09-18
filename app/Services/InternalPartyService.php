<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerType;

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
 * Found by **code**, never by name: `customers.code` is unique, and the company
 * name is editable in settings — matching on it would silently create a second
 * internal party the day somebody corrects a typo.
 */
class InternalPartyService
{
    /** Reserved. Short enough for the 10-character code column. */
    public const CODE = 'SELF';

    /** The customer-type tag that marks a party as the yard itself. */
    public const TYPE = 'Internal';

    /**
     * The yard's own party record, created on first use.
     *
     * Idempotent, so it is safe from a seeder, a migration or a service call.
     * The name follows company settings *on creation only* — renaming the
     * company later must not orphan jobs already pointing at this record.
     */
    public static function customer(): Customer
    {
        $customer = Customer::firstOrCreate(
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

        if ($type = CustomerType::where('name', self::TYPE)->first()) {
            $customer->types()->syncWithoutDetaching([$type->id]);
        }

        return $customer;
    }

    /** The id alone, for the common case of stamping it on a job. */
    public static function customerId(): int
    {
        return static::customer()->id;
    }

    /** True when this party is the yard itself. */
    public static function isInternal(?Customer $customer): bool
    {
        return $customer?->code === self::CODE;
    }
}
