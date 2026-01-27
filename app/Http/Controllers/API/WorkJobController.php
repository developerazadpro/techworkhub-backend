<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;

use App\Models\WorkJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobAssignment;
use Illuminate\Support\Facades\DB;
use App\Services\GoMatchingService;
use App\Models\User;
use App\Support\JobStatusTransition;

class WorkJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('technician')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $jobs = WorkJob::where('status', 'open')->latest()->get();
        
        $jobs = $jobs->map(function ($job) {

                // skills stored as JSON string        
                $skills = is_array($job->skills) ? $job->skills : json_decode($job->skills ?? '[]', true);

                // Handle recommended_technicians JSON
                $recommendedTechnicians = is_array($job->recommended_technicians)
                    ? $job->recommended_technicians
                    : json_decode($job->recommended_technicians ?? '[]', true);

                return [
                    'id' => $job->id,
                    'client_id' => $job->client_id,
                    'title' => $job->title,
                    'description' => $job->description,                    
                    'skills' => $skills,                    
                    'status' => $job->status,
                    'recommended_technicians' => $recommendedTechnicians,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                ];
        });
        
        // Success response
        return response()->json([
            'success' => true,
            'jobs' => $jobs
        ], 200);
    }

    public function store(Request $request)
    {
        // 1. Authorization: only clients can create jobs
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$user->hasRole('client')) {
            return response()->json(['message' => 'Only clients can create jobs.'], 403);
        }

        // 2. Validate input
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'skills' => 'required|array|min:1',
            'skills.*' => 'string',
        ]);

        // 3. Create job
        $job = WorkJob::create([
            'client_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'skills' => $validated['skills'],
            'status' => 'open',
        ]);

        // 4. Call Go matching service (non-blocking)
        try {
            $matchingService = new GoMatchingService();

            // Fetch all technicians with their skills
           $technicians = User::whereHas('roles', function ($q) {
                            $q->where('name', 'technician');
                        })
                        ->with('skills')
                        ->get();

            $response = $matchingService->matchTechnicians([
                'job_id' => $job->id,
                'required_skills' => $job->skills,
                'technicians' => $technicians->map(fn ($tech) => [
                    'id' => $tech->id,
                    'skills' => $tech->skills->pluck('name')->toArray(),
                ])->toArray(),
            ]);

            $job->update([
                'recommended_technicians' => $response['recommended_technicians'] ?? [],
            ]);

        } catch (\Throwable $e) {
            logger()->error('Go matching failed', [
                'work_job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 5. Refresh job to get updated recommended_technicians
        $job->refresh();

        // 6. Return response
        return response()->json([
            'message' => 'Job created successfully',
            'job' => $job,
        ], 201);
    }

    public function show($id)
    {
        $job = WorkJob::with('client:id,name,created_at')->findOrFail($id);

        return response()->json([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'skills' => $job->skills ?? [],
                'status' => $job->status,
                'created_at' => $job->created_at->toDateString(),
                'posted_ago' => $job->created_at->diffForHumans(),
                'client' => [
                    'id' => $job->client_id,
                    'jobs_posted' => WorkJob::where('client_id', $job->client_id)->count(),
                    'member_since' => $job->client->created_at->toDateString(),
                ]
            ]
        ], 200);
    }

    public function accept(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$user->hasRole('technician')) {
            return response()->json(['message' => 'Only technicians can accept jobs.'], 403);
        }

        DB::transaction(function () use ($id) {
            $job = WorkJob::lockForUpdate()->findOrFail($id);

            if ($job->status !== 'open') {
                abort(409, 'Job already assigned.');                
            }
            

            JobAssignment::create([
                'work_job_id' => $job->id,
                'technician_id' => Auth::id(),
            ]);

            $job->update(['status' => 'assigned']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Job accepted successfully'
        ], 200);
        
    }

    public function myJobs()
    {
        // 1️ Ensure user is authenticated
        $technicianId = Auth::id();

        if (!$technicianId) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // 2️ Fetch jobs assigned to technician, descending order
        $jobs = WorkJob::whereHas('assignments', function ($q) use ($technicianId) {
                $q->where('technician_id', $technicianId);
            })
            ->latest() // orders by created_at DESC
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'client_id' => $job->client_id,
                    'title' => $job->title,
                    'description' => $job->description,
                    'skills' => $job->skills,
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                ];
            });

        // 3️ Success response
        return response()->json([
            'success' => true,
            'jobs' => $jobs
        ], 200);
    }

    public function clientJobs(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('client')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }       

        $jobs = WorkJob::where('client_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'jobs' => $jobs
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,assigned,in_progress,completed,cancelled',
        ]);

        $job = WorkJob::with('client:id,name,email,created_at')->findOrFail($id);

        $currentStatus = $job->status;
        $nextStatus = $request->status;

        if (! JobStatusTransition::canTransition($currentStatus, $nextStatus)) {
            return response()->json([
                'message' => "Invalid status transition from {$currentStatus} to {$nextStatus}",
                'allowed' => JobStatusTransition::allowed()[$currentStatus] ?? [],
            ], 422);
        }

        // Avoid unnecessary update
        if ($job->status === $validated['status']) {
            return response()->json([
                'success' => false,
                'message' => 'Status is already set to this value',
            ], 422);
        }

        $job->status = $request->status;
        $job->save();

        // Optional: Trigger notifications here
        // Notification::dispatch($job);

        return response()->json([
            'success' => true,
            'message' => 'Job status updated successfully',
            'job' => $job,
            'allowed_next_statuses' => JobStatusTransition::allowed()[$nextStatus],
        ]);
    }
}
