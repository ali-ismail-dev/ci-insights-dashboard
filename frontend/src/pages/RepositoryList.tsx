import { useRepositories } from '@hooks/useApi';
import { Link } from 'react-router-dom';
import { LoadingSpinner } from '@components/LoadingSpinner';

export default function RepositoryList() {
  const { data: repositories, isLoading } = useRepositories();
  
  if (isLoading) return <LoadingSpinner />;
  
  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">Repositories</h1>
      <div className="grid gap-4">
        {repositories?.map((repo) => (
          <Link
            key={repo.id}
            to={`/repositories/${repo.id}`}
            className="block p-6 bg-white rounded-lg shadow hover:shadow-md transition-shadow"
          >
            <h3 className="text-lg font-semibold">{repo.full_name}</h3>
            <p className="text-gray-600 mt-1">{repo.description}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}