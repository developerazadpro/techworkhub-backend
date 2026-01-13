<?php

namespace App\Http\Controllers;

use App\Models\WorkJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobAssignment;
use Illuminate\Support\Facades\DB;
use App\Services\GoMatchingService;
use App\Models\User;

class WorkJobController extends Controller
{
    public function index()
    {
        if (!Auth::user()->hasRole('technician')) {
            abort(403, 'Only technicians can view jobs.');
        }

        $jobs = WorkJob::where('status', 'open')->get();

        return response()->json($jobs);
    }

    public function store(Request $request)
    {
        // 1. Authorization: only clients can create jobs
        if (!Auth::user()->hasRole('client')) {
            abort(403, 'Only clients can create jobs.');
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
            $technicians = User::role('technician')
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

        // 5. Return response
        return response()->json([
            'message' => 'Job created successfully',
            'job' => $job,
        ], 201);
    }

    public function accept($id)
    {
        if (!Auth::user()->hasRole('technician')) {
            abort(403, 'Only technicians can accept jobs.');
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
