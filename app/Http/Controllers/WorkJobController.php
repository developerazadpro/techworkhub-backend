<?php

namespace App\Http\Controllers;

use App\Models\WorkJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobAssignment;
use Illuminate\Support\Facades\DB;

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
        ]);

        // 3. Create job
        $job = WorkJob::create([
            'client_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        // 4. Return response (temporary)
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
