<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOrder extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_id',
        'status',
        'subtotal',
        'shipping_total',
        'grand_total',
        'deposit_amount',
        'balance_amount',
        'cancellation_reason',
        'shipping_cost_set_at',
        'deposit_expires_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'stock_released_at' => 'datetime',
        'stock_committed_at' => 'datetime',
        'shipping_cost_set_at' => 'datetime',
        'deposit_expires_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shippingHistories(): HasMany
    {
        return $this->hasMany(SupplierOrderShippingHistory::class);
    }
}
