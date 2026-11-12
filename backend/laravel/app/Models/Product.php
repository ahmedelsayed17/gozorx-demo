<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class Product extends Model
{
    protected $fillable = ['supplier_id','category_id','name_ar','name_en','description','unit_name','unit_price','minimum_order_quantity','available_quantity','status'];
    protected $casts = ['unit_price' => 'decimal:2'];

    public function supplier(): BelongsTo { return $this->belongsTo(SupplierProfile::class, 'supplier_id'); }
}
