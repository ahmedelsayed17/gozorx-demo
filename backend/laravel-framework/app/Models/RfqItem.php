<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    protected $fillable = [
        'rfq_request_id',
        'name',
        'quantity',
        'unit',
        'specifications',
        'selected_supplier_id',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'selected_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RfqRequest::class, 'rfq_request_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RfqItemOffer::class, 'rfq_item_id');
    }

    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class, 'selected_supplier_id');
    }
}
