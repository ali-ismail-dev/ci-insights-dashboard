import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import AuthLayout from '@/layouts/AuthLayout';
import { apiClient } from '@/api/client';
import { User, Mail, Lock, Loader2, ArrowRight, Github } from 'lucide-react';
import toast from 'react-hot-toast';

export default function Register() {
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);
  
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (formData.password !== formData.password_confirmation) {
      return toast.error('Passwords do not match');
    }

    setIsLoading(true);
    try {
      // Direct call to Laravel registration endpoint
      await apiClient.post('/register', formData);
      toast.success('Account created! Please sign in.');
      navigate('/login');
    } catch (error: any) {
      const errors = error.response?.data?.errors;
      const message = errors ? Object.values(errors)[0][0] : 'Registration failed';
      toast.error(message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout title="Create Account" subtitle="Join the elite CI monitoring community">
      <form className="space-y-4" onSubmit={handleSubmit}>
        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
          <div className="mt-1 relative">
            <User className="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
            <input
              type="text"
              required
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              className="block w-full pl-10 pr-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none transition-all sm:text-sm"
              placeholder="Alex Rivera"
            />
          </div>
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Work Email</label>
          <div className="mt-1 relative">
            <Mail className="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
            <input
              type="email"
              required
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              className="block w-full pl-10 pr-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none transition-all sm:text-sm"
              placeholder="alex@company.com"
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Password</label>
            <div className="mt-1 relative">
              <Lock className="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
              <input
                type="password"
                required
                value={formData.password}
                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                className="block w-full pl-10 pr-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none transition-all sm:text-sm"
              />
            </div>
          </div>
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest ml-1">Confirm</label>
            <div className="mt-1 relative">
              <Lock className="absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
              <input
                type="password"
                required
                value={formData.password_confirmation}
                onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                className="block w-full pl-10 pr-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none transition-all sm:text-sm"
              />
            </div>
          </div>
        </div>

        <button
          type="submit"
          disabled={isLoading}
          className="w-full mt-2 flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-all active:scale-95"
        >
          {isLoading ? <Loader2 className="animate-spin h-5 w-5" /> : (
            <>Create Account <ArrowRight className="h-4 w-4" /></>
          )}
        </button>

        <div className="relative my-4">
          <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-slate-200 dark:border-slate-700"></div></div>
          <div className="relative flex justify-center text-xs uppercase"><span className="px-2 bg-white dark:bg-slate-800 text-slate-400 font-bold">Standard Access</span></div>
        </div>

        <button
          type="button"
          className="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-slate-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 text-sm font-medium transition-all"
        >
          <Github className="h-5 w-5" /> Continue with GitHub
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-slate-600 dark:text-slate-400 font-medium">
        Joined us before?{' '}
        <Link to="/login" className="text-indigo-600 hover:text-indigo-500 font-extrabold underline decoration-2 underline-offset-4">
          Sign In
        </Link>
      </p>
    </AuthLayout>
  );
}
