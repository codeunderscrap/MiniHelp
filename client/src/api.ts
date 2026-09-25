import axios from 'axios';
import { useAuthStore } from './store';

// Using relative path so Vite proxy (dev) or Nginx proxy (prod) handles it
export const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('minihelp_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// An expired or revoked session answers 401: drop the stale local sign-in and go to /login,
// which offers MM OS sign-in again. A failed password attempt also answers 401, so auth.php
// is left to the login form.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const url: string = error.config?.url || '';
    if (error.response?.status === 401 && !url.includes('/auth.php')) {
      useAuthStore.getState().logout();
      if (window.location.pathname !== '/login') {
        window.location.replace('/login');
      }
    }
    return Promise.reject(error);
  }
);
