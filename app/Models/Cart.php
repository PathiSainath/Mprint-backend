<?php
// ...existing code...
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
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'selected_attributes' => 'array',
        'unit_price' => 'float',
        'total_price' => 'float',
        'quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }
}
// ...existing code...