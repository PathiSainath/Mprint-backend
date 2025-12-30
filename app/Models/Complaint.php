<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'product_id',
        'product_name',
        'issue_type',
        'description',
        'images',
        'status',
        'admin_response',
        'resolved_at',
    ];

    protected $casts = [
        'images' => 'array',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
