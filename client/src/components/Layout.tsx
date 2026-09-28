import { useState, useEffect, useRef } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { LayoutDashboard, Ticket, PlusCircle, Settings, LogOut, Bell, Sun, Moon } from 'lucide-react';
import { useAuthStore } from '../store';
import { api } from '../api';
import './Layout.css';

export function Layout() {
  const { user, logout, theme, toggleTheme } = useAuthStore();
  const navigate = useNavigate();
  const [deferredPrompt, setDeferredPrompt] = useState<any>(null);
  const [isInstalled, setIsInstalled] = useState(false);
  
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [showDropdown, setShowDropdown] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const fetchNotifications = async () => {
    if (!user) return;
    try {
      const res = await api.get(`/notifications.php?user_id=${user.id}`);
      if (res.data?.success) {
        const newUnread = res.data.unread_count || 0;
        
        // Play sound if unread count increases
        setUnreadCount(prev => {
          if (newUnread > prev && prev !== -1) { // use -1 trick if we want to avoid initial load sound, but here we can just do if prev > 0 or if we use a ref.
            // Dispatch a play sound event for App.tsx to handle, or play directly
            window.dispatchEvent(new CustomEvent('play-sound', {
                detail: { type: 'PLAY_SOUND', priority: 'medium' }
            }));
          }
          return newUnread;
        });
        
        setNotifications(res.data.data);
      }
    } catch (err) {}
  };

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 60000); // Check every minute
    
    const handleRefresh = () => fetchNotifications();
    window.addEventListener('refresh-notifications', handleRefresh);

    // Force Service Worker Update check
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(registrations => {
        for (let registration of registrations) {
          registration.update();
        }
      });
    }
    
    return () => {
      clearInterval(interval);
      window.removeEventListener('refresh-notifications', handleRefresh);
    };
  }, [user]);

  const handleOpenNotifications = async () => {
    setShowDropdown(!showDropdown);
    if (!showDropdown && unreadCount > 0 && user) {
      try {
        await api.patch('/notifications.php', { user_id: user.id });
        setUnreadCount(0);
      } catch (err) {}
    }
  };

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

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
          <img src="/logo.png" alt="MiniMines" className="sidebar-logo" />
          <span className="brand-name">Helpdesk</span>
        </div>
        
        <nav className="sidebar-nav">
          <div className="nav-group">
            <div className="nav-group-label">Main Workspace</div>
            <NavLink to="/" end className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <LayoutDashboard size={20} strokeWidth={1.5} />
              <span>Dashboard</span>
            </NavLink>
            <NavLink to="/tickets" end className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <Ticket size={20} strokeWidth={1.5} />
              <span>Pulse Board</span>
            </NavLink>
            {user?.role !== 'employee' && (
              <NavLink to="/tickets/kanban" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
                <LayoutDashboard size={20} strokeWidth={1.5} />
                <span>Kanban</span>
              </NavLink>
            )}
          </div>

          <div className="nav-group">
            <div className="nav-group-label">Actions</div>
            <NavLink to="/tickets/new" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
              <PlusCircle size={20} strokeWidth={1.5} />
              <span>Create Ticket</span>
            </NavLink>
          </div>
          
          {(user?.role === 'admin' || user?.role === 'dept_head') && (
            <div className="nav-group mt-auto">
              <div className="nav-group-label">Administration</div>
              <NavLink to="/settings" className={({isActive}) => isActive ? 'nav-item active' : 'nav-item'}>
                <Settings size={20} strokeWidth={1.5} />
                <span>Settings</span>
              </NavLink>
            </div>
          )}
        </nav>
      </aside>

      {/* Main Content Area */}
      <div className="main-wrapper">
        <header className="top-header glass" style={{ position: "relative", zIndex: 50 }}>
          <div className="mobile-logo-wrapper">
            <img src="/logo.png" alt="MiniMines" className="mobile-logo" />
            <span className="mobile-brand-name">Helpdesk</span>
          </div>
          <div className="header-actions" style={{ marginLeft: 'auto' }}>
            <button className="icon-btn" onClick={toggleTheme}>
              {theme === 'dark' ? <Sun size={20} strokeWidth={1.5} /> : <Moon size={20} strokeWidth={1.5} />}
            </button>
            <div className="notification-wrapper" ref={dropdownRef} style={{ position: 'relative' }}>
              <button className="icon-btn" onClick={() => { if ('Notification' in window && Notification.permission === 'default') { Notification.requestPermission(); }; handleOpenNotifications(); }} style={{ position: 'relative' }} title="Click to view notifications and enable alerts">
                <Bell size={20} strokeWidth={1.5} />
                {unreadCount > 0 && (
                  <span className="notification-badge">{unreadCount}</span>
                )}
              </button>
              
              {showDropdown && (
                <div className="notification-dropdown glass" style={{
                  position: 'absolute',
                  top: '100%',
                  right: 0,
                  width: '320px',
                  maxHeight: '400px',
                  overflowY: 'auto',
                  background: 'var(--bg-secondary)',
                  border: '1px solid var(--border)',
                  borderRadius: '8px',
                  boxShadow: 'var(--shadow-glass)',
                  zIndex: 1000,
                  padding: '12px 0'
                }}>
                  <div style={{ padding: '0 16px 12px', borderBottom: '1px solid var(--border)', fontWeight: 600 }}>Notifications</div>
                  {notifications.length === 0 ? (
                    <div style={{ padding: '16px', textAlign: 'center', color: 'var(--text-tertiary)' }}>No notifications</div>
                  ) : (
                    notifications.map(n => (
                      <div key={n.id} 
                           onClick={() => { setShowDropdown(false); if(n.ticket_id) navigate(`/tickets/${n.ticket_id}`); }}
                           style={{
                        padding: '12px 16px',
                        borderBottom: '1px solid var(--border)',
                        cursor: n.ticket_id ? 'pointer' : 'default',
                        background: n.is_read ? 'transparent' : 'rgba(0, 212, 170, 0.05)',
                        transition: 'background 0.2s'
                      }}
                      onMouseEnter={(e) => e.currentTarget.style.background = 'var(--bg-tertiary)'}
                      onMouseLeave={(e) => e.currentTarget.style.background = n.is_read ? 'transparent' : 'rgba(0, 212, 170, 0.05)'}
                      >
                        <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-primary)', marginBottom: '4px' }}>{n.title}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginBottom: '4px' }}>{n.message}</div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', color: 'var(--text-tertiary)' }}>
                          <span>{n.department_name || 'System'}</span>
                          <span>{new Date(n.created_at).toLocaleString([], {hour: '2-digit', minute:'2-digit', month: 'short', day: 'numeric'})}</span>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
            <div className="user-profile" style={{ cursor: 'pointer' }} onClick={() => document.getElementById('avatar-upload')?.click()} title="Change Profile Picture">
              <input 
                type="file" 
                id="avatar-upload" 
                style={{ display: 'none' }} 
                accept="image/*"
                onChange={async (e) => {
                  if (e.target.files && e.target.files[0] && user) {
                    const formData = new FormData();
                    formData.append('avatar', e.target.files[0]);
                    formData.append('user_id', user.id.toString());
                    try {
                      const res = await api.post('/upload_avatar.php', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                      });
                      if (res.data?.success) {
                        // Update user store
                        const updatedUser = { ...user, avatar_url: res.data.avatar_url };
                        useAuthStore.getState().login(updatedUser, localStorage.getItem('minihelp_token') || '');
                        alert("Profile picture updated!");
                      } else {
                        alert(res.data?.error || "Upload failed");
                      }
                    } catch (err) {
                      alert("Error uploading image");
                    }
                  }
                }}
              />
              {user?.avatar_url ? (
                <img src={user.avatar_url} alt="Avatar" className="avatar" style={{ width: '36px', height: '36px', borderRadius: '50%', objectFit: 'cover', border: '2px solid var(--accent-primary)' }} />
              ) : (
                <div className="avatar" style={{ position: 'relative' }}>
                  {user?.name.charAt(0)}
                  <div style={{ position: 'absolute', bottom: -2, right: -2, background: 'var(--bg-secondary)', borderRadius: '50%', padding: '2px' }}>
                     <PlusCircle size={10} color="var(--accent-primary)" />
                  </div>
                </div>
              )}
              <div className="user-info">
                <span className="user-name">{user?.name}</span>
                <span className="user-role">{user?.role === 'agent' ? 'Specialist' : user?.role === 'dept_head' ? 'Department Head' : user?.role}</span>
              </div>
            </div>
            <button className="icon-btn logout-btn" onClick={logout}>
              <LogOut size={20} strokeWidth={1.5} />
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




