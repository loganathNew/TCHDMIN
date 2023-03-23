<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inward extends Model
{
    use SoftDeletes;
    //
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'location_id', 'week', 'duration', 'r_date', 'in_time', 'out_time', 'inv_no', 'inv_date',
        'lwt', 'ewt', 'nwt',
        'ecu', 'ecm', 'ecl', 'aec',
        'm1', 'm2', 'm3', 'am',
        'sand', 'fibre', 'a_bagwt',
        'freight', 'vehicle_no', 'transporter', 'storage_location', 'qc_name', 'remarks',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'lwt' => 'float', 'ewt' => 'float', 'nwt' => 'float',
        'ecu' => 'float', 'ecm' => 'float', 'ecl' => 'float', 'aec' => 'float',
        'm1' => 'float', 'm2' => 'float', 'm3' => 'float', 'am' => 'float',
        'sand' => 'float', 'fibre' => 'float', 'a_bagwt' => 'float',
        'freight' => 'float',
    ];


    public function products()
    {
        return $this->hasMany("App\Product");
    }

    public function setRDateAttribute($value)
    {
        $this->attributes['r_date'] = date("Y-m-d H:i:s", strtotime($value));
    }

    public function setInTimeAttribute($value)
    {
        $this->attributes['in_time'] = date("H:i:s", strtotime($value));
    }

    public function setOutTimeAttribute($value)
    {
        $this->attributes['out_time'] = date("H:i:s", strtotime($value));
    }

    public function setInvDateAttribute($value)
    {
        $this->attributes['inv_date'] = date("Y-m-d H:i:s", strtotime($value));
    }

}
