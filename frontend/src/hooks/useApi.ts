/**
 * React Query Hooks
 * 
 * Custom hooks for data fetching with caching, refetching, and error handling.
 * All hooks use TanStack Query (React Query) for server state management.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type {
  PullRequestFilters,
  Alert,
  Repository,
} from '../types';
import { api } from '@api/client';
import toast from 'react-hot-toast';

// ============================================================================
// Query Keys (for cache invalidation)
// ============================================================================

export const queryKeys = {
  repositories: ['repositories'] as const,
  repository: (id: number) => ['repositories', id] as const,
  
  pullRequests: (repoId: number, filters?: PullRequestFilters) =>
    ['pull-requests', repoId, filters] as const,
  pullRequest: (id: number) => ['pull-requests', id] as const,
  
  dashboardStats: (repoId?: number) => ['dashboard-stats', repoId] as const,
  
  dailyMetrics: (repoId: number, dateFrom: string, dateTo: string) =>
    ['daily-metrics', repoId, dateFrom, dateTo] as const,
  
  testRuns: (repoId: number, prId?: number) =>
    ['test-runs', repoId, prId] as const,
  testRun: (id: number) => ['test-runs', id] as const,
  
  testResults: (testRunId: number) => ['test-results', testRunId] as const,
  flakyTests: (repoId: number | null) => ['flaky-tests', repoId] as const,
  
  alerts: (repoId?: number, status?: Alert['status']) =>
    ['alerts', repoId, status] as const,
};

// ============================================================================
// Repositories
// ============================================================================

export function useRepositories() {
  return useQuery({
    queryKey: queryKeys.repositories,
    queryFn: () => api.getRepositories(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
}

export function useRepository(id: number) {
  return useQuery({
    queryKey: queryKeys.repository(id),
    queryFn: () => api.getRepository(id),
    staleTime: 2 * 60 * 1000, // 2 minutes
    enabled: !!id,
  });
}

export function useCreateRepository() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (repositoryData: Partial<Repository>) => api.createRepository(repositoryData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.repositories });
      toast.success('Repository added successfully!');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to add repository');
    },
  });
}

// ============================================================================
// Pull Requests
// ============================================================================

export function usePullRequests(
  repositoryId: number,
  filters?: PullRequestFilters,
  page = 1,
  perPage = 20
) {
  return useQuery({
    queryKey: queryKeys.pullRequests(repositoryId, filters),
    queryFn: () => api.getPullRequests(repositoryId, filters, page, perPage),
    staleTime: 30 * 1000, // 30 seconds
    enabled: !!repositoryId,
  });
}

export function usePullRequest(id: number) {
  return useQuery({
    queryKey: queryKeys.pullRequest(id),
    queryFn: () => api.getPullRequest(id),
    staleTime: 30 * 1000, // 30 seconds
    enabled: !!id,
    // Refetch on window focus for real-time updates
    refetchOnWindowFocus: true,
  });
}

// ============================================================================
// Dashboard Stats
// ============================================================================

export function useDashboardStats(repositoryId?: number) {
  return useQuery({
    queryKey: queryKeys.dashboardStats(repositoryId),
    queryFn: () => api.getDashboardStats(repositoryId),
    staleTime: 60 * 1000, // 1 minute
    // Background refetch every 2 minutes
    refetchInterval: 2 * 60 * 1000,
  });
}

// ============================================================================
// Daily Metrics
// ============================================================================

export function useDailyMetrics(
  repositoryId: number,
  dateFrom: string,
  dateTo: string
) {
  return useQuery({
    queryKey: queryKeys.dailyMetrics(repositoryId, dateFrom, dateTo),
    queryFn: () => api.getDailyMetrics(repositoryId, dateFrom, dateTo),
    staleTime: 5 * 60 * 1000, // 5 minutes (metrics calculated daily)
    enabled: !!repositoryId && !!dateFrom && !!dateTo,
  });
}

// ============================================================================
// Test Runs
// ============================================================================

export function useTestRuns(repositoryId: number, pullRequestId?: number) {
  return useQuery({
    queryKey: queryKeys.testRuns(repositoryId, pullRequestId),
    queryFn: () => api.getTestRuns(repositoryId, pullRequestId),
    staleTime: 60 * 1000, // 1 minute
    enabled: !!repositoryId,
  });
}

export function useTestRun(id: number) {
  return useQuery({
    queryKey: queryKeys.testRun(id),
    queryFn: () => api.getTestRun(id),
    staleTime: 2 * 60 * 1000, // 2 minutes
    enabled: !!id,
  });
}

// ============================================================================
// Test Results & Flaky Tests
// ============================================================================

export function useTestResults(testRunId: number) {
  return useQuery({
    queryKey: queryKeys.testResults(testRunId),
    queryFn: () => api.getTestResults(testRunId),
    staleTime: 5 * 60 * 1000, // 5 minutes
    enabled: !!testRunId,
  });
}

export function useFlakyTests(repositoryId: number | null) {
  return useQuery({
    queryKey: queryKeys.flakyTests(repositoryId),
    queryFn: () => repositoryId ? api.getFlakyTests(repositoryId) : [],
    staleTime: 30 * 1000, // 30 seconds - reduced to ensure fresh data when switching
    enabled: !!repositoryId,
  });
}

// ============================================================================
// Alerts
// ============================================================================

export function useAlerts(repositoryId?: number, status?: Alert['status']) {
  return useQuery({
    queryKey: queryKeys.alerts(repositoryId, status),
    queryFn: () => api.getAlerts(repositoryId, status),
    staleTime: 30 * 1000, // 30 seconds
    // Background refetch every minute
    refetchInterval: 60 * 1000,
  });
}

export function useAcknowledgeAlert() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (alertId: number) => api.acknowledgeAlert(alertId),
    onSuccess: () => {
      // Invalidate alerts cache
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
      toast.success('Alert acknowledged');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to acknowledge alert');
    },
  });
}

export function useResolveAlert() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ alertId, notes }: { alertId: number; notes?: string }) =>
      api.resolveAlert(alertId, notes),
    onSuccess: () => {
      // Invalidate alerts cache
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
      toast.success('Alert resolved');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to resolve alert');
    },
  });
}

// ============================================================================
// Authentication
// ============================================================================

export function useLogin() {
  return useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) =>
      api.login(email, password),
    onSuccess: () => {
      toast.success('Logged in successfully');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Login failed');
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: () => api.logout(),
    onSuccess: () => {
      // Clear all cached data
      queryClient.clear();
      toast.success('Logged out successfully');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Logout failed');
    },
  });
}

// ============================================================================
// Helper Hooks
// ============================================================================

/**
 * Invalidate all queries (force refetch)
 */
export function useInvalidateAll() {
  const queryClient = useQueryClient();
  
  return () => {
    queryClient.invalidateQueries();
    toast.success('Data refreshed');
  };
}

/**
 * Check if any query is loading
 */
export function useIsLoading() {
  const queryClient = useQueryClient();
  const queries = queryClient.getQueryCache().getAll();
  
  return queries.some(query => query.state.fetchStatus === 'fetching');
}

