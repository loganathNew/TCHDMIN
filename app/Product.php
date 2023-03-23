<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    //
    protected $fillable = [
        'item_id', 'item_name', 'inward_id', 'item_value', 'dcno', 'supplier_id', 'bags'
    ];


    protected $casts = [
        'item_value' => 'float',
    ];

    public function inward()
    {
        return $this->belongsTo("App\Inward");
    }

    public function item()
    {
        return $this->belongsTo("App\Item");
    }
}
