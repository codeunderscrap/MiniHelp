import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'dept_head' | 'agent' | 'employee' | 'user';
  avatar_url?: string;
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  theme: 'dark' | 'light';
  login: (user: User, token: string) => void;
  loginFromSSO: (user: User, token: string) => void;
  logout: () => void;
  toggleTheme: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      isAuthenticated: false,
      theme: (localStorage.getItem('minihelp_theme') as 'dark' | 'light') || 'light',
      login: (user, token) => {
        localStorage.setItem('minihelp_token', token);
        set({ user, isAuthenticated: true });
      },
      loginFromSSO: (user, token) => {
        localStorage.setItem('minihelp_token', token);
        set({ user, isAuthenticated: true });
      },
      logout: () => {
        localStorage.removeItem('minihelp_token');
        set({ user: null, isAuthenticated: false });
      },
      toggleTheme: () => set((state) => {
        const newTheme = state.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('minihelp_theme', newTheme);
        document.documentElement.setAttribute('data-theme', newTheme);
        return { theme: newTheme };
      }),
    }),
    {
      name: 'minihelp-auth-storage',
      partialize: (state) => ({ user: state.user, isAuthenticated: state.isAuthenticated }),
    }
  )
);
