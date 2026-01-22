<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkJob extends Model
{
    protected $table = 'work_jobs';

    protected $fillable = ['client_id', 'title', 'description', 'status', 'skills', 'recommended_technicians'];

    protected $casts = [
        'skills' => 'array',
        'recommended_technicians' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignments()
    {
        return $this->hasMany(JobAssignment::class, 'work_job_id');
    }
}

