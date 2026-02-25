import { useParams, Link } from 'react-router-dom';
import { usePullRequest, useTestRuns } from '../hooks/useApi';
import LoadingSpinner from '../components/LoadingSpinner';
import { 
  Clock, 
  CheckCircle2, 
  XCircle, 
  ChevronLeft,
  ExternalLink,
  GitBranch,
  User as UserIcon,
  Calendar,
  Zap,
  Activity, // Added missing import
  ArrowRight // Added missing import
} from 'lucide-react';
import { formatDistanceToNow, format } from 'date-fns';

export default function PullRequestDetail() {
  const { id } = useParams<{ id: string }>();
  const prId = Number(id);

  const { data: pr, isLoading: prLoading } = usePullRequest(prId);
  const { data: testRuns, isLoading: testsLoading } = useTestRuns(pr?.repository_id || 0, prId);

  if (prLoading || testsLoading) return <div className="h-96 flex items-center justify-center"><LoadingSpinner /></div>;
  
  // Professional empty state
  if (!pr) return (
    <div className="flex flex-col items-center justify-center py-20">
      <div className="bg-slate-100 p-4 rounded-full mb-4">
        <XCircle className="w-12 h-12 text-slate-400" />
      </div>
      <h2 className="text-xl font-black text-slate-900">PR Not Found</h2>
      <p className="text-slate-500">The requested Pull Request (ID: {prId}) does not exist in our records.</p>
      <Link to="/" className="mt-6 text-indigo-600 font-bold hover:underline">Return to Dashboard</Link>
    </div>
  );

  return (
    <div className="max-w-5xl mx-auto pb-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="mb-8">
        <Link to={`/repositories/${pr.repository_id}/pull-requests`} className="flex items-center text-sm font-bold text-indigo-600 hover:text-indigo-500 transition-colors mb-4">
          <ChevronLeft className="w-4 h-4 mr-1" /> All Pull Requests
        </Link>
        <div className="flex flex-col md:flex-row md:items-start justify-between gap-6">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-3 mb-2">
              <span className={`px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-widest border ${
                pr.state === 'merged' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'
              }`}>
                {pr.state}
              </span>
              <span className="text-slate-400 font-mono text-sm">#{pr.number}</span>
            </div>
            <h1 className="text-3xl font-black text-slate-900 dark:text-white leading-tight">{pr.title}</h1>
            <div className="flex flex-wrap items-center gap-4 mt-4 text-sm text-slate-500 font-medium">
              <div className="flex items-center gap-1.5"><UserIcon className="w-4 h-4" /> {pr.author?.username || 'author'}</div>
              <div className="flex items-center gap-1.5"><Calendar className="w-4 h-4" /> {pr.created_at ? format(new Date(pr.created_at), 'MMM d, yyyy') : 'N/A'}</div>
              <div className="flex items-center gap-1.5"><GitBranch className="w-4 h-4" /> {pr.head_branch} <ArrowRight className="w-3 h-3 mx-1" /> {pr.base_branch}</div>
            </div>
          </div>
          <a href={pr.html_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 px-6 py-3 bg-slate-900 text-white dark:bg-white dark:text-slate-900 rounded-2xl font-bold text-sm hover:opacity-90 shadow-lg">
            <ExternalLink className="w-4 h-4" /> GitHub
          </a>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 space-y-8">
          <section>
            <h3 className="text-lg font-black text-slate-900 dark:text-white mb-4 flex items-center gap-2">
              <Zap className="w-5 h-5 text-amber-500" /> CI Run History
            </h3>
            <div className="space-y-3">
              {testRuns?.length ? testRuns.map((run: any) => (
                <div key={run.id} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center justify-between">
                  <div className="flex items-center gap-4">
                    {run.conclusion === 'success' ? <CheckCircle2 className="w-6 h-6 text-emerald-500" /> : <XCircle className="w-6 h-6 text-rose-500" />}
                    <div>
                      <p className="font-bold text-slate-900 dark:text-white">{run.name || 'Build'}</p>
                      <p className="text-xs text-slate-500 font-medium">{run.completed_at ? formatDistanceToNow(new Date(run.completed_at), { addSuffix: true }) : ''}</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <span className="text-sm font-black text-slate-900 dark:text-white">{run.duration_seconds}s</span>
                  </div>
                </div>
              )) : (
                <div className="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-10 text-center text-slate-400 font-bold italic border-2 border-dashed border-slate-100 dark:border-slate-800">
                  No CI runs detected.
                </div>
              )}
            </div>
          </section>
        </div>

        <div className="space-y-6">
          <div className="bg-indigo-600 rounded-3xl p-6 text-white shadow-xl">
            <h4 className="text-sm font-black uppercase tracking-widest opacity-80">Insights</h4>
            <div className="mt-6 space-y-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2"><Clock className="w-5 h-5 opacity-70" /><span className="text-sm font-bold">Cycle Time</span></div>
                {/* Fixed TS18047: Added null check for cycle_time */}
                <span className="text-lg font-black">{pr.cycle_time ? Math.round(Number(pr.cycle_time) / 3600) : '--'}h</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2"><Activity className="w-5 h-5 opacity-70" /><span className="text-sm font-bold">Changed Files</span></div>
                <span className="text-lg font-black">{pr.changed_files}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
