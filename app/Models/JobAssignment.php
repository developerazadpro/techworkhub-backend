<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobAssignment extends Model
{
    protected $fillable = [
        'work_job_id',
        'technician_id',
    ];

    public function job()
    {
        return $this->belongsTo(WorkJob::class, 'work_job_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
