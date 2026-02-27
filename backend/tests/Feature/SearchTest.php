<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // use database driver which behaves like simple Eloquent search (no external service)
        config(['scout.driver' => 'database']);
    }

    public function test_search_returns_repositories_and_pull_requests(): void
    {
        // create data
        $repo = Repository::factory()->create([
            'full_name' => 'alice/example',
            'name' => 'example',
            'owner' => 'alice',
        ]);

        $pr = PullRequest::factory()->create([
            'repository_id' => $repo->id,
            'title' => 'Fix critical bug',
            'number' => 42,
        ]);

        // index the models explicitly (array driver doesn't automatically listen to events)
        $repo->searchable();
        $pr->searchable();

        $user = User::factory()->create([
            'username' => 'searchuser',
        ]);
        $this->actingAs($user, 'sanctum');

        // first query should return the repository but no pull requests
        $response = $this->getJson('/api/search?q=alice');
        fwrite(STDERR, "\nSEARCH RESPONSE BODY (alice): " . $response->getContent() . "\n");
        $response->assertOk();
        $response->assertJsonFragment(['full_name' => 'alice/example']);
        $this->assertIsArray($response->json('pull_requests'));
        $this->assertCount(0, $response->json('pull_requests'));

        // search by PR title
        $response = $this->getJson('/api/search?q=critical');
        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Fix critical bug']);
    }
}
