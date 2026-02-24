import { lazy, Suspense } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';

// Guards & Providers
 import { AuthProvider } from '@/context/AuthContext'; // I will provide this next
// import ProtectedRoute from '@/components/auth/ProtectedRoute'; // I will provide this next

// Layouts
import { MainLayout } from '@/layouts/MainLayout';

// Pages - Auth (Static import for core entry points)
import Login from '@/pages/auth/Login';
import Register from '@/pages/auth/Register';

// Pages - App (Lazy-loaded for senior-level performance/code splitting)
const Dashboard = lazy(() => import('@pages/Dashboard'));
const RepositoryList = lazy(() => import('@pages/RepositoryList'));
const RepositoryDetail = lazy(() => import('@pages/RepositoryDetail'));
const PullRequestList = lazy(() => import('@pages/PullRequestList'));
const PullRequestDetail = lazy(() => import('@pages/PullRequestDetail'));
const FlakyTests = lazy(() => import('@pages/FlakyTests'));
const Settings = lazy(() => import('@pages/Settings'));

// UI Components
const PageLoader = () => (
  <div className="flex items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900">
    <div className="flex flex-col items-center gap-4">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      <p className="text-sm font-medium text-slate-500 animate-pulse">Loading CI Data...</p>
    </div>
  </div>
);

// React Query Config (Senior Defaults)
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 5 * 60 * 1000,
    },
  },
});

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <div className="min-h-screen bg-slate-50 dark:bg-slate-900">
            <Routes>
              {/* Public Auth Routes */}
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />

              {/* Private Application Routes */}
              <Route path="/" element={
                /* <ProtectedRoute> */ 
                  <MainLayout /> 
                /* </ProtectedRoute> */
              }>
                <Route index element={
                  <Suspense fallback={<PageLoader />}>
                    <Dashboard />
                  </Suspense>
                } />
                
                <Route path="repositories" element={
                  <Suspense fallback={<PageLoader />}>
                    <RepositoryList />
                  </Suspense>
                } />
                
                <Route path="repositories/:id" element={
                  <Suspense fallback={<PageLoader />}>
                    <RepositoryDetail />
                  </Suspense>
                } />
                
                <Route path="repositories/:id/pull-requests" element={
                  <Suspense fallback={<PageLoader />}>
                    <PullRequestList />
                  </Suspense>
                } />
                
                <Route path="pull-requests/:id" element={
                  <Suspense fallback={<PageLoader />}>
                    <PullRequestDetail />
                  </Suspense>
                } />
                
                <Route path="flaky-tests" element={
                  <Suspense fallback={<PageLoader />}>
                    <FlakyTests />
                  </Suspense>
                } />
                
                <Route path="settings" element={
                  <Suspense fallback={<PageLoader />}>
                    <Settings />
                  </Suspense>
                } />
              </Route>

              {/* Catch-all redirect to Dashboard (which triggers Guard) */}
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
            
            <Toaster position="top-right" />
          </div>
        </AuthProvider>
      </BrowserRouter>
      
      {import.meta.env.DEV && <ReactQueryDevtools initialIsOpen={false} />}
    </QueryClientProvider>
  );
}

export default App;
