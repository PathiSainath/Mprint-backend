<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'quantity',
        'selected_attributes',
        'front_design_path',
        'back_design_path',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'selected_attributes' => 'array',
        'unit_price' => 'float',
        'total_price' => 'float',
        'quantity' => 'integer',
    ];

    protected $appends = ['front_design_url', 'back_design_url'];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function getFrontDesignUrlAttribute()
    {
        return $this->front_design_path ? asset('storage/' . $this->front_design_path) : null;
    }

    public function getBackDesignUrlAttribute()
    {
        return $this->back_design_path ? asset('storage/' . $this->back_design_path) : null;
    }
}