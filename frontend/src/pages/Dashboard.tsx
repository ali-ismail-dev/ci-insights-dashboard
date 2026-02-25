import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  GitPullRequest,
  CheckCircle2,
  AlertTriangle,
  Clock,
  TrendingUp,
  ArrowRight,
  Filter
} from 'lucide-react';
import { useDashboardStats, useRepositories, usePullRequests } from '../hooks/useApi';
import { formatDistanceToNow } from 'date-fns';

// ============================================================================
// Senior UI: Sub-Components
// ============================================================================

function StatCard({ icon: Icon, label, value, trend, color = 'blue' }: {
  icon: any;
  label: string;
  value: string | number;
  trend?: 'up' | 'down';
  color?: 'blue' | 'green' | 'red' | 'yellow';
}) {
  const colorMap = {
    blue: 'text-indigo-600 bg-indigo-50 border-indigo-100 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400',
    green: 'text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400',
    red: 'text-rose-600 bg-rose-50 border-rose-100 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400',
    yellow: 'text-amber-600 bg-amber-50 border-amber-100 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400',
  };

  return (
    <div className="relative group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none hover:-translate-y-1">
      <div className="flex items-center justify-between mb-4">
        <div className={`p-2.5 rounded-xl border ${colorMap[color]}`}>
          <Icon className="w-6 h-6" />
        </div>
        {trend && (
          <span className={`flex items-center text-xs font-bold px-2 py-1 rounded-full ${trend === 'up' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
            <TrendingUp className={`w-3 h-3 mr-1 ${trend === 'down' ? 'rotate-180' : ''}`} />
            {trend === 'up' ? 'Improved' : 'Needs attention'}
          </span>
        )}
      </div>
      <div>
        <h3 className="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{value}</h3>
        <p className="text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1">{label}</p>
      </div>
    </div>
  );
}

function PRListItem({ pr }: { pr: any }) {
  const statusConfig: Record<string, { label: string; class: string }> = {
    success: { label: 'Passed', class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20' },
    failure: { label: 'Failed', class: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20' },
    pending: { label: 'In Progress', class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20' },
  };

  const stateColors: Record<string, string> = {
    open: 'text-emerald-500',
    merged: 'text-indigo-500',
    closed: 'text-slate-400',
  };

  return (
    <Link to={`/pull-requests/${pr.id}`} className="group block px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4 min-w-0">
          <div className={`p-2 rounded-full bg-slate-100 dark:bg-slate-800 ${stateColors[pr.state] || 'text-slate-400'}`}>
            <GitPullRequest className="w-4 h-4" />
          </div>
          <div className="min-w-0">
            <h4 className="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-indigo-600 transition-colors">
              {pr.title}
            </h4>
            <div className="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
              <span className="font-mono bg-slate-100 dark:bg-slate-800 px-1 rounded">#{pr.number}</span>
              <span>•</span>
              <span className="font-medium text-slate-700 dark:text-slate-300">{pr.repository?.name}</span>
              <span>•</span>
              <span>{pr.created_at ? formatDistanceToNow(new Date(pr.created_at), { addSuffix: true }) : 'Just now'}</span>
            </div>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {pr.ci_status && (
            <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border ${statusConfig[pr.ci_status]?.class || ''}`}>
              {statusConfig[pr.ci_status]?.label || pr.ci_status}
            </span>
          )}
          <ArrowRight className="w-4 h-4 text-slate-300 group-hover:text-indigo-500 transform group-hover:translate-x-1 transition-all" />
        </div>
      </div>
    </Link>
  );
}

// ============================================================================
// Main Page Component
// ============================================================================

