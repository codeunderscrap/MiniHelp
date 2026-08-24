import { useEffect } from 'react';
import { Activity, Clock, CheckCircle, AlertCircle } from 'lucide-react';
import './Dashboard.css';
import { api } from '../api';

export function Dashboard() {
  useEffect(() => {
    // Request Notification permission
    if ('Notification' in window) {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          subscribeToPush();
        }
      });
    }
  }, []);

  const subscribeToPush = async () => {
    console.log('Subscribing to push notifications...');
    try {
      // Dummy Service Worker registration & pushManager logic
      const dummySubscription = { endpoint: 'https://dummy.push.endpoint' };
      await api.post('/subscribe.php', { subscription: dummySubscription }).catch(() => console.log('Dummy subscribe API called'));
      console.log('Successfully subscribed to Push API');
    } catch (e) {
      console.error('Failed to subscribe to push', e);
    }
  };

  const playNotificationSound = () => {
    console.log('Playing notification.mp3 notification sound...');
    const audio = new Audio('/notification.mp3');
    audio.play().catch(e => console.error('Audio play failed:', e));
  };

  useEffect(() => {
    // @ts-ignore
    window.playNotificationSound = playNotificationSound;
  }, []);

  const kpis = [
    { title: 'Open Tickets', value: '24', icon: <AlertCircle />, color: 'var(--status-open)' },
    { title: 'In Progress', value: '12', icon: <Activity />, color: 'var(--status-progress)' },
    { title: 'Resolved', value: '148', icon: <CheckCircle />, color: 'var(--status-resolved)' },
    { title: 'Avg. Resolution Time', value: '4.2h', icon: <Clock />, color: 'var(--accent-secondary)' }
  ];

  const recentTickets = [
    { id: 'TKT-1024', title: 'Cannot access internal CRM', status: 'Open', priority: 'High', date: '2h ago' },
    { id: 'TKT-1023', title: 'Request for new software license', status: 'In Progress', priority: 'Medium', date: '5h ago' },
    { id: 'TKT-1022', title: 'VPN connection dropping', status: 'Resolved', priority: 'Critical', date: '1d ago' },
    { id: 'TKT-1021', title: 'Update onboarding docs', status: 'Closed', priority: 'Low', date: '2d ago' },
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

      <div className="dashboard-content">
        <div className="recent-tickets glass">
          <div className="card-header">
            <h3>Recent Tickets</h3>
            <button className="btn-secondary">View All</button>
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
                {recentTickets.map((t) => (
                  <tr key={t.id}>
                    <td><span className="mono ticket-id">{t.id}</span></td>
                    <td className="ticket-title">{t.title}</td>
                    <td>
                      <span className={`status-badge status-${t.status.toLowerCase().replace(' ', '')}`}>
                        {t.status}
                      </span>
                    </td>
                    <td>
                      <span className={`priority-badge priority-${t.priority.toLowerCase()}`}>
                        {t.priority}
                      </span>
                    </td>
                    <td className="text-muted">{t.date}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
