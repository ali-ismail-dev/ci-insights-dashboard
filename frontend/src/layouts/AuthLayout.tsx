import React from 'react';
import { Activity } from 'lucide-react';

interface AuthLayoutProps {
  children: React.ReactNode;
  title: string;
  subtitle: string;
}

export default function AuthLayout({ children, title, subtitle }: AuthLayoutProps) {
  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 dark:bg-slate-900">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="flex justify-center items-center gap-2 mb-6">
          <div className="bg-indigo-600 p-2 rounded-lg">
            <Activity className="h-8 w-8 text-white" />
          </div>
          <span className="text-2xl font-bold text-slate-900 dark:text-white">CI Insights</span>
        </div>
        <h2 className="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
          {title}
        </h2>
        <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
          {subtitle}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white dark:bg-slate-800 py-8 px-4 shadow-xl shadow-slate-200/50 dark:shadow-none sm:rounded-xl sm:px-10 border border-slate-200 dark:border-slate-700">
          {children}
        </div>
      </div>
    </div>
  );
}
