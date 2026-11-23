<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSettlement extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_id',
        'products_amount',
        'shipping_amount',
        'commission_rate',
        'commission_amount',
        'deposit_amount',
        'direct_balance_amount',
        'supplier_total_amount',
        'transfer_amount',
        'transferred_amount',
        'status',
        'balance_payment_method',
        'balance_transaction_reference',
        'balance_confirmed_by',
        'balance_confirmed_at',
        'balance_confirmation_notes',
        'transferred_by',
        'transaction_reference',
        'notes',
        'ready_at',
        'transferred_at',
    ];

    protected $casts = [
        'products_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'direct_balance_amount' => 'decimal:2',
        'supplier_total_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'transferred_amount' => 'decimal:2',
        'balance_confirmed_at' => 'datetime',
        'ready_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class);
    }

    public function balanceConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'balance_confirmed_by');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
