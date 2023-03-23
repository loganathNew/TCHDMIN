<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    //
    protected $fillable = [
        'location_id', 'item_id',
        'total_inward', 'total_outward', 'balance',
        'total_inbag', 'total_outbag', 'balance_bag'
    ];

    public function item()
    {
        return $this->belongsTo("App\Item");
    }

    public function location()
    {
        return $this->belongsTo("App\Location");
    }
}
