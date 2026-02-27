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

import { 
  AreaChart, 
  Area, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer 
} from 'recharts';
import { useDailyMetrics } from '../hooks/useApi'; // Ensure this path is correct
import { subDays, format } from 'date-fns';


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

  const { data: repoResponse, isLoading: repoLoading } = useRepository(repoId);
  const { data: stats, isLoading: statsLoading } = useDashboardStats(repoId);
  const { data: prs, isLoading: prsLoading } = usePullRequests(repoId, { state: 'all' }, 1, 10);

  // Calculate date range for metrics
  const dateTo = format(new Date(), 'yyyy-MM-dd');
  const dateFrom = format(subDays(new Date(), 30), 'yyyy-MM-dd');
  
  // All hooks must be called before any conditional returns
  const { data: metrics, isLoading: metricsLoading } = useDailyMetrics(repoId, dateFrom, dateTo);

  // Extract the actual repo object from the 'data' wrapper if it exists
  const repo = (repoResponse as any)?.data || repoResponse;

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
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
  <h3 className="text-lg font-black text-slate-900 dark:text-white mb-6 flex items-center gap-2">
    <Activity className="w-5 h-5 text-indigo-500" /> Success Rate Trend (Last 30 Days)
  </h3>
  <div className="h-72 w-full">
    {metricsLoading ? (
      <div className="h-full flex items-center justify-center bg-slate-50 dark:bg-slate-800/50 rounded-2xl animate-pulse">
        <LoadingSpinner />
      </div>
    ) : (
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={metrics}>
          <defs>
            <linearGradient id="colorRate" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#6366f1" stopOpacity={0.1}/>
              <stop offset="95%" stopColor="#6366f1" stopOpacity={0}/>
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
          <XAxis 
            dataKey="date" 
            tickFormatter={(str) => format(new Date(str), 'MMM d')}
            tick={{fontSize: 10, fontWeight: 700}}
            stroke="#94a3b8"
          />
          <YAxis 
            domain={[0, 100]} 
            tick={{fontSize: 10, fontWeight: 700}}
            stroke="#94a3b8"
            tickFormatter={(val) => `${val}%`}
          />
          <Tooltip 
  labelFormatter={(label) => format(new Date(label), 'MMM d, yyyy')}
  contentStyle={{ 
    borderRadius: '12px', 
    border: 'none', 
    boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)',
    backgroundColor: '#ffffff',
    fontSize: '12px',
    fontWeight: 700
  }}
/>
          <Area 
            type="monotone" 
            dataKey="success_rate" 
            stroke="#6366f1" 
            strokeWidth={3}
            fillOpacity={1} 
            fill="url(#colorRate)" 
          />
        </AreaChart>
      </ResponsiveContainer>
    )}
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
  Created {formatDistanceToNow(new Date(pr.created_at), { addSuffix: true })} by {pr.author?.name || 'testuser'}
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

      {activeTab === 'settings' && (
  <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 animate-in fade-in duration-300">
    <div className="max-w-2xl">
      <h3 className="text-xl font-black text-slate-900 dark:text-white mb-2">Repository Configuration</h3>
      <p className="text-sm text-slate-500 mb-8 font-medium">Manage sync settings and webhook integration for this repository.</p>
      
      <div className="space-y-6">
        <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
          <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Webhook URL</p>
          <code className="text-xs font-mono text-indigo-600 dark:text-indigo-400">http://localhost:8080/api/webhooks/github</code>
        </div>
        
        <div className="flex items-center justify-between p-4 border border-slate-100 dark:border-slate-800 rounded-2xl">
          <div>
            <p className="text-sm font-bold text-slate-900 dark:text-white">Auto-Sync</p>
            <p className="text-xs text-slate-500">Automatically fetch new PRs every hour.</p>
          </div>
          <div className="h-6 w-11 bg-indigo-600 rounded-full flex items-center px-1">
            <div className="h-4 w-4 bg-white rounded-full ml-auto shadow-sm" />
          </div>
        </div>
      </div>
    </div>
  </div>
)}

    </div>
  );
}
