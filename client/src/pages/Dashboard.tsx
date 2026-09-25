import { useEffect, useState } from 'react';
import { Activity, Clock, CheckCircle, AlertCircle } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import './Dashboard.css';
import { api } from '../api';
import { useAuthStore } from '../store';

export function Dashboard() {
  const user = useAuthStore(state => state.user);
  const [stats, setStats] = useState({ open: 0, progress: 0, resolved: 0, avg: '0h' });
  const [recentTickets, setRecentTickets] = useState<any[]>([]);
  const navigate = useNavigate();

  

  useEffect(() => {
    // Request Notification permission
    if ('Notification' in window) {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          subscribeToPush();
        }
      });
    }

    if (user) {
      fetchData();
    }
  }, [user]);

  const urlB64ToUint8Array = (base64String: string) => {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, '+')
      .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  };

  const subscribeToPush = async () => {
    try {
      const registration = await navigator.serviceWorker.ready;
      const keyRes = await api.get('/push_key.php');
      const serverKey = urlB64ToUint8Array(keyRes.data.publicKey);

      // A subscription made with an earlier server key can't receive pushes; replace it.
      const existing = await registration.pushManager.getSubscription();
      if (existing) {
        const existingKey = existing.options.applicationServerKey
          ? new Uint8Array(existing.options.applicationServerKey)
          : null;
        const sameKey = !!existingKey && existingKey.length === serverKey.length
          && existingKey.every((b, i) => b === serverKey[i]);
        if (!sameKey) await existing.unsubscribe();
      }

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: serverKey
      });

      await api.post('/subscribe.php', {
        subscription,
        user_id: user?.id
      });
      console.log('Successfully subscribed to Push API');
    } catch (e) {
      console.error('Failed to subscribe to push', e);
    }
  };

  const fetchData = async () => {
    try {
      if (!user) return;
      const res = await api.get(`/tickets.php?role=${user.role}&user_id=${user.id}&department_id=${(user as any).department_id || ''}`);
      
      if (res.data && res.data.success) {
        const t = res.data.data;
        setRecentTickets(t.slice(0, 5));
        
        let open = 0, progress = 0, resolved = 0;
        t.forEach((tick: any) => {
          const status = (tick.status || 'open').toLowerCase();
          if (status === 'open') open++;
          if (status === 'in_progress') progress++;
          if (status === 'resolved' || status === 'closed') resolved++;
        });
        setStats({ open, progress, resolved, avg: '2.5h' }); 
      }
    } catch (err) {
      console.error(err);
    }
  };

  const kpis = [
    { title: 'Open Tickets', value: stats.open, icon: <AlertCircle size={24} strokeWidth={1.5} />, color: 'var(--status-open)' },
    { title: 'In Progress', value: stats.progress, icon: <Activity size={24} strokeWidth={1.5} />, color: 'var(--status-progress)' },
    { title: 'Resolved', value: stats.resolved, icon: <CheckCircle size={24} strokeWidth={1.5} />, color: 'var(--status-resolved)' },
    { title: 'Avg. Resolution Time', value: stats.avg, icon: <Clock size={24} strokeWidth={1.5} />, color: 'var(--accent-secondary)' }
  ];

  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <div>
          <h1>Dashboard</h1>
          <p>Welcome back! Here's an overview of your support tickets.</p>
        </div>
      </div>

      <div className="kpi-grid">
        {kpis.map((kpi, idx) => (
          <div key={idx} className="kpi-card glass">
            <div className="kpi-icon" style={{ color: kpi.color, backgroundColor: `${kpi.color}20` }}>
              {kpi.icon}
            </div>
            <div className="kpi-info">
              <span className="kpi-title">{kpi.title}</span>
              <span className="kpi-value">{kpi.value}</span>
            </div>
          </div>
        ))}
      </div>

      {user?.role === 'employee' && stats.open > 0 && (
        <div className="glass" style={{ padding: '16px 24px', marginBottom: '24px', background: 'linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%)', color: 'white', borderRadius: '8px' }}>
          <h3 style={{ margin: '0 0 8px 0', fontSize: '1.25rem' }}>Your ticket is in the queue! 🕒</h3>
          <p style={{ margin: 0, opacity: 0.9 }}>
            Our IT Support team has received your request. There are currently <strong>3 tickets</strong> ahead of you. 
            Based on current SLAs (managed by Admin), your issue will be entertained within <strong>2 hours</strong>.
          </p>
        </div>
      )}

      <div className="dashboard-content">
        <div className="recent-tickets glass">
          <div className="card-header">
            <h3>Recent Tickets</h3>
            <Link to="/tickets" className="btn-secondary" style={{ textDecoration: 'none' }}>View All</Link>
          </div>
          <div className="table-responsive">
            <table className="tickets-table">
              <thead>
                <tr>
                  <th>Ticket ID</th>
                  <th>Title</th>
                  <th>Status</th>
                  <th>Priority</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                {recentTickets.length === 0 ? (
                  <tr><td colSpan={5} style={{textAlign: 'center', padding: '20px'}}>No tickets found.</td></tr>
                ) : (
                  recentTickets.map((t) => (
                    <tr key={t.id} onClick={() => navigate(`/tickets/${t.id}`)} style={{ cursor: 'pointer' }}>
                      <td><span className="mono ticket-id">{t.ticket_number || t.id}</span></td>
                      <td className="ticket-title">{t.title}</td>
                      <td style={{ position: 'relative' }} onClick={e => e.stopPropagation()}>
                        <span className={`status-badge status-${(t.status || 'open').toLowerCase().replace(' ', '')}`}>
                          {(t.status || 'open').toUpperCase()}
                        </span>
                        {user?.role === 'admin' && (
                          <select 
                            style={{
                              position: 'absolute', top: 0, left: 0, width: '100%', height: '100%',
                              opacity: 0, cursor: 'pointer'
                            }}
                            value={t.status || 'open'}
                            onChange={(e) => {
                              api.patch(`/tickets.php?id=${t.id}`, { status: e.target.value })
                                .then(() => fetchData());
                            }}
                          >
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                          </select>
                        )}
                      </td>
                      <td>
                        <span className={`priority-badge priority-${(t.priority || 'low').toLowerCase()}`}>
                          {(t.priority || 'low').toUpperCase()}
                        </span>
                      </td>
                      <td className="text-muted">{new Date(t.created_at).toLocaleDateString()}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