export default function Dashboard() {
  const [selectedRepo, setSelectedRepo] = useState<number | undefined>();
  const { data: stats, isLoading: statsLoading } = useDashboardStats(selectedRepo);
  const { data: repositories } = useRepositories();
  const firstRepoId = selectedRepo || repositories?.[0]?.id;
  const { data: recentPRs, isLoading: prsLoading } = usePullRequests(firstRepoId!, { state: 'all' }, 1, 5);

  return (
    <div className="max-w-7xl mx-auto pb-12">
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
        <div>
          <h1 className="text-4xl font-black text-slate-900 dark:text-white tracking-tight">System Overview</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-2 font-medium">Real-time CI/CD performance metrics across your organization.</p>
        </div>

        <div className="flex items-center gap-3">
          <div className="relative">
            <Filter className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <select
              value={selectedRepo || ''}
              onChange={(e) => setSelectedRepo(e.target.value ? Number(e.target.value) : undefined)}
              className="pl-10 pr-8 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none appearance-none cursor-pointer"
            >
              <option value="">Global View</option>
              {Array.isArray(repositories)
                ? repositories.map((repo: any) => (
                  <option key={repo.id} value={repo.id}>{repo.name}</option>
                ))
                : (repositories as any)?.data?.map((repo: any) => (
                  <option key={repo.id} value={repo.id}>{repo.name}</option>
                ))
              }

            </select>
          </div>
        </div>
      </div>

      {/* Primary Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        {statsLoading ? (
          [...Array(4)].map((_, i) => <div key={i} className="h-32 bg-slate-200 dark:bg-slate-800 animate-pulse rounded-2xl" />)
        ) : stats ? (
          <>
            <StatCard icon={GitPullRequest} label="Open PRs" value={stats.open_prs} color="blue" />
            <StatCard
              icon={CheckCircle2}
              label="Success Rate"
              value={`${Math.round(stats.ci_success_rate)}%`}
              trend={stats.ci_success_rate > 85 ? 'up' : 'down'}
              color={stats.ci_success_rate > 85 ? 'green' : 'red'}
            />
            <StatCard icon={Clock} label="Avg Cycle Time" value={`${Math.round(stats.avg_cycle_time_hours)}h`} color="yellow" />
            <StatCard icon={AlertTriangle} label="Flaky Tests" value={stats.flaky_tests_count} color={stats.flaky_tests_count > 5 ? 'red' : 'yellow'} />
          </>
        ) : null}
      </div>

      {/* Secondary Content Area */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Recent Activity: Takes 2 columns */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
          <div className="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h2 className="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
              Recent Activity
            </h2>
            <Link to="/repositories" className="text-xs font-bold text-indigo-600 hover:text-indigo-500 uppercase tracking-widest">View All</Link>
          </div>

          <div className="divide-y divide-slate-100 dark:divide-slate-800">
            {prsLoading ? (
              [...Array(3)].map((_, i) => <div key={i} className="h-20 bg-slate-50 dark:bg-slate-800/30 animate-pulse" />)
            ) : recentPRs?.data?.length ? (
              recentPRs.data.map((pr: any) => <PRListItem key={pr.id} pr={pr} />)
            ) : (
              <div className="py-12 text-center text-slate-400 font-medium">No recent activity found.</div>
            )}
          </div>
        </div>

        {/* Quick Insights Widget */}
        <div className="bg-indigo-600 rounded-2xl p-8 text-white shadow-lg shadow-indigo-200 dark:shadow-none relative overflow-hidden">
          <div className="relative z-10">
            <h3 className="text-xl font-black mb-2">Pro Insights</h3>
            <p className="text-indigo-100 text-sm font-medium leading-relaxed">
              Your average cycle time has decreased by 12% this week. Keep up the small PR sizes to maintain momentum!
            </p>
            <button className="mt-6 px-4 py-2 bg-white text-indigo-600 rounded-lg text-xs font-black uppercase tracking-wider hover:bg-indigo-50 transition-colors">
              Read Report
            </button>
          </div>
          <GitPullRequest className="absolute -bottom-6 -right-6 w-32 h-32 text-indigo-500/20 transform -rotate-12" />
        </div>
      </div>
    </div>
  );
}
