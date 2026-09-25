import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store';
import { api } from '../api';
import './Login.css';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [ssoUrl, setSsoUrl] = useState<string | null>(null);
  const login = useAuthStore(state => state.login);
  const isAuthenticated = useAuthStore(state => state.isAuthenticated);
  const navigate = useNavigate();

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/', { replace: true });
      return;
    }
    fetch('/_mmos/info')
      .then(r => r.json())
      .then(data => {
        if (data.auth_mode === 'http' && data.os_url) {
          setSsoUrl(data.os_url);
        }
      })
      .catch(() => {});
  }, [isAuthenticated, navigate]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await api.post('/auth.php', { email, password });
      if (res.data && res.data.success) {
        login(res.data.user, res.data.token);
        navigate('/');
      } else {
        setError(res.data?.error || 'Invalid credentials');
      }
    } catch (err: any) {
      setError(err.response?.data?.error || 'An error occurred during login');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-left">
        <div className="login-form-wrapper glass">
          <div className="brand-header" style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '30px' }}>
            <img src="/logo.png" alt="MiniMines" style={{ height: '40px', objectFit: 'contain' }} />
            <h2 style={{ margin: 0 }}>Service Desk</h2>
          </div>
          <h1>Welcome back</h1>
          <p className="login-subtitle">Sign in to your account to continue</p>
          
          {error && <div className="login-error">{error}</div>}

          <form onSubmit={handleLogin} className="login-form">
            <div className="form-group">
              <label>Email Address</label>
              <input 
                type="email" 
                className="form-input" 
                placeholder="name@minimines.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="form-group">
              <label>Password</label>
              <input 
                type="password" 
                className="form-input" 
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <div className="form-options">
              <label className="checkbox-label">
                <input type="checkbox" /> Remember me
              </label>
              <a href="#" className="forgot-password">Forgot password?</a>
            </div>
            <button type="submit" className="btn-primary login-btn" disabled={loading}>
              {loading ? 'Signing in...' : 'Sign In'}
            </button>
          </form>
          {ssoUrl && (
            <div style={{ marginTop: '20px', textAlign: 'center' }}>
              <div style={{ color: 'var(--text-secondary)', fontSize: '13px', marginBottom: '12px' }}>or</div>
              <a
                href={ssoUrl}
                className="btn-primary login-btn"
                style={{ display: 'block', textDecoration: 'none', background: '#005D7F' }}
              >
                Sign in with MM OS
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
