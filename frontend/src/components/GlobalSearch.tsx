import { useState, useEffect } from 'react';
import { api } from '@/api/client';
import type { Repository, PullRequest } from '@/types';
import { useNavigate } from 'react-router-dom';
import { X, Search as SearchIcon } from 'lucide-react';

interface SearchResponse {
  repositories: Repository[];
  pull_requests: PullRequest[];
}

interface Props {
  open: boolean;
  onClose: () => void;
}

export default function GlobalSearch({ open, onClose }: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResponse>({ repositories: [], pull_requests: [] });
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // clear state when closed
  useEffect(() => {
    if (!open) {
      setQuery('');
      setResults({ repositories: [], pull_requests: [] });
      setLoading(false);
    }
  }, [open]);

  // debounce search
  useEffect(() => {
    if (!open) return;
    const handle = setTimeout(() => {
      const term = query.trim();
      if (term.length === 0) {
        setResults({ repositories: [], pull_requests: [] });
        setLoading(false);
        return;
      }
      setLoading(true);
      api.search(term)
        .then((data) => setResults(data))
        .catch(() => {})
        .finally(() => setLoading(false));
    }, 300);
    return () => clearTimeout(handle);
  }, [query, open]);

  const selectRepo = (repo: Repository) => {
    onClose();
    navigate(`/repositories/${repo.id}`);
  };

  const selectPR = (pr: PullRequest) => {
    onClose();
    navigate(`/pull-requests/${pr.id}`);
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/30">
      <div className="mt-20 w-full max-w-xl bg-white dark:bg-slate-800 rounded-lg shadow-lg p-4">
        <div className="flex items-center gap-2">
          <SearchIcon className="w-5 h-5 text-slate-500" />
          <input
            autoFocus
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="flex-1 p-2 bg-slate-100 dark:bg-slate-700 rounded-md outline-none"
            placeholder="Search repositories or pull requests..."
          />
          <button onClick={onClose} className="p-1">
            <X className="w-4 h-4 text-slate-500" />
          </button>
        </div>
        <div className="mt-4 max-h-60 overflow-y-auto">
          {loading && <p className="text-sm text-slate-500">Searching…</p>}
          {!loading && results.repositories.length === 0 && results.pull_requests.length === 0 && query && (
            <p className="text-sm text-slate-500">No results found.</p>
          )}

          {results.repositories.map((repo) => (
            <div
              key={repo.id}
              onClick={() => selectRepo(repo)}
              className="cursor-pointer px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              <strong className="block">{repo.full_name}</strong>
              <span className="text-xs text-slate-500">Repository</span>
            </div>
          ))}

          {results.pull_requests.map((pr) => (
            <div
              key={pr.id}
              onClick={() => selectPR(pr)}
              className="cursor-pointer px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              <strong className="block">#{pr.number} {pr.title}</strong>
              <span className="text-xs text-slate-500">Pull Request</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
