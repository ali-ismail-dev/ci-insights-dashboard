/**
 * Frontend TypeScript Types
 * 
 * Generated from Laravel backend DTOs using Spatie Laravel TypeScript Transformer
 * Keep in sync with backend models
 */

// ============================================================================
// Repository Types
// ============================================================================

export interface Repository {
  id: number;
  external_id: number;
  provider: 'github' | 'gitlab' | 'bitbucket';
  full_name: string;
  name: string;
  owner: string;
  default_branch: string;
  description: string | null;
  language: string | null;
  html_url: string;
  stars_count: number;
  forks_count: number;
  open_issues_count: number;
  ci_enabled: boolean;
  is_active: boolean;
  is_private: boolean;
  last_synced_at: string | null;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Pull Request Types
// ============================================================================

export interface PullRequest {
  id: number;
  repository_id: number;
  author_id: number | null;
  external_id: number;
  number: number;
  state: 'open' | 'closed' | 'merged';
  title: string;
  description: string | null;
  head_branch: string;
  base_branch: string;
  head_sha: string;
  base_sha: string;
  html_url: string;
  
  // Statistics
  additions: number;
  deletions: number;
  changed_files: number;
  commits_count: number;
  comments_count: number;
  
  // Review status
  review_status: string | null;
  approvals_count: number;
  review_comments_count: number;
  
  // CI status
  ci_status: 'success' | 'failure' | 'pending' | 'error' | null;
  ci_checks_count: number;
  ci_checks_passed: number;
  ci_checks_failed: number;
  
  // Test metrics
  test_coverage: number | null;
  tests_total: number;
  tests_passed: number;
  tests_failed: number;
  tests_skipped: number;
  
  // Time metrics (seconds)
  cycle_time: number | null;
  time_to_first_review: number | null;
  time_to_approval: number | null;
  time_to_merge: number | null;
  
  // Flags
  is_draft: boolean;
  is_mergeable: boolean | null;
  is_hot: boolean;
  is_stale: boolean;
  
  // Labels and assignees
  labels: string[];
  assignees: number[];
  requested_reviewers: number[];
  
  // Timestamps
  first_commit_at: string | null;
  first_review_at: string | null;
  approved_at: string | null;
  merged_at: string | null;
  closed_at: string | null;
  last_activity_at: string | null;
  created_at: string;
  updated_at: string;
  
  // Relations (when included)
  repository?: Repository;
  author?: User;
}

// ============================================================================
// User Types
// ============================================================================

export interface User {
  id: number;
  external_id: number | null;
  provider: 'github' | 'gitlab';
  username: string;
  name: string | null;
  email: string;
  avatar_url: string | null;
  bio: string | null;
  location: string | null;
  company: string | null;
  website_url: string | null;
  role: 'admin' | 'member' | 'viewer';
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Test Run Types
// ============================================================================

export interface TestRun {
  id: number;
  repository_id: number;
  pull_request_id: number | null;
  ci_provider: string;
  external_id: string;
  workflow_name: string;
  job_name: string | null;
  branch: string;
  commit_sha: string;
  status: 'success' | 'failure' | 'error' | 'canceled' | 'skipped';
  
  // Test results
  total_tests: number;
  passed_tests: number;
  failed_tests: number;
  skipped_tests: number;
  flaky_tests: number;
  
  // Coverage
  line_coverage: number | null;
  branch_coverage: number | null;
  method_coverage: number | null;
  
  // Timing
  duration: number | null;
  started_at: string | null;
  completed_at: string | null;
  
  // URLs
  run_url: string | null;
  logs_url: string | null;
  
  created_at: string;
  updated_at: string;
  
  // Relations
  pull_request?: PullRequest;
  test_results?: TestResult[];
}

// ============================================================================
// Test Result Types
// ============================================================================

export interface TestResult {
  id: number;
  test_run_id: number;
  repository_id: number;
  test_identifier: string;
  test_name: string;
  test_file: string;
  test_class: string | null;
  test_method: string | null;
  status: 'passed' | 'failed' | 'skipped' | 'error';
  duration: number | null;
  error_message: string | null;
  stack_trace: string | null;
  failure_type: string | null;
  is_flaky: boolean;
  passed_on_retry: boolean;
  retry_count: number;
  flakiness_score: number | null;
  failure_rate: number | null;

// ---------------------------------------------------------------------------
// Search API
// ---------------------------------------------------------------------------
export interface SearchResponse {
  repositories: Repository[];
  pull_requests: PullRequest[];
}
  tags: string[];
  executed_at: string;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Daily Metrics Types
// ============================================================================

export interface DailyMetric {
  id: number;
  repository_id: number;
  metric_date: string;
  
  // PR metrics
  prs_opened: number;
  prs_merged: number;
  prs_closed: number;
  prs_active: number;
  avg_cycle_time: number | null;
  median_cycle_time: number | null;
  avg_time_to_first_review: number | null;
  avg_time_to_merge: number | null;
  
  // CI metrics
  test_runs_total: number;
  test_runs_passed: number;
  test_runs_failed: number;
  ci_success_rate: number | null;
  avg_test_duration: number | null;
  
  // Coverage metrics
  avg_line_coverage: number | null;
  avg_branch_coverage: number | null;
  coverage_trend: number | null;
  
  // Flakiness metrics
  flaky_tests_detected: number;
  flaky_tests_fixed: number;
  avg_flakiness_score: number | null;
  
  // Contributor metrics
  active_contributors: number;
  total_commits: number;
  total_code_changes: number;
  
  // Alert metrics
  alerts_triggered: number;
  alerts_resolved: number;
  
  is_final: boolean;
  calculated_at: string | null;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Alert Types
// ============================================================================

export interface Alert {
  id: number;
  alert_rule_id: number;
  repository_id: number | null;
  pull_request_id: number | null;
  alert_type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  title: string;
  message: string;
  context: Record<string, unknown>;
  status: 'open' | 'acknowledged' | 'resolved' | 'dismissed';
  acknowledged_at: string | null;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// API Response Types
// ============================================================================

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}

// ============================================================================
// Filter & Sort Types
// ============================================================================

export interface PullRequestFilters {
  repository_id?: number;
  state?: 'open' | 'closed' | 'merged' | 'all';
  ci_status?: 'success' | 'failure' | 'pending' | 'error' | 'all';
  is_stale?: boolean;
  is_draft?: boolean;
  author_id?: number;
  search?: string;
  labels?: string[];
  date_from?: string;
  date_to?: string;
}

export interface SortOptions {
  field: string;
  direction: 'asc' | 'desc';
}

// ============================================================================
// Dashboard Stat Types
// ============================================================================

export interface DashboardStats {
  total_prs: number;
  open_prs: number;
  merged_prs_today: number;
  avg_cycle_time_hours: number;
  ci_success_rate: number;
  flaky_tests_count: number;
  avg_test_coverage: number;
  stale_prs_count: number;
}

// ============================================================================
// Chart Data Types
// ============================================================================

export interface ChartDataPoint {
  date: string;
  value: number;
  label?: string;
}

export interface TimeSeriesData {
  labels: string[];
  datasets: {
    label: string;
    data: number[];
    color: string;
  }[];
}