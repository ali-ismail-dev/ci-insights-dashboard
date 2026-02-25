import { Suspense } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { 
  LayoutDashboard, 
  GitBranch, 
  AlertTriangle, 
  Settings,
  LogOut,
  Bell,
  Search,
  User as UserIcon,
  ChevronRight,
  Activity
} from 'lucide-react';
import { useAuth } from '@/context/AuthContext';
import LoadingSpinner from '@/components/LoadingSpinner';

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Repositories', href: '/repositories', icon: GitBranch },
  { name: 'Flaky Tests', href: '/flaky-tests', icon: AlertTriangle },
];

const secondaryNavigation = [
  { name: 'Settings', href: '/settings', icon: Settings },
];

export function MainLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

 const handleLogout = async () => {
    try {
      // 1. Force stop any background polling/queries if using React Query
      // 2. Call the logout API
      await logout();
    } catch (err) {
      console.warn("Logout cleanup", err);
    } finally {
      // 3. Always redirect, even if the API call fails (force client-side logout)
      navigate('/login', { replace: true });
    }
  };

  const isActive = (href: string) => {
    if (href === '/') return location.pathname === '/';
    return location.pathname.startsWith(href);
  };

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-950 overflow-hidden">
      {/* Sidebar */}
      <aside className="hidden md:flex md:flex-col md:w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shadow-sm">
        {/* Brand Logo */}
        <div className="flex items-center h-16 px-6 border-b border-slate-100 dark:border-slate-800/50">
          <div className="flex items-center gap-3">
            <div className="bg-indigo-600 p-1.5 rounded-lg shadow-indigo-200 dark:shadow-none shadow-lg">
              <Activity className="h-5 w-5 text-white" />
            </div>
            <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-600 dark:from-white dark:to-slate-400">
              CI Insights
            </span>
          </div>
        </div>

        {/* Sidebar Nav */}
        <div className="flex-1 flex flex-col justify-between overflow-y-auto py-6 px-4">
          <nav className="space-y-1">
            <p className="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Main Menu</p>
            {navigation.map((item) => {
              const active = isActive(item.href);
              return (
                <Link
                  key={item.name}
                  to={item.href}
                  className={`flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200 group ${
                    active 
                      ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' 
                      : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50'
                  }`}
                >
                  <item.icon className={`w-5 h-5 mr-3 transition-colors ${active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600'}`} />
                  {item.name}
                </Link>
              );
            })}
          </nav>

          <nav className="space-y-1 border-t border-slate-100 dark:border-slate-800 pt-6">
             <p className="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">System</p>
            {secondaryNavigation.map((item) => (
              <Link
                key={item.name}
                to={item.href}
                className="flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50 transition-all"
              >
                <item.icon className="w-5 h-5 mr-3 text-slate-400" />
                {item.name}
              </Link>
            ))}
            
            <button
              onClick={handleLogout}
              className="w-full flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 transition-all mt-4"
            >
              <LogOut className="w-5 h-5 mr-3" />
              Sign Out
            </button>
          </nav>
        </div>
      </aside>

      {/* Main Container */}
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        {/* Top Header */}
        <header className="h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-8 sticky top-0 z-10">
          <div className="flex items-center gap-2 text-sm text-slate-500 font-medium">
             <span className="hover:text-slate-900 dark:hover:text-white cursor-pointer transition-colors">App</span>
             <ChevronRight className="h-4 w-4" />
             <span className="text-slate-900 dark:text-white capitalize">{location.pathname.split('/')[1] || 'Dashboard'}</span>
          </div>

          <div className="flex items-center gap-4">
            <button className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors">
              <Search className="h-5 w-5" />
            </button>
            <button className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white relative transition-colors">
              <Bell className="h-5 w-5" />
              <span className="absolute top-1.5 right-1.5 h-2 w-2 bg-rose-500 rounded-full border-2 border-white dark:border-slate-900"></span>
            </button>
            
            <div className="h-8 w-px bg-slate-200 dark:border-slate-800 mx-2"></div>
            
            <div className="flex items-center gap-3 pl-2">
              <div className="flex flex-col items-end">
                <span className="text-sm font-bold text-slate-900 dark:text-white">{user?.name || 'User'}</span>
                <span className="text-[10px] text-slate-500 uppercase tracking-tight">{user?.role || 'Admin'}</span>
              </div>
              <div className="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center border border-slate-200 dark:border-slate-700">
                <UserIcon className="h-5 w-5 text-slate-600 dark:text-slate-400" />
              </div>
            </div>
          </div>
        </header>

        {/* Scrollable Page Content */}
        <main className="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 p-8">
          <div className="max-w-7xl mx-auto">
             <Suspense fallback={<div className="h-[60vh] flex items-center justify-center"><LoadingSpinner /></div>}>
                <Outlet />
             </Suspense>
          </div>
        </main>
      </div>
    </div>
  );
}
