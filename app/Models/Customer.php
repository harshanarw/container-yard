<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CustomerType;

class Customer extends Model
{
    use HasFactory;

    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function stateInfo()
    {
        return $this->belongsTo(CountryState::class, 'state_id');
    }

    public function districtInfo()
    {
        return $this->belongsTo(CountryState::class, 'district_id');
    }

    protected $fillable = [
        'code', 'name', 'registration_no', 'tin_number', 'address', 'city', 'state',
        'country', 'country_id', 'state_id', 'district_id', 'contact_person', 'designation', 'phone_office', 'phone_mobile',
        'fax', 'email', 'website', 'currency', 'credit_limit', 'payment_terms',
        'ap_credit_limit', 'ap_payment_terms',
        'status',
        'contract_start', 'contract_end', 'email_notifications', 'auto_invoice',
        'tax_exempt',
        'local_agent_id', 'billing_party_id',
        'logo', 'notes',
    ];

    protected $casts = [
        'credit_limit'        => 'decimal:2',
        'ap_credit_limit'     => 'decimal:2',
        'contract_start'      => 'date',
        'contract_end'        => 'date',
        'email_notifications' => 'boolean',
        'auto_invoice'        => 'boolean',
        'tax_exempt'          => 'boolean',
    ];

    public function types()
    {
        return $this->belongsToMany(CustomerType::class)->orderBy('sort_order');
    }

    public function localAgent()
    {
        return $this->belongsTo(Customer::class, 'local_agent_id');
    }

    public function billingParty()
    {
        return $this->belongsTo(Customer::class, 'billing_party_id');
    }

    // Relationships
    public function containers()
    {
        return $this->hasMany(Container::class);
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function gateMovements()
    {
        return $this->hasMany(GateMovement::class);
    }

    public function yardStorage()
    {
        return $this->hasMany(YardStorage::class);
    }

    // Storage tariff for this customer (active, currently valid)
    public function activeTariff()
    {
        return $this->hasOne(StorageMasterHeader::class)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->latestOfMany('valid_from');
    }

    public function emailContacts()
    {
        return $this->hasMany(\App\Models\CustomerEmailContact::class)->orderBy('category')->orderBy('sort_order');
    }

    // ── Accounts Payable (this Contact acting as a creditor / supplier) ──────

    /** Purchase bills owed to this contact. */
    public function supplierInvoices()
    {
        return $this->hasMany(SupplierInvoice::class, 'customer_id');
    }

    /** Payment vouchers issued to this contact. */
    public function paymentVouchers()
    {
        return $this->hasMany(PaymentVoucher::class, 'customer_id');
    }

    /**
     * Contacts eligible to be billed as a supplier/creditor in the AP module.
     * The unified master means any active contact can owe-or-be-owed, so this
     * simply lists active parties (ordered for dropdowns); the Customer Type
     * tags (Vendor, Transporter, Shipping Line, …) remain available for
     * filtering and reporting.
     */
    public function scopeApContacts($query)
    {
        return $query->selectable()->where('status', 'active')->orderBy('name');
    }

    /**
     * Contacts an operator may choose from a dropdown.
     *
     * Everything except the managed placeholder that
     * {@see \App\Services\InternalPartyService} creates to represent the yard
     * itself. That record exists so a lease-in has a holder to name; it is not
     * a party anyone gates a container in for or raises an invoice to, and
     * offering it invites both.
     *
     * A contact the operator *mapped* as the yard is deliberately still listed.
     * They chose a record they already trade with — most yards have one, used
     * for internal storage or inter-company billing — and hiding it would take
     * away a party they have been selecting all along.
     *
     * A plain `!=` is safe here only because `customers.code` is `NOT NULL` and
     * unique (migration 000001) — every contact has one. Were it ever made
     * nullable, `code != 'SELF'` would be *unknown* rather than true for a null
     * row and every contact without a code would vanish from every dropdown in
     * the system, so this comparison has to be revisited with the column.
     */
    public function scopeSelectable($query)
    {
        return $query->where('code', '!=', \App\Services\InternalPartyService::CODE);
    }
}
