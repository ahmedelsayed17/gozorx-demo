<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'buyer_id',
        'supplier_id',
        'order_number',
        'status',
        'payment_method',
        'payment_status',
        'recipient_name',
        'recipient_phone',
        'governorate',
        'city',
        'address',
        'delivery_notes',
        'subtotal',
        'shipping_total',
        'grand_total',
        'deposit_amount',
        'balance_amount',
        'deposit_expires_at',
        'shipping_cost_set_at',
        'stock_released_at',
        'stock_committed_at',
        'delivered_at',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'deposit_expires_at' => 'datetime',
        'shipping_cost_set_at' => 'datetime',
        'stock_released_at' => 'datetime',
        'stock_committed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_id');
    }

    public function individualItem(): HasOne
    {
        return $this->hasOne(IndividualOrderItem::class);
    }

    public function individualItems(): HasMany
    {
        return $this->hasMany(IndividualOrderItem::class);
    }

    public function shippingHistories(): HasMany
    {
        return $this->hasMany(OrderShippingHistory::class);
    }

    public function supplierOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
    public function supplierSettlements(): HasMany
    {
        return $this->hasMany(SupplierSettlement::class);
    }
}
