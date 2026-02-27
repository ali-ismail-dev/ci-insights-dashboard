<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\TestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $repositoryId = $request->get('repository_id');
        
        $query = PullRequest::query();
        if ($repositoryId) {
            $query->where('repository_id', $repositoryId);
        }
        
        $totalPrs = (clone $query)->count();
        $openPrs = (clone $query)->where('state', 'open')->count();
        $mergedToday = (clone $query)
            ->where('state', 'merged')
            ->whereDate('merged_at', today())
            ->count();
        
        $avgCycleTime = (clone $query)
            ->where('state', 'merged')
            ->whereNotNull('cycle_time')
            ->avg('cycle_time');
        
        $avgCycleTimeHours = $avgCycleTime ? round($avgCycleTime / 3600, 1) : 0;
        
        $ciSuccessQuery = (clone $query)
            ->whereIn('ci_status', ['success', 'failure'])
            ->selectRaw('
                COUNT(CASE WHEN ci_status = "success" THEN 1 END) as passed,
                COUNT(*) as total
            ')
            ->first();
        
        $ciSuccessRate = $ciSuccessQuery->total > 0 
            ? round(($ciSuccessQuery->passed / $ciSuccessQuery->total) * 100, 1)
            : 0;
        
        $flakyTestsCount = TestResult::flaky()
            ->when($repositoryId, fn($q) => $q->where('repository_id', $repositoryId))
            ->count();
        
        $avgTestCoverage = (clone $query)
            ->whereNotNull('test_coverage')
            ->avg('test_coverage') ?? 0;
        
        $stalePrsCount = (clone $query)
            ->where('state', 'open')
            ->where('is_stale', true)
            ->count();
        
        return response()->json([
            'total_prs' => $totalPrs,
            'open_prs' => $openPrs,
            'merged_prs_today' => $mergedToday,
            'avg_cycle_time_hours' => $avgCycleTimeHours,
            'ci_success_rate' => $ciSuccessRate,
            'flaky_tests_count' => $flakyTestsCount,
            'avg_test_coverage' => round(floatval($avgTestCoverage), 1),
            'stale_prs_count' => $stalePrsCount,
        ]);
    }

    public function dailyMetrics(int $id, Request $request): \Illuminate\Http\JsonResponse
{
    $metrics = \App\Models\DailyMetric::where('repository_id', $id)
        ->where('metric_date', '>=', now()->subDays(30))
        ->orderBy('metric_date', 'asc')
        ->get()
        ->map(fn($m) => [
            'date' => $m->metric_date,
            'success_rate' => (float) $m->ci_success_rate,
            'cycle_time' => round($m->avg_cycle_time / 3600, 1),
        ]);

    return response()->json($metrics);
}

}