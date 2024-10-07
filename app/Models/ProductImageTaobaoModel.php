<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImageTaobaoModel extends Model
{
    use HasFactory;

    protected $table = 'product_images_taobaos';

    protected $fillable = ['product_id', 'src'];
}
