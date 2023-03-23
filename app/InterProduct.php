<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterProduct extends Model
{
    use SoftDeletes;
    //
    protected $fillable = [
        'item_id', 'item_name', 'inter_id', 'item_value', 'dcno', 'supplier_id', 'bags', 'avg_weight'
    ];


    protected $casts = [
        'item_value' => 'float',
        'avg_weight' => 'float',
    ];

    public function inter()
    {
        return $this->belongsTo("App\Inter");
    }

    public function item()
    {
        return $this->belongsTo("App\Item");
    }
}
