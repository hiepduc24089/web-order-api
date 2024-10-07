<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTaobaoModel extends Model
{
    use HasFactory;

    protected $table = 'product_taobaos';

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
