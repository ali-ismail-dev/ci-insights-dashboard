<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Simple search endpoint used by the SPA global search bar.
 *
 * Relies on Laravel Scout being configured (Meilisearch by default, Elasticsearch
 * can be used by setting SCOUT_DRIVER=elasticsearch and installing the driver).
 * Models which should appear in results must use the `Searchable` trait and
 * implement `toSearchableArray()`; both Repository and PullRequest already do.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $term = $data['q'];
        $limit = $data['limit'] ?? 10;

        // If we're running with the "database" driver (common in tests) we fall
        // back to a plain Eloquent query so we don't accidentally filter on
        // non-existent virtual columns (e.g. pull_requests.author).
        if (config('scout.driver') === 'database') {
            $repositories = Repository::query()
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->limit($limit)
                ->get();

            $pullRequests = PullRequest::query()
                ->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhere('number', 'like', "%{$term}%")
                        ->orWhere('state', 'like', "%{$term}%");
                })
                ->limit($limit)
                ->get();
        } else {
            $repositories = Repository::search($term)->take($limit)->get();
            $pullRequests = PullRequest::search($term)->take($limit)->get();
        }

        return response()->json([
            'repositories' => $repositories,
            'pull_requests' => $pullRequests,
        ]);
    }
}
