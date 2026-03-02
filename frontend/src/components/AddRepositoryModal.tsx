import { useState } from 'react';
import { X, GitBranch, Search, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react';
import { useCreateRepository } from '@/hooks/useApi';

interface AddRepositoryModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function AddRepositoryModal({ isOpen, onClose }: AddRepositoryModalProps) {
  const { mutate: createRepository, isPending } = useCreateRepository();
  
  // State for the "Smart" flow
  const [fullName, setFullName] = useState('');
  const [isFetching, setIsFetching] = useState(false);
  const [previewData, setPreviewData] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  // Phase 1: Fetch metadata from GitHub (Senior Move)
  const handleFetchMetadata = async () => {
    if (!fullName.includes('/')) {
      setError('Please use the format: owner/repository');
      return;
    }

    setIsFetching(true);
    setError(null);

    try {
      const response = await fetch(`https://api.github.com{fullName}`);
      if (!response.ok) throw new Error('Repository not found on GitHub');
      
      const data = await response.json();
      setPreviewData(data);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setIsFetching(false);
    }
  };

  // Phase 2: Submit the verified data
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!previewData) return;

    const submitData = {
      external_id: previewData.id,
      provider: 'github',
      full_name: previewData.full_name,
      name: previewData.name,
      owner: previewData.owner.login,
      default_branch: previewData.default_branch,
      description: previewData.description,
      language: previewData.language,
      html_url: previewData.html_url,
      clone_url: previewData.clone_url,
      stars_count: previewData.stargazers_count,
      forks_count: previewData.forks_count,
      open_issues_count: previewData.open_issues_count,
      is_private: previewData.private,
      ci_enabled: true,
      is_active: true,
    };

    createRepository(submitData, {
      onSuccess: () => {
        setPreviewData(null);
        setFullName('');
        onClose();
      },
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-lg shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden animate-in zoom-in-95 duration-200">
        
        {/* Header */}
        <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-indigo-600 rounded-2xl shadow-indigo-200 shadow-lg">
              <GitBranch className="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 className="text-xl font-black text-slate-900 dark:text-white">Connect Repository</h2>
              <p className="text-xs font-bold text-slate-500 uppercase tracking-wider">GitHub Integration</p>
            </div>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-white dark:hover:bg-slate-800 rounded-xl transition-all shadow-sm">
            <X className="w-5 h-5 text-slate-400" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Search Input */}
          <div className="space-y-2">
            <label className="text-sm font-black text-slate-700 dark:text-slate-300 ml-1">Repository Path</label>
            <div className="relative">
              <input
                type="text"
                placeholder="e.g. facebook/react"
                value={fullName}
                onChange={(e) => setFullName(e.target.value)}
                className="w-full pl-11 pr-32 py-4 bg-slate-100 dark:bg-slate-800 border-none rounded-2xl text-slate-900 dark:text-white font-bold placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all"
              />
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
              <button
                type="button"
                onClick={handleFetchMetadata}
                disabled={isFetching || !fullName}
                className="absolute right-2 top-1/2 -translate-y-1/2 px-4 py-2 bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 rounded-xl text-xs font-black uppercase tracking-tight hover:bg-indigo-50 transition-all disabled:opacity-50"
              >
                {isFetching ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Verify'}
              </button>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="p-4 bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 rounded-2xl flex items-center gap-3 text-rose-600 dark:text-rose-400 text-sm font-bold">
              <AlertCircle className="w-5 h-5" /> {error}
            </div>
          )}

          {/* Preview Card (Shows only after verification) */}
          {previewData && (
            <div className="p-5 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 rounded-2xl animate-in fade-in slide-in-from-top-2">
              <div className="flex items-start justify-between mb-3">
                <div>
                  <h4 className="font-black text-slate-900 dark:text-white">{previewData.name}</h4>
                  <p className="text-xs text-slate-500 font-bold italic">{previewData.description || 'No description provided.'}</p>
                </div>
                <CheckCircle2 className="w-6 h-6 text-emerald-500" />
              </div>
              <div className="flex gap-4">
                <div className="text-[10px] font-black uppercase text-indigo-600 bg-white dark:bg-slate-800 px-2 py-1 rounded-md shadow-sm">
                  ⭐ {previewData.stargazers_count}
                </div>
                <div className="text-[10px] font-black uppercase text-indigo-600 bg-white dark:bg-slate-800 px-2 py-1 rounded-md shadow-sm">
                  {previewData.language || 'Mixed'}
                </div>
              </div>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-4 text-slate-500 dark:text-slate-400 font-black text-sm hover:bg-slate-50 dark:hover:bg-slate-800 rounded-2xl transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!previewData || isPending}
              className="flex-[2] py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-black text-sm rounded-2xl shadow-xl hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
            >
              {isPending ? 'Connecting...' : 'Confirm & Connect'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
