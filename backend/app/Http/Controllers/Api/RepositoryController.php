<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Create a new repository with automatic GitHub metadata fetching
     * 
     * POST /api/repositories
     */
    public function store(Request $request): RepositoryResource
    {
        $validated = $request->validate([
            'full_name' => 'required|string|unique:repositories,full_name',
            'provider' => 'required|string|in:github,gitlab',
            // All other fields become nullable because we will fetch them!
            'external_id' => 'nullable|integer|unique:repositories,external_id',
            'name' => 'nullable|string',
            'owner' => 'nullable|string',
            'default_branch' => 'nullable|string',
            'html_url' => 'nullable|string',
        ]);

        // Senior Move: Auto-fetch metadata if it's a GitHub repo
        if ($validated['provider'] === 'github') {
            try {
                $response = \Illuminate\Support\Facades\Http::get("https://api.github.com{$validated['full_name']}");
                
                if ($response->successful()) {
                    $githubData = $response->json();
                    
                    // Merge GitHub data into our validated array
                    $validated = array_merge($validated, [
                        'external_id' => $githubData['id'],
                        'name' => $githubData['name'],
                        'owner' => $githubData['owner']['login'],
                        'default_branch' => $githubData['default_branch'],
                        'description' => $githubData['description'],
                        'language' => $githubData['language'],
                        'html_url' => $githubData['html_url'],
                        'clone_url' => $githubData['clone_url'],
                        'stars_count' => $githubData['stargazers_count'],
                        'forks_count' => $githubData['forks_count'],
                        'open_issues_count' => $githubData['open_issues_count'],
                        'is_private' => $githubData['private'],
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to fetch GitHub metadata: " . $e->getMessage());
                // Fall back to what the user provided if API fails
            }
        }

        // Final safety check: Ensure we have the critical ID
        if (empty($validated['external_id'])) {
            abort(422, 'Could not resolve GitHub Repository ID. Please provide it manually.');
        }

        $repository = Repository::create($validated);

        return new RepositoryResource($repository);
    }


    /**
     * Get single repository
     * 
     * GET /api/repositories/{id}
     */
   public function show(Repository $repository): RepositoryResource
{
    $repository->load([
        // Senior Move: Load the PRs AND the Author in one database trip
        'pullRequests' => function ($query) {
            $query->with('author')->latest()->limit(10);
        },
    ]);

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