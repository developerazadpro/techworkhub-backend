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

    public const STATUS_OPEN = 'open';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function isOpen(): bool 
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isAssigned(): bool 
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    public function isInProgress(): bool 
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool 
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool 
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignments()
    {
        return $this->hasMany(JobAssignment::class, 'work_job_id');
    }
}

