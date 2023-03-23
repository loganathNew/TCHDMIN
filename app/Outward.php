<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outward extends Model
{
    use SoftDeletes;
    
    //
    //
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'location_id', 'date', 'inv_no', 'project_no', 'vehicle_no', 'container_no',
        'created_by', 'updated_by'
    ];

    public function products()
    {
        return $this->hasMany("App\OutwardProduct");
    }

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = date("Y-m-d H:i:s", strtotime($value));
    }
}
