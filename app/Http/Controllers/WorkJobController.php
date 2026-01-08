<?php

namespace App\Http\Controllers;

use App\Models\WorkJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkJobController extends Controller
{
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
}
