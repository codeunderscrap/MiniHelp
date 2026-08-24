import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store';
import { api } from '../api';
import './Login.css';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const login = useAuthStore(state => state.login);
  const navigate = useNavigate();

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await api.post('/auth.php', { email, password });
      if (res.data && res.data.success) {
        localStorage.setItem('minihelp_token', res.data.token);
        login(res.data.user);
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
          <div className="brand-header">
            <div className="logo-placeholder">MH</div>
            <h2>MiniHelp</h2>
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
        </div>
      </div>
      <div className="login-right">
        <div className="login-showcase">
          <div className="showcase-content glass">
            <h2>Streamline your support</h2>
            <p>MiniHelp is the premium problem ticket management platform designed exclusively for MiniMines.</p>
            <ul className="feature-list">
              <li>✨ Real-time Collaboration</li>
              <li>⚡ Lightning Fast Workflows</li>
              <li>📊 Deep Analytics & Reporting</li>
            </ul>
          </div>
          <div className="abstract-shapes">
            <div className="shape shape-1"></div>
            <div className="shape shape-2"></div>
            <div className="shape shape-3"></div>
          </div>
        </div>
      </div>
    </div>
  );
}
