import axios from 'axios';
import { apiClient } from './client';

// We create a base instance specifically for the root Laravel routes (Login/Register/CSRF)
// Use the same base URL as the API client
const authClient = axios.create({
  baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080/api').replace('/api', ''),
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN', // The name of the cookie Laravel sends
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
});

export const authApi = {
  // 1. Get CSRF handshake from the root
  getCsrf: () => authClient.get('/sanctum/csrf-cookie'),
  
  // 2. Login/Register are now API routes
  login: (data: any) => authClient.post('/api/login', data),
  register: (data: any) => authClient.post('/api/register', data),
  logout: () => authClient.post('/api/logout'),
  
  // 3. The 'me' user data
  me: () => apiClient.get('/user'),
};
