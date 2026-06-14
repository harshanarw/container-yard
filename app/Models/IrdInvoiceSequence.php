<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrdInvoiceSequence extends Model
{
    protected $table = 'ird_invoice_sequences';
    protected $primaryKey = 'period';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['period', 'last_number'];
}
