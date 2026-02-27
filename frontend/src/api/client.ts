/**
 * Senior API Client
 * 
 * Configured for Laravel Sanctum (Cookie-based auth)
 */

import axios, { AxiosError, AxiosInstance } from 'axios';
import type {
  Repository,
  PullRequest,
  PaginatedResponse,
  PullRequestFilters,
  DashboardStats,
  DailyMetric,
  TestRun,
  TestResult,
  Alert,
  ApiError,
} from '../types';

// ============================================================================
// Axios Instance Configuration
// ============================================================================

export const apiClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 30000,
  xsrfCookieName: 'XSRF-TOKEN', // The name of the cookie Laravel sends
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Required for Sanctum to avoid 302 redirects
  },
  withCredentials: true, // CRITICAL: Allows browser to send/receive Sanctum cookies
});

// ============================================================================
// Interceptors
// ============================================================================

apiClient.interceptors.request.use(
  (config) => {
    if (import.meta.env.DEV) {
      console.log(`%c[API Request] ${config.method?.toUpperCase()} ${config.url}`, 'color: #6366f1; font-weight: bold;');
    }
    return config;
  },
  (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const status = error.response?.status;
    
    // In a professional SPA, we don't use window.location.href for 401s
    // The AuthContext/ProtectedRoute handles the redirect gracefully
    if (status === 401 && !window.location.pathname.includes('/login')) {
      console.warn('[Auth] Session expired or invalid');
    }

    if (import.meta.env.DEV) {
      console.error(`%c[API Error] ${status}: ${error.message}`, 'color: #ef4444; font-weight: bold;', error.response?.data);
    }
    
    return Promise.reject(error);
  }
);

// ============================================================================
// Functional API Export (Senior Pattern for Tree Shaking)
// ============================================================================

export const api = {
  // Repositories
  getRepositories: () => 
    apiClient.get<Repository[]>('/repositories').then(r => r.data),
    
  getRepository: (id: number) => 
    apiClient.get<Repository>(`/repositories/${id}`).then(r => r.data),

  // Pull Requests
  getPullRequests: (repositoryId: number, filters?: PullRequestFilters, page = 1, perPage = 20) =>
    apiClient.get<PaginatedResponse<PullRequest>>(`/repositories/${repositoryId}/pull-requests`, {
      params: { ...filters, page, per_page: perPage },
    }).then(r => r.data),

  getPullRequest: (id: number) => 
    apiClient.get<PullRequest>(`/pull-requests/${id}`).then(r => r.data),

  // Dashboard
  getDashboardStats: (repositoryId?: number) => 
    apiClient.get<DashboardStats>('/dashboard/stats', {
      params: { repository_id: repositoryId },
    }).then(r => r.data),

  // Metrics
  getDailyMetrics: (repositoryId: number, dateFrom: string, dateTo: string) =>
    apiClient.get<DailyMetric[]>(`/repositories/${repositoryId}/metrics/daily`, {
      params: { date_from: dateFrom, date_to: dateTo },
    }).then(r => r.data),

  // Test Runs
  getTestRuns: (repositoryId: number, pullRequestId?: number) =>
    apiClient.get<TestRun[]>(`/repositories/${repositoryId}/test-runs`, {
      params: { pull_request_id: pullRequestId },
    }).then(r => r.data),

  getTestRun: (id: number) => 
    apiClient.get<TestRun>(`/test-runs/${id}`).then(r => r.data),

  getTestResults: async (testRunId: number): Promise<TestResult[]> => {
    const testRun = await apiClient.get<TestRun>(`/test-runs/${testRunId}`).then(r => r.data);
    return testRun.test_results || [];
  },

  getFlakyTests: (repositoryId: number) => 
    apiClient.get<TestResult[]>(`/repositories/${repositoryId}/flaky-tests`).then(r => r.data),

  // Alerts
  getAlerts: (repositoryId?: number, status?: Alert['status']) =>
    apiClient.get<PaginatedResponse<Alert>>('/alerts', {
      params: { repository_id: repositoryId, status, per_page: 20 },
    }).then(r => r.data.data),

  acknowledgeAlert: (id: number) => 
    apiClient.post<Alert>(`/alerts/${id}/acknowledge`).then(r => r.data),

  resolveAlert: (id: number, notes?: string) => 
    apiClient.post<Alert>(`/alerts/${id}/resolve`, { notes }).then(r => r.data),

  // Global search (returns both repositories and pull requests)
  search: (query: string) =>
    apiClient.get<{ repositories: Repository[]; pull_requests: PullRequest[] }>('/search', {
      params: { q: query },
    }).then(r => r.data),

  // Authentication
  login: (email: string, password: string) =>
    apiClient.post('/login', { email, password }),

  logout: () =>
    apiClient.post('/logout'),

  getCurrentUser: () =>
    apiClient.get('/api/user').then(r => r.data),};