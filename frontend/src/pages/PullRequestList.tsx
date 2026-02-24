import { useParams } from 'react-router-dom';
import { usePullRequests } from '@hooks/useApi';
import { LoadingSpinner } from '@components/LoadingSpinner';

export default function PullRequestList() {
  const { id } = useParams<{ id: string }>();
  const { data: response, isLoading } = usePullRequests(Number(id));
  
  if (isLoading) return <LoadingSpinner />;
  
  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">Pull Requests</h1>
      <div className="space-y-4">
        {response?.data.map((pr) => (
          <div key={pr.id} className="bg-white p-4 rounded-lg shadow">
            <h3 className="font-semibold">#{pr.number} {pr.title}</h3>
            <p className="text-sm text-gray-600 mt-1">{pr.state}</p>
          </div>
        ))}
      </div>
    </div>
  );
}