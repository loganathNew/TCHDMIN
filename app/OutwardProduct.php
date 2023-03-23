<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutwardProduct extends Model
{
    use SoftDeletes;
    //
    protected $fillable = [
        'items', 'gb_size', 'mixture', 'outward_id', 'quality', 'plant_hole', 'pcs_pallet', 'pallet', 'total_pcs', 'nwt', 'remarks'
    ];

    protected $casts = [
        'pcs_pallet' => 'float',
        'pallet' => 'float',
        'total_pcs' => 'float',
        'nwt' => 'float',
        'items' => 'json',
    ];
    

    public function outward()
    {
        return $this->belongsTo("App\Outward",'outward_id','id');
    }

    // public function getItemsAttribute($value){
    //     return (array) $value;
    // }
}
