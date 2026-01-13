<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['name'];

    public function technicians()
    {
        return $this->belongsToMany(
            User::class,
            'technician_skill',
            'skill_id',
            'technician_id'
        );
    }
}
