<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'api_id',
        'name',
        'slug',
        'category_id',
        'description',
        'quantity',
        'price',
        'sold'
    ];
}
