<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateApprovalAction extends Model
{
    protected $fillable = [
        'estimate_id', 'estimate_line_item_id', 'action',
        'amended_amount', 'notes', 'actioned_by', 'performed_by_email',
        'approver_name', 'approver_designation', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'amended_amount' => 'decimal:2',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function lineItem()
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_line_item_id');
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
