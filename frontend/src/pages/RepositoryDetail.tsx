import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { 
  useRepository, 
  usePullRequests, 
  useDashboardStats 
} from '../hooks/useApi';
import LoadingSpinner from '../components/LoadingSpinner';
import { 
  GitPullRequest, 
  Settings, 
  Activity, 
  Clock, 
  CheckCircle2, 
  ChevronLeft,
  ExternalLink
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

// ============================================================================
// Internal Components
// ============================================================================

function MetricTile({ label, value, icon: Icon, color }: { label: string, value: string | number, icon: any, color: string }) {
  return (
    <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
      <div className="flex items-center gap-4">
        <div className={`p-2.5 rounded-xl ${color}`}>
          <Icon className="w-5 h-5" />
        </div>
        <div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-widest">{label}</p>
          <p className="text-xl font-black text-slate-900 dark:text-white mt-0.5">{value}</p>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Main Page
// ============================================================================

export default function RepositoryDetail() {
  const { id } = useParams<{ id: string }>();
  const repoId = Number(id);
  const [activeTab, setActiveTab] = useState<'overview' | 'pull-requests' | 'settings'>('overview');

  const { data: repo, isLoading: repoLoading } = useRepository(repoId);
  const { data: stats, isLoading: statsLoading } = useDashboardStats(repoId);
  const { data: prs, isLoading: prsLoading } = usePullRequests(repoId, { state: 'all' }, 1, 10);

  if (repoLoading || statsLoading) return <div className="h-96 flex items-center justify-center"><LoadingSpinner /></div>;
  if (!repo) return <div className="text-center py-20 font-bold text-slate-500">Repository not found.</div>;

  return (
    <div className="max-w-7xl mx-auto pb-12">
      {/* Breadcrumb & Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <div className="flex flex-col gap-2">
          <Link to="/repositories" className="flex items-center text-sm font-bold text-indigo-600 hover:text-indigo-500 transition-colors">
            <ChevronLeft className="w-4 h-4 mr-1" /> Back to Repositories
          </Link>
          <div className="flex items-center gap-3">
            <h1 className="text-3xl font-black text-slate-900 dark:text-white">{repo.name}</h1>
            <span className="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-black text-slate-500 uppercase tracking-widest border border-slate-200 dark:border-slate-700">
              {repo.provider}
            </span>
          </div>
          <p className="text-slate-500 font-medium">{repo.full_name}</p>
        </div>

        <div className="flex items-center gap-3">
          <a 
            href={repo.html_url} 
            target="_blank" 
            rel="noopener noreferrer"
            className="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-all"
          >
            <ExternalLink className="w-4 h-4" /> View on GitHub
          </a>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-800 mb-8">
        {[
          { id: 'overview', label: 'Overview', icon: Activity },
          { id: 'pull-requests', label: 'Pull Requests', icon: GitPullRequest },
          { id: 'settings', label: 'Settings', icon: Settings },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            className={`flex items-center gap-2 px-6 py-4 text-sm font-bold border-b-2 transition-all ${
              activeTab === tab.id 
                ? 'border-indigo-600 text-indigo-600' 
                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            <tab.icon className="w-4 h-4" />
            {tab.label}
          </button>
        ))}
      </div>

      {/* Content Rendering */}
      {activeTab === 'overview' && (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500">
          {/* Quick Stats Grid */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <MetricTile 
              label="Success Rate" 
              value={`${Math.round(stats?.ci_success_rate || 0)}%`} 
              icon={CheckCircle2} 
              color="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400" 
            />
            <MetricTile 
              label="Avg Cycle Time" 
              value={`${Math.round(stats?.avg_cycle_time_hours || 0)}h`} 
              icon={Clock} 
              color="bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400" 
            />
            <MetricTile 
              label="Open PRs" 
              value={stats?.open_prs || 0} 
              icon={GitPullRequest} 
              color="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400" 
            />
          </div>

          {/* Repo Info Card */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8">
             <h3 className="text-lg font-black text-slate-900 dark:text-white mb-4">Repository Analytics</h3>
             <div className="h-64 flex items-center justify-center border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-2xl">
                <p className="text-slate-400 font-bold italic">Charts coming soon: Coverage & Performance Trends</p>
             </div>
          </div>
        </div>
      )}

      {activeTab === 'pull-requests' && (
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm animate-in fade-in duration-300">
           {prsLoading ? (
             <div className="p-20 flex justify-center"><LoadingSpinner /></div>
           ) : prs?.data?.length ? (
             <div className="divide-y divide-slate-100 dark:divide-slate-800">
                {prs.data.map((pr: any) => (
                  <div key={pr.id} className="p-6 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                     <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                           <div className="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                              <GitPullRequest className="w-5 h-5 text-indigo-500" />
                           </div>
                           <div>
                              <h4 className="font-bold text-slate-900 dark:text-white">#{pr.number} {pr.title}</h4>
                              <p className="text-xs text-slate-500 font-medium">
                                Created {formatDistanceToNow(new Date(pr.created_at), { addSuffix: true })} by {pr.user?.login || 'testuser'}
                              </p>
                           </div>
                        </div>
                        <Link to={`/pull-requests/${pr.id}`} className="px-4 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-black uppercase hover:bg-indigo-600 hover:text-white transition-all">
                           Details
                        </Link>
                     </div>
                  </div>
                ))}
             </div>
           ) : (
             <div className="p-20 text-center text-slate-400 font-bold italic">No pull requests found for this repository.</div>
           )}
        </div>
      )}
    </div>
  );
}
