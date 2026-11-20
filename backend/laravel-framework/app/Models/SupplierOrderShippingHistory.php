<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderShippingHistory extends Model
{
    protected $fillable = [
        'supplier_order_id',
        'admin_id',
        'old_shipping_cost',
        'new_shipping_cost',
        'internal_notes',
    ];

    protected $casts = [
        'old_shipping_cost' => 'decimal:2',
        'new_shipping_cost' => 'decimal:2',
    ];

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
