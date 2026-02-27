import { useRepositories, useFlakyTests } from '@/hooks/useApi';
import { useState, useEffect } from 'react';
import { 
  AlertTriangle, 
  Filter, 
  BarChart3, 
  Clock, 
  History,
  ShieldAlert,
  CheckCircle2
} from 'lucide-react';
import LoadingSpinner from '@/components/LoadingSpinner';

// ============================================================================
// Internal UI Components
// ============================================================================

function FlakyTestCard({ test }: { test: any }) {
  // Use the actual fields from TestResult model
  const failureRate = test.failure_rate || 0;
  const isHighImpact = failureRate > 15;

  return (
    <div className="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-amber-300 dark:hover:border-amber-500/50 transition-all duration-300 shadow-sm hover:shadow-md">
      <div className="flex items-start justify-between">
        <div className="flex gap-4">
          <div className={`p-3 rounded-xl border ${
            isHighImpact 
              ? 'bg-rose-50 text-rose-600 border-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20' 
              : 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'
          }`}>
            <ShieldAlert className="w-6 h-6" />
          </div>
          <div>
            <h3 className="text-sm font-black text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">
              {test.test_name || 'Unknown Test Case'}
            </h3>
            <p className="text-xs font-mono text-slate-500 mt-1 truncate max-w-md">
              {test.test_file || 'src/tests/ExampleTest.php'}
            </p>
          </div>
        </div>
        <div className="text-right">
          <span className={`text-xs font-black uppercase tracking-widest px-2 py-1 rounded-md ${
            isHighImpact ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'
          }`}>
            {isHighImpact ? 'High Impact' : 'Moderate'}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4 mt-6 pt-5 border-t border-slate-50 dark:border-slate-800/50">
        <div>
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Failure Rate</p>
          <p className="text-sm font-black text-slate-900 dark:text-white">{Math.round(failureRate)}%</p>
        </div>
        <div>
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Total Flakes</p>
          <p className="text-sm font-black text-slate-900 dark:text-white">{test.retry_count || 0}</p>
        </div>
        <div>
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Last Seen</p>
          <p className="text-sm font-black text-slate-900 dark:text-white">
            {test.executed_at ? new Date(test.executed_at).toLocaleString() : 'Unknown'}
          </p>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Main Page
// ============================================================================

const repoList = (repos: unknown): { id: number; name: string }[] =>
  Array.isArray(repos) ? repos : (repos as { data?: { id: number; name: string }[] })?.data ?? [];

export default function FlakyTests() {
  const [selectedRepo, setSelectedRepo] = useState<number | null>(null);
  const { data: repositories } = useRepositories();
  const repos = repoList(repositories);
  
  // Auto-select first repository on mount if none is selected
  useEffect(() => {
    if (selectedRepo === null && repos.length > 0 && repos[0]) {
      setSelectedRepo(repos[0].id);
    }
  }, [selectedRepo, repos]);
  
  const repoId = selectedRepo;

  const { data: flakyTests, isLoading } = useFlakyTests(repoId);

  return (
    <div className="max-w-7xl mx-auto pb-12 animate-in fade-in duration-500">
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div>
          <div className="flex items-center gap-2 text-amber-600 mb-2">
            <AlertTriangle className="w-5 h-5" />
            <span className="text-xs font-black uppercase tracking-[0.2em]">Stability Intelligence</span>
          </div>
          <h1 className="text-4xl font-black text-slate-900 dark:text-white tracking-tight">
            Flaky Tests
          </h1>
          <p className="text-slate-500 dark:text-slate-400 mt-2 font-medium">
            Identify and prioritize tests that fail inconsistently in your pipeline.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <div className="relative">
            <Filter className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <select
              value={repoId ?? ''}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedRepo(val === '' ? null : Number(val));
              }}
              className="pl-10 pr-8 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 outline-none appearance-none cursor-pointer"
            >
              <option value="">Select Repository</option>
              {repos.map((repo) => (
                <option key={repo.id} value={repo.id}>{repo.name}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Analytics Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div className="bg-indigo-600 rounded-2xl p-6 text-white flex items-center justify-between">
          <div>
            <p className="text-xs font-bold uppercase opacity-80 tracking-widest">Total Identified</p>
            <p className="text-3xl font-black mt-1">{flakyTests?.length || 0}</p>
          </div>
          <BarChart3 className="w-10 h-10 opacity-20" />
        </div>
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex items-center justify-between">
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">CI Time Wasted</p>
            <p className="text-3xl font-black text-slate-900 dark:text-white mt-1">14.2h</p>
          </div>
          <Clock className="w-10 h-10 text-slate-100 dark:text-slate-800" />
        </div>
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex items-center justify-between">
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Resolution Rate</p>
            <p className="text-3xl font-black text-slate-900 dark:text-white mt-1">64%</p>
          </div>
          <History className="w-10 h-10 text-slate-100 dark:text-slate-800" />
        </div>
      </div>

      {/* Main Content */}
      <div className="space-y-4">
        {isLoading ? (
          <div className="h-64 flex items-center justify-center"><LoadingSpinner /></div>
        ) : flakyTests?.length ? (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {flakyTests.map((test: any, index: number) => (
              <FlakyTestCard key={index} test={test} />
            ))}
          </div>
        ) : (
          <div className="bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-20 text-center">
            <div className="bg-emerald-50 dark:bg-emerald-500/10 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <CheckCircle2 className="w-8 h-8 text-emerald-500" />
            </div>
            <h3 className="text-lg font-bold text-slate-900 dark:text-white">Your pipeline is stable</h3>
            <p className="text-slate-500 max-w-sm mx-auto mt-2 font-medium">No flaky tests have been detected in the last 30 days. Great work on maintaining test quality!</p>
          </div>
        )}
      </div>
    </div>
  );
}
