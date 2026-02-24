import { useParams } from 'react-router-dom';
import { useRepository } from '@hooks/useApi';
import { LoadingSpinner } from '@components/LoadingSpinner';

export default function RepositoryDetail() {
  const { id } = useParams<{ id: string }>();
  const { data: repository, isLoading } = useRepository(Number(id));
  
  if (isLoading) return <LoadingSpinner />;
  if (!repository) return <div>Repository not found</div>;
  
  return (
    <div>
      <h1 className="text-3xl font-bold mb-6">{repository.full_name}</h1>
      <div className="bg-white p-6 rounded-lg shadow">
        <p>{repository.description}</p>
      </div>
    </div>
  );
}