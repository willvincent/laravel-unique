<?php

namespace WillVincent\LaravelUnique\Tests\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WillVincent\LaravelUnique\HasUniqueNames;

class Item extends Model
{
    use HasUniqueNames, SoftDeletes;

    protected $table = 'items';

    protected $fillable = [
        'name',
        'organization_id',
        'department_id',
    ];
}
