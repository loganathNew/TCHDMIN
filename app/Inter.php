<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inter extends Model
{
    use SoftDeletes;
    
    //
    protected $fillable = [
        'from_id', 'to_id', 'date', 'inv_no', 'vehicle_no', 'remarks',
        'created_by', 'updated_by'
    ];

    public function products()
    {
        return $this->hasMany("App\InterProduct");
    }

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = date("Y-m-d H:i:s", strtotime($value));
    }
}
