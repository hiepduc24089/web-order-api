<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeTaobaoModel extends Model
{
    use HasFactory;

    protected $table = 'product_attributes_taobaos';

    protected $fillable = ['product_value_id', 'name', 'quantity', 'price'];
}
