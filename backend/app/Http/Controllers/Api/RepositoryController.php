<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Repository API Controller
 * 
 * @package App\Http\Controllers\Api
 */
class RepositoryController extends Controller
{
    /**
     * List all repositories
     * 
     * GET /api/repositories
     */
    public function index(): AnonymousResourceCollection
    {
        $repositories = Repository::active()
            ->withCount(['pullRequests'])
            ->orderBy('full_name')
            ->get();

        return RepositoryResource::collection($repositories);
    }

    /**
     * Get single repository
     * 
     * GET /api/repositories/{id}
     */
    public function show(Repository $repository): RepositoryResource
    {
        $repository->loadCount([
            'pullRequests',
            'pullRequests as open_prs_count' => function ($query) {
                $query->where('state', 'open');
            },
            'testRuns',
            'alertRules',
        ]);

        return new RepositoryResource($repository);
    }
}