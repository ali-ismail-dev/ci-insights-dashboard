<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestRunResource;
use App\Models\Repository;
use App\Models\TestRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestRunController extends Controller
{
    public function index(Request $request, Repository $repository): AnonymousResourceCollection
    {
        $query = $repository->testRuns()
            ->with(['pullRequest']);
        
        if ($request->filled('pull_request_id')) {
            $query->where('pull_request_id', $request->pull_request_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $query->latest('started_at');
        
        $testRuns = $query->paginate($request->get('per_page', 20));
        
        return TestRunResource::collection($testRuns);
    }
    
    public function show(TestRun $testRun): TestRunResource
    {
        $testRun->load(['pullRequest', 'repository', 'testResults']);
        
        return new TestRunResource($testRun);
    }
    
    public function flakyTests(Repository $repository)
    {
        // Debug: Log repository info
        \Log::info('Flaky tests requested for repository', [
            'repository_id' => $repository->id,
            'repository_name' => $repository->full_name,
        ]);
        
        $flakyTests = $repository->testResults()
            ->flaky()
            ->with(['testRun'])
            ->orderBy('flakiness_score', 'desc')
            ->limit(50)
            ->get();
        
        // Debug: Log query results
        \Log::info('Flaky tests query results', [
            'repository_id' => $repository->id,
            'count' => $flakyTests->count(),
            'first_test_id' => $flakyTests->first()?->id,
        ]);
        
        return response()->json($flakyTests);
    }
}