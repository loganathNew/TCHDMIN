<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageLocation extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name', 'des', 'value', 'created_by', 'updated_by'
    ];
}
