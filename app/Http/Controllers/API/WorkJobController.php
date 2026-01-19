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

class WorkJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('technician')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $jobs = WorkJob::where('status', 'open')->get();
        
        $jobs = $jobs->map(function ($job) {

                // skills stored as JSON string        
                $skills = is_array($job->skills) ? $job->skills : json_decode($job->skills ?? '[]', true);

                // convert technician IDs to names
                $technicianIds = is_array($job->recommended_technicians) ? $job->recommended_technicians : json_decode($job->technicians ?? '[]', true);

                return [
                    'id' => $job->id,
                    'client_id' => $job->client_id,
                    'title' => $job->title,
                    'description' => $job->description,                    
                    'skills' => $skills,                    
                    'recommended_technicians' => User::whereIn('id', $technicianIds)->pluck('name')->toArray(),
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                ];
        });
        
        return response()->json($jobs);
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
                abort(400, 'Job is not available.');
            }

            JobAssignment::create([
                'work_job_id' => $job->id,
                'technician_id' => Auth::id(),
            ]);

            $job->update(['status' => 'assigned']);
        });

        return response()->json([
            'message' => 'Job accepted successfully'
        ]);
    }

}
