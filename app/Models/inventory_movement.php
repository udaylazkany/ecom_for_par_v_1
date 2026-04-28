<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class inventory_movement extends Model
{
    use HasFactory;
    protected $fillable = [
    'product_id',
    'change',
    'reason',
    'related_order_id',
];

       public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }
}
