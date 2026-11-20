<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_order_id',
        'payment_type',
        'payment_method',
        'amount',
        'status',
        'transaction_reference',
        'receipt_path',
        'receipt_uploaded_at',
        'reviewed_by',
        'reviewed_at',
        'collected_by',
        'collected_at',
        'review_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
