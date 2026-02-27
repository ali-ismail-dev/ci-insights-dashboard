import { useRepositories } from '@/hooks/useApi';
import { GitBranch, Star, Lock, Globe, ChevronRight, Plus, RefreshCw } from 'lucide-react';
import { Link } from 'react-router-dom';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';

export default function RepositoryList() {
  const { data: repositories, isLoading, refetch } = useRepositories();

  // Handle both Array and Paginated Object responses
  const repoList = Array.isArray(repositories) ? repositories : (repositories as any)?.data || [];

  if (isLoading) return <div className="h-96 flex items-center justify-center"><LoadingSpinner /></div>;

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-black text-slate-900 dark:text-white">Repositories</h1>
          <p className="text-slate-500 dark:text-slate-400 font-medium mt-1">
            Managing {repoList.length} active sources.
          </p>
        </div>
        <div className="flex gap-3">
          <button
            onClick={() => {
              refetch();
              toast.success('Syncing with GitHub...');
            }}
            className="p-2.5 text-slate-500 hover:text-indigo-600 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl transition-all"
          >
            <RefreshCw className="w-5 h-5" />
          </button>
          <button 
  onClick={() => toast('GitHub Repository Import is coming soon!', { style: { background: '#3b82f6', color: 'white' } })}
  className="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-200 dark:shadow-none transition-all active:scale-95"
>
  <Plus className="w-4 h-4" /> Add Repository
</button>
        </div>
      </div>

      {!repoList.length ? (
        <div className="bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-20 text-center">
          <div className="bg-slate-100 dark:bg-slate-800 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <GitBranch className="w-8 h-8 text-slate-400" />
          </div>
          <h3 className="text-lg font-bold text-slate-900 dark:text-white">No repositories connected</h3>
          <p className="text-slate-500 max-w-sm mx-auto mt-2">Connect your GitHub organization to start monitoring pull requests and CI health.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {repoList.map((repo: any) => (
            <Link
              key={repo.id}
              to={`/repositories/${repo.id}`}
              className="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl flex items-center justify-between hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none transition-all"
            >
              <div className="flex items-center gap-5">
                <div className="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-xl flex items-center justify-center border border-slate-100 dark:border-slate-700 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-500/10 transition-colors">
                  {repo.is_private ? <Lock className="w-5 h-5 text-slate-400" /> : <Globe className="w-5 h-5 text-slate-400" />}
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">
                    {repo.full_name}
                  </h3>
                  <div className="flex items-center gap-3 mt-1">
                    <span className="text-xs font-medium text-slate-400 flex items-center gap-1">
                      <Star className="w-3 h-3" /> {repo.stars_count || 0}
                    </span>
                    <span className="text-xs font-medium text-slate-400">
                      {repo.language || 'Documentation'}
                    </span>
                    {repo.is_active && (
                      <span className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-100 dark:border-emerald-500/20">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                        Active
                      </span>
                    )}
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-8">
                <div className="hidden sm:flex flex-col items-end">
                  <span className="text-xs font-bold text-slate-400 uppercase tracking-tighter">Default Branch</span>
                  <span className="text-sm font-mono text-slate-700 dark:text-slate-300 font-bold">{repo.default_branch || 'main'}</span>
                </div>
                <ChevronRight className="w-5 h-5 text-slate-300 group-hover:text-indigo-500 transform group-hover:translate-x-1 transition-all" />
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
