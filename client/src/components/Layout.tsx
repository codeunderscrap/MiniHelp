
import { useState, useEffect } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { LayoutDashboard, Ticket, PlusCircle, Settings, LogOut, Bell, Search, Sun, Moon } from 'lucide-react';
import { useAuthStore } from '../store';
import './Layout.css';

export function Layout() {
  const { user, logout, theme, toggleTheme } = useAuthStore();
  const [deferredPrompt, setDeferredPrompt] = useState<any>(null);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    const handleBeforeInstallPrompt = (e: any) => {
      e.preventDefault();
      setDeferredPrompt(e);
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);

    if (window.matchMedia('(display-mode: standalone)').matches) {
      setIsInstalled(true);
    }

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    };
  }, []);

  const handleInstallClick = async () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      if (outcome === 'accepted') {
        setDeferredPrompt(null);
      }
    }
  };

  return (
    <div className="layout-container">
      {/* Sidebar */}
      <aside className="sidebar glass">
        <div className="sidebar-header">
          <div className="logo-placeholder">MH</div>
          <span className="brand-name">MiniHelp</span>
        </div>
        
        <nav className="sidebar-nav">
          <div className="nav-group">
            <div className="nav-group-label">Main Workspace</div>
            <NavLink to="/" end className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <LayoutDashboard size={18} />
              <span>Dashboard</span>
            </NavLink>
            <NavLink to="/tickets" end className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <Ticket size={18} />
              <span>Pulse Board</span>
            </NavLink>
            <NavLink to="/tickets/kanban" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <LayoutDashboard size={18} />
              <span>Kanban</span>
            </NavLink>
          </div>

          <div className="nav-group">
            <div className="nav-group-label">Actions</div>
            <NavLink to="/tickets/new" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <PlusCircle size={18} />
              <span>Create Ticket</span>
            </NavLink>
          </div>
          
          {(user?.role === 'admin' || user?.role === 'dept_head') && (
            <div className="nav-group mt-auto">
              <div className="nav-group-label">Administration</div>
              <NavLink to="/settings" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
                <Settings size={18} />
                <span>Settings</span>
              </NavLink>
            </div>
          )}
        </nav>
      </aside>

      {/* Main Content Area */}
      <div className="main-wrapper">
        <header className="top-header glass">
          <div className="header-search">
            <Search size={18} className="search-icon" />
            <input type="text" placeholder="Search tickets..." className="search-input" />
          </div>
          <div className="header-actions">
            <button className="icon-btn" onClick={toggleTheme}>
              {theme === 'dark' ? <Sun size={20} /> : <Moon size={20} />}
            </button>
            <button className="icon-btn">
              <Bell size={20} />
            </button>
            <div className="user-profile">
              <div className="avatar">{user?.name.charAt(0)}</div>
              <div className="user-info">
                <span className="user-name">{user?.name}</span>
                <span className="user-role">{user?.role}</span>
              </div>
            </div>
            <button className="icon-btn logout-btn" onClick={logout}>
              <LogOut size={20} />
            </button>
          </div>
        </header>

        <main className="main-content">
          {!isInstalled && deferredPrompt && (
            <div className="pwa-install-banner glass" style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '12px 24px',
              marginBottom: '20px',
              borderRadius: '8px',
              backgroundColor: 'var(--bg-tertiary)',
              border: '1px solid var(--accent-primary)'
            }}>
              <div>
                <h4 style={{ margin: 0, color: 'var(--text-primary)' }}>Install MiniHelp Desktop App</h4>
                <p style={{ margin: 0, fontSize: '0.875rem' }}>Get quicker access and a better experience.</p>
              </div>
              <button className="btn-primary" onClick={handleInstallClick}>
                Install App
              </button>
            </div>
          )}
          <Outlet />
        </main>
      </div>
    </div>
  );
}
