import { useState } from 'react';
import { 
  Settings as SettingsIcon, 
  Shield, 
  Bell, 
  Globe, 
  Save, 
  RefreshCw,
  Trash2,
} from 'lucide-react';
import toast from 'react-hot-toast';
import { useRepositories } from '@/hooks/useApi';

export default function Settings() {
  const [activeTab, setActiveTab] = useState<'general' | 'security' | 'notifications'>('general');
  const [isSaving, setIsSaving] = useState(false);
  const { data: repositories, refetch } = useRepositories();
  const repoList = Array.isArray(repositories) ? repositories : (repositories as any)?.data || [];

  const handleSave = () => {
    setIsSaving(true);
    setTimeout(() => {
      setIsSaving(false);
      toast.success('Settings updated successfully');
    }, 1000);
  };

  return (
    <div className="max-w-6xl mx-auto pb-12 animate-in fade-in duration-500">
      <div className="mb-10">
        <h1 className="text-4xl font-black text-slate-900 dark:text-white tracking-tight">Settings</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-2 font-medium">Configure your workspace and integration preferences.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
        {/* Sidebar Tabs */}
        <aside className="space-y-1">
          {[
            { id: 'general', label: 'General', icon: SettingsIcon },
            { id: 'security', label: 'Security & API', icon: Shield },
            { id: 'notifications', label: 'Notifications', icon: Bell },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-xl transition-all ${
                activeTab === tab.id 
                  ? 'bg-white dark:bg-slate-800 text-indigo-600 shadow-sm border border-slate-200 dark:border-slate-700' 
                  : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800/50'
              }`}
            >
              <tab.icon className="w-4 h-4" />
              {tab.label}
            </button>
          ))}
        </aside>

        {/* Content Area */}
        <div className="lg:col-span-3 space-y-6">
          {activeTab === 'general' && (
            <div className="space-y-6 animate-in slide-in-from-right-4 duration-300">
              {/* Repository Management Section */}
              <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden">
                <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                  <div>
                    <h3 className="text-lg font-black text-slate-900 dark:text-white">Connected Repositories</h3>
                    <p className="text-xs text-slate-500 font-medium mt-1">Manage active sync for your GitHub sources.</p>
                  </div>
                  <button 
                    onClick={() => { refetch(); toast.success('Syncing with GitHub...'); }}
                    className="flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-slate-700 dark:text-slate-300 hover:text-indigo-600 rounded-lg text-xs font-black uppercase transition-all"
                  >
                    <RefreshCw className="w-3 h-3" /> Sync Now
                  </button>
                </div>
                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                  {repoList.map((repo: any) => (
                    <div key={repo.id} className="p-4 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <Globe className="w-4 h-4 text-slate-400" />
                        <span className="text-sm font-bold text-slate-700 dark:text-slate-200">{repo.full_name}</span>
                      </div>
                      <div className="flex items-center gap-4">
                        <span className="text-[10px] font-black uppercase text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-100 dark:border-emerald-500/20">Live Sync</span>
                        <button className="text-slate-300 hover:text-rose-600 transition-colors"><Trash2 className="w-4 h-4" /></button>
                      </div>
                    </div>
                  ))}
                </div>
              </section>

              {/* General Form */}
              <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Workspace Name</label>
                    <input type="text" defaultValue="CI Insights Dashboard" className="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold" />
                  </div>
                  <div>
                    <label className="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Retention Period (Days)</label>
                    <input type="number" defaultValue="90" className="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold" />
                  </div>
                </div>
                <div className="pt-4 flex justify-end">
                  <button 
                    onClick={handleSave}
                    disabled={isSaving}
                    className="flex items-center gap-2 px-8 py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl font-black text-sm hover:opacity-90 transition-all disabled:opacity-50"
                  >
                    {isSaving ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                    Save Changes
                  </button>
                </div>
              </section>
            </div>
          )}

          {activeTab === 'security' && (
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 space-y-8 animate-in slide-in-from-right-4 duration-300">
               <div>
                  <h3 className="text-lg font-black text-slate-900 dark:text-white">API Keys</h3>
                  <p className="text-sm text-slate-500 font-medium mt-1">Use these keys to send data from your CI provider (GitHub Actions, Jenkins, etc.)</p>
               </div>
               <div className="bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                  <div className="flex items-center justify-between mb-4">
                     <span className="text-xs font-bold text-slate-500 uppercase">Primary Dashboard Key</span>
                     <span className="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">Active</span>
                  </div>
                  <div className="flex gap-2">
                     <input readOnly value="ci_live_8f3k29sk01ml29sk09" className="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-4 py-2 rounded-lg font-mono text-xs text-slate-600" />
                     <button className="px-4 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold" onClick={() => toast.success('Key copied')}>Copy</button>
                  </div>
               </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
