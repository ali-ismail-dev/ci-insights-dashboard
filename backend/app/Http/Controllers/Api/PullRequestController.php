<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PullRequestResource;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Pull Request API Controller
 * 
 * @package App\Http\Controllers\Api
 */
class PullRequestController extends Controller
{
    /**
     * List pull requests for a repository
     * 
     * GET /api/repositories/{repository}/pull-requests
     */
    public function index(Request $request, Repository $repository): AnonymousResourceCollection
    {
        $query = $repository->pullRequests()
            ->with(['author', 'repository']);

        // Apply filters
        if ($request->filled('state')) {
            if ($request->state === 'all') {
                // No filter
            } else {
                $query->where('state', $request->state);
            }
        }

        if ($request->filled('ci_status') && $request->ci_status !== 'all') {
            $query->where('ci_status', $request->ci_status);
        }

        if ($request->filled('is_stale')) {
            $query->where('is_stale', $request->boolean('is_stale'));
        }

        if ($request->filled('is_draft')) {
            $query->where('is_draft', $request->boolean('is_draft'));
        }

        if ($request->filled('author_id')) {
            $query->where('author_id', $request->author_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $pullRequests = $query->paginate($perPage);

        return PullRequestResource::collection($pullRequests);
    }

    /**
     * Get single pull request
     * 
     * GET /api/pull-requests/{pullRequest}
     */
    public function show(PullRequest $pullRequest): PullRequestResource
    {
        $pullRequest->load([
            'author',
            'repository',
            'testRuns' => function ($query) {
                $query->latest()->limit(10);
            },
            'fileChanges',
        ]);

        return new PullRequestResource($pullRequest);
    }
}