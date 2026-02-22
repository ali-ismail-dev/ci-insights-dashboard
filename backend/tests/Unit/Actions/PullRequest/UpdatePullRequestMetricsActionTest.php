<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\PullRequest;

use App\Actions\PullRequest\UpdatePullRequestMetricsAction;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Update Pull Request Metrics Action Test
 *
 * @package Tests\Unit\Actions\PullRequest
 */
class UpdatePullRequestMetricsActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdatePullRequestMetricsAction $action;
    private PullRequest $pullRequest;
    private Repository $repository;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdatePullRequestMetricsAction();

        $this->repository = Repository::factory()->create([
            'name' => 'test-repo',
            'full_name' => 'owner/test-repo',
        ]);

        $this->user = User::factory()->create([
            'username' => 'testuser',
        ]);

        $this->pullRequest = PullRequest::factory()->create([
            'repository_id' => $this->repository->id,
            'author_id' => $this->user->id,
            'number' => 1,
            'state' => 'open',
            'title' => 'Test PR',
            'additions' => 100,
            'deletions' => 50,
            'comments_count' => 5,
            'review_comments_count' => 3,
        ]);
    }

    public function test_updates_review_metrics_for_pr_without_reviews(): void
    {
        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertEquals(0, $this->pullRequest->approvals_count);
        $this->assertEquals('pending', $this->pullRequest->review_status);
    }

    public function test_updates_review_metrics_for_pr_with_approvals(): void
    {
        // Simulate approvals in metadata
        $this->pullRequest->update([
            'metadata' => ['approvals_count' => 2],
        ]);

        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertEquals(2, $this->pullRequest->approvals_count);
        $this->assertEquals('approved', $this->pullRequest->review_status);
    }

    public function test_updates_ci_metrics_from_test_runs(): void
    {
        // Create test runs for this PR
        TestRun::factory()->create([
            'repository_id' => $this->repository->id,
            'pull_request_id' => $this->pullRequest->id,
            'status' => 'success',
            'total_tests' => 50,
            'passed_tests' => 48,
            'failed_tests' => 2,
            'line_coverage' => 85.5,
        ]);

        TestRun::factory()->create([
            'repository_id' => $this->repository->id,
            'pull_request_id' => $this->pullRequest->id,
            'status' => 'success',
            'total_tests' => 30,
            'passed_tests' => 30,
            'failed_tests' => 0,
            'line_coverage' => 92.1,
        ]);

        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertEquals('success', $this->pullRequest->ci_status);
        $this->assertEquals(80, $this->pullRequest->ci_checks_count); // 50 + 30
        $this->assertEquals(78, $this->pullRequest->ci_checks_passed); // 48 + 30
        $this->assertEquals(2, $this->pullRequest->ci_checks_failed);
        $this->assertEquals(88.8, $this->pullRequest->test_coverage); // Average of 85.5 and 92.1
    }

    public function test_updates_test_metrics_aggregation(): void
    {
        // Create multiple test runs
        TestRun::factory()->create([
            'repository_id' => $this->repository->id,
            'pull_request_id' => $this->pullRequest->id,
            'total_tests' => 100,
            'passed_tests' => 95,
            'failed_tests' => 5,
        ]);

        TestRun::factory()->create([
            'repository_id' => $this->repository->id,
            'pull_request_id' => $this->pullRequest->id,
            'total_tests' => 50,
            'passed_tests' => 50,
            'failed_tests' => 0,
        ]);

        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertEquals(150, $this->pullRequest->tests_total);
        $this->assertEquals(145, $this->pullRequest->tests_passed);
        $this->assertEquals(5, $this->pullRequest->tests_failed);
    }

    public function test_marks_pr_as_hot_when_criteria_met(): void
    {
        $this->pullRequest->update([
            'comments_count' => 15, // > 10
            'additions' => 500,
            'deletions' => 200, // Total changes > 1000
        ]);

        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertTrue($this->pullRequest->is_hot);
    }

    public function test_updates_last_activity_timestamp(): void
    {
        $originalTimestamp = $this->pullRequest->last_activity_at;

        // Simulate some time passing
        $this->travel(1)->hour();

        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertNotEquals($originalTimestamp, $this->pullRequest->last_activity_at);
    }

    public function test_handles_pr_without_test_runs(): void
    {
        $this->action->execute($this->pullRequest);

        $this->pullRequest->refresh();

        $this->assertNull($this->pullRequest->ci_status);
        $this->assertEquals(0, $this->pullRequest->ci_checks_count);
        $this->assertEquals(0, $this->pullRequest->tests_total);
        $this->assertNull($this->pullRequest->test_coverage);
    }
}