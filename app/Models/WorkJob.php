<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkJob extends Model
{
    protected $table = 'work_jobs';

    protected $fillable = ['client_id', 'title', 'description', 'status'];

    
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}

