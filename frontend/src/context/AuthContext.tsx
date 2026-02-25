import React, { createContext, useContext, useState, useEffect } from 'react';
import { authApi } from '@/api/auth';
import { apiClient } from '@/api/client';

interface AuthContextType {
  user: any | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (data: any) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check token on mount or when URL changes
    checkAuth();
  }, []);

  const checkAuth = async () => {
    // Check for token in URL params (for OAuth redirects)
    const urlParams = new URLSearchParams(window.location.search);
    const tokenFromUrl = urlParams.get('token');
    if (tokenFromUrl) {
      localStorage.setItem('auth_token', tokenFromUrl);
      // Remove token from URL
      const newUrl = window.location.pathname + window.location.hash;
      window.history.replaceState({}, '', newUrl);
    }

    const token = localStorage.getItem('auth_token');
    if (token) {
      // Set the token in axios headers
      apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      try {
        const response = await authApi.me();
        setUser(response.data);
      } catch (error: any) {
        // Token is invalid
        localStorage.removeItem('auth_token');
        delete apiClient.defaults.headers.common['Authorization'];
        setUser(null);
      }
    } else {
      setUser(null);
    }
    setIsLoading(false);
  };

  const login = async (data: any) => {
    // 1. Handshake: Get the CSRF cookie from Laravel (still needed for some requests)
    await authApi.getCsrf(); 

    // 2. Auth: Send the credentials and get token
    const response = await authApi.login(data);
    
    // 3. Store token and user
    const { token, user: userData } = response.data;
    localStorage.setItem('auth_token', token);
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    setUser(userData);
  };

  const logout = async () => {
    try {
      // Get CSRF cookie first
      await authApi.getCsrf();
      // Call logout API
      await authApi.logout();
    } catch (error) {
      console.warn('Logout API failed', error);
    } finally {
      // Always clear local state
      localStorage.removeItem('auth_token');
      delete apiClient.defaults.headers.common['Authorization'];
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
};
