import { create } from 'zustand';

interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'dept_head' | 'agent' | 'employee' | 'user';
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  theme: 'dark' | 'light';
  login: (user: User) => void;
  logout: () => void;
  toggleTheme: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  isAuthenticated: false,
  theme: (localStorage.getItem('minihelp_theme') as 'dark' | 'light') || 'dark',
  login: (user) => set({ user, isAuthenticated: true }),
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
}));
