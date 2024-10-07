<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryTaobaoModel extends Model
{
    use HasFactory;

    protected $table = 'category_taobaos';

    protected $fillable = [
        'api_id',
        'name',
        'slug',
        'type',
        'parent_id',
        'child_id',
    ];
}
