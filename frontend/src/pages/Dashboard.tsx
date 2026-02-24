/**
 * Dashboard Page
 * 
 * Main overview showing key metrics and trends across all repositories.
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { 
  GitPullRequest, 
  CheckCircle2, 
  AlertTriangle, 
  Clock,
  TrendingUp
} from 'lucide-react';
import { 
  useDashboardStats, 
  useRepositories,
  usePullRequests 
} from '../hooks/useApi'; // Fixed alias to relative path to solve your other error
import { formatDistanceToNow } from 'date-fns';

// ============================================================================
// Components
// ============================================================================

function StatCard({ 
  icon: Icon, 
  label, 
  value, 
  change, 
  trend, 
  color = 'blue' 
}: {
  icon: any;
  label: string;
  value: string | number;
  change?: string;
  trend?: 'up' | 'down';
  color?: 'blue' | 'green' | 'red' | 'yellow';
}) {
  
  // Added Record<string, string> to allow dynamic indexing
  const colorClasses: Record<string, string> = {
    blue: 'bg-primary-50 text-primary-600',
    green: 'bg-success-50 text-success-600',
    red: 'bg-danger-50 text-danger-600',
    yellow: 'bg-warning-50 text-warning-600',
  };
  
  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-soft p-6">
      <div className="flex items-center justify-between">
        <div className={`p-3 rounded-lg ${colorClasses[color]}`}>
          <Icon className="w-6 h-6" />
        </div>
        
        {change && (
          <div className={`flex items-center text-sm font-medium ${
            trend === 'up' ? 'text-success-600' : 'text-danger-600'
          }`}>
            <TrendingUp className={`w-4 h-4 mr-1 ${trend === 'down' ? 'rotate-180' : ''}`} />
            {change}
          </div>
        )}
      </div>
      
      <div className="mt-4">
        <h3 className="text-2xl font-bold text-gray-900 dark:text-white">{value}</h3>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{label}</p>
      </div>
    </div>
  );
}

function PRListItem({ pr }: { pr: any }) {
  // Added Record<string, string> to solve TS7053
  const statusColors: Record<string, string> = {
    success: 'bg-success-100 text-success-800',
    failure: 'bg-danger-100 text-danger-800',
    pending: 'bg-warning-100 text-warning-800',
  };
  
  // Added Record<string, string> to solve TS7053
  const stateColors: Record<string, string> = {
    open: 'text-success-600',
    merged: 'text-primary-600',
    closed: 'text-gray-600',
  };
  
  return (
    <Link 
      to={`/pull-requests/${pr.id}`}
      className="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            {/* Added fallback to prevent crash if state is undefined */}
            <GitPullRequest className={`w-4 h-4 ${stateColors[pr.state] || 'text-gray-400'}`} />
            <span className="text-sm font-medium text-gray-900 dark:text-white truncate">
              {pr.title}
            </span>
          </div>
          
          <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
            <span>#{pr.number}</span>
            <span>•</span>
            <span>{pr.repository?.name}</span>
            <span>•</span>
            <span>{pr.created_at ? formatDistanceToNow(new Date(pr.created_at), { addSuffix: true }) : ''}</span>
          </div>
        </div>
        
        {pr.ci_status && (
          <span className={`ml-3 px-2 py-1 text-xs font-medium rounded ${
            statusColors[pr.ci_status] || 'bg-gray-100 text-gray-800'
          }`}>
            {pr.ci_status}
          </span>
        )}
      </div>
    </Link>
  );
}

// ============================================================================
// Dashboard Page
// ============================================================================

export default function Dashboard() {
  const [selectedRepo, setSelectedRepo] = useState<number | undefined>();
  
  // Fetch data
  const statsQuery = useDashboardStats(selectedRepo);
  const repoQuery = useRepositories();
  
  const stats = statsQuery.data;
  const repositories = repoQuery.data;
  
  // Fetch recent PRs
  const firstRepoId = selectedRepo || repositories?.[0]?.id;
  // Added non-null assertion (!) as per your logic
  const prQuery = usePullRequests(firstRepoId!, { state: 'all' }, 1, 5);
  const recentPRs = prQuery.data;
  
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Overview of your CI insights</p>
        </div>
        
        {repositories && repositories.length > 1 && (
          <select
            value={selectedRepo || ''}
            onChange={(e) => setSelectedRepo(e.target.value ? Number(e.target.value) : undefined)}
            className="px-4 py-2 border border-gray-300 rounded-lg"
          >
            <option value="">All Repositories</option>
            {repositories.map((repo: any) => (
              <option key={repo.id} value={repo.id}>{repo.full_name}</option>
            ))}
          </select>
        )}
      </div>
      
      {statsQuery.isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="bg-white rounded-lg p-6 animate-pulse h-32 bg-gray-100" />
          ))}
        </div>
      ) : stats ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard icon={GitPullRequest} label="Open Pull Requests" value={stats.open_prs} />
          <StatCard 
            icon={CheckCircle2} 
            label="CI Success Rate" 
            value={`${Math.round(stats.ci_success_rate)}%`} 
            trend={stats.ci_success_rate > 80 ? 'up' : 'down'}
            color={stats.ci_success_rate > 80 ? 'green' : 'red'}
          />
          <StatCard icon={Clock} label="Avg Cycle Time" value={`${Math.round(stats.avg_cycle_time_hours)}h`} color="yellow" />
          <StatCard icon={AlertTriangle} label="Flaky Tests" value={stats.flaky_tests_count} color="red" />
        </div>
      ) : null}

      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-soft">
        <div className="p-6 border-b border-gray-200">
          <h2 className="text-lg font-semibold">Recent Activity</h2>
        </div>
        <div className="divide-y divide-gray-200">
          {recentPRs?.data?.map((pr: any) => (
            <PRListItem key={pr.id} pr={pr} />
          ))}
        </div>
      </div>
    </div>
  );
}
