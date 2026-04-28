<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class batch_report extends Model
{
    use HasFactory;
    protected $fillable = [
    'date',
    'total_orders',
    'total_revenue',
    'processed_at',
];

}
