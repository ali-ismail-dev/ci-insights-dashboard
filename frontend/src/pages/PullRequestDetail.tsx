import { useParams } from 'react-router-dom';
import { usePullRequest } from '@hooks/useApi';
import { LoadingSpinner } from '@components/LoadingSpinner';

export default function PullRequestDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: pr, isLoading } = usePullRequest(Number(id));
  
  if (isLoading) return <LoadingSpinner />;
  if (!pr) return <div>Pull request not found</div>;
  
  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">PR #{pr.number}: {pr.title}</h1>
      <div className="bg-white p-6 rounded-lg shadow">
        <p>{pr.description}</p>
      </div>
    </div>
  );
}