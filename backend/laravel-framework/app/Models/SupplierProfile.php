<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProfile extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'business_type', 'description',
        'address', 'latitude', 'longitude', 'status',
        'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function products(): HasMany { return $this->hasMany(Product::class, 'supplier_id'); }
    public function documents(): HasMany { return $this->hasMany(SupplierDocument::class); }

    public function settlements(): HasMany
    {
        return $this->hasMany(SupplierSettlement::class, "supplier_id");
    }

    public function rfqItemOffers(): HasMany
    {
        return $this->hasMany(RfqItemOffer::class, 'supplier_id');
    }
}
