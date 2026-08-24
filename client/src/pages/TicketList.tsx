import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import './TicketList.css';
import { Search, Filter, MessageSquare, X, Send } from 'lucide-react';

interface Ticket {
  id: string;
  title: string;
  status: string;
  priority: string;
  department_id: string;
  created_at: string;
  assigned_to?: string;
}

export function TicketList() {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  
  // Chat slide-out state
  const [activeTicketChat, setActiveTicketChat] = useState<string | null>(null);
  const [comments, setComments] = useState<any[]>([]);
  const [newComment, setNewComment] = useState('');
  const [chatLoading, setChatLoading] = useState(false);

  // We will fall back to dummy data if API fails
  const dummyTickets = [
    { id: 'TKT-1024', title: 'Cannot access internal CRM', status: 'Working on it', priority: 'High', department_id: 'IT', created_at: 'Oct 12' },
    { id: 'TKT-1025', title: 'Need access to Github', status: 'Done', priority: 'Medium', department_id: 'IT', created_at: 'Oct 11' },
    { id: 'TKT-1026', title: 'Request for new software license', status: 'Working on it', priority: 'Medium', department_id: 'Finance', created_at: 'Oct 11' },
    { id: 'TKT-1027', title: 'VPN connection dropping', status: 'Stuck', priority: 'Critical', department_id: 'IT', created_at: 'Oct 10' },
  ];

  const fetchTickets = async () => {
    setLoading(true);
    try {
      const res = await api.get('/tickets.php');
      if (res.data && res.data.success) {
        setTickets(res.data.data);
      } else {
        setTickets(dummyTickets);
      }
    } catch (err) {
      console.error('API Error, using dummy data', err);
      setTickets(dummyTickets);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  useEffect(() => {
    if (activeTicketChat) {
      fetchComments(activeTicketChat);
    }
  }, [activeTicketChat]);

  const fetchComments = async (ticketId: string) => {
    setChatLoading(true);
    try {
      const res = await api.get(`/comments.php?ticket_id=${ticketId}`);
      if (res.data && res.data.success) {
        setComments(res.data.data);
      } else {
        setComments([]);
      }
    } catch (err) {
      console.error('API Error fetching comments', err);
      setComments([]);
    } finally {
      setChatLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'done':
      case 'resolved':
        return 'status-done';
      case 'working on it':
      case 'in progress':
        return 'status-working';
      case 'stuck':
      case 'critical':
      case 'open':
        return 'status-stuck';
      default:
        return 'status-default';
    }
  };

  const handleSendComment = async () => {
    if (newComment.trim() && activeTicketChat) {
      try {
        const res = await api.post('/comments.php', {
          ticket_id: activeTicketChat,
          content: newComment.trim()
        });
        if (res.data && res.data.success) {
          setNewComment('');
          fetchComments(activeTicketChat);
        }
      } catch (err) {
        console.error('API Error sending comment', err);
      }
    }
  };

  return (
    <div className="pulse-board">
      <div className="board-header">
        <div>
          <h1>Issue Tracker</h1>
          <p>Main workspace / IT Department</p>
        </div>
        <div className="board-actions">
          <button className="btn-secondary"><Filter size={16} /> Filter</button>
          <button className="btn-primary" onClick={() => navigate('/tickets/new')}>New Item</button>
        </div>
      </div>

      <div className="board-controls">
        <div className="search-bar">
          <Search size={16} className="text-muted" />
          <input type="text" placeholder="Search..." />
        </div>
      </div>

      <div className="board-group glass">
        <div className="group-header">
          <div className="group-color-indicator" style={{ backgroundColor: 'var(--accent-primary)' }}></div>
          <h2>This Month</h2>
          <span className="count-badge">{tickets.length} items</span>
        </div>

        <div className="pulse-table-container">
          <table className="pulse-table">
            <thead>
              <tr>
                <th className="cell-checkbox"><input type="checkbox" /></th>
                <th className="cell-item">Item</th>
                <th className="cell-person">Owner</th>
                <th className="cell-status">Status</th>
                <th className="cell-priority">Priority</th>
                <th className="cell-date">Date</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={6} style={{ textAlign: 'center', padding: '20px' }}>Loading...</td></tr>
              ) : (
                tickets.map(ticket => (
                  <tr key={ticket.id} className="pulse-row">
                    <td className="cell-checkbox"><input type="checkbox" /></td>
                    <td className="cell-item">
                      <div className="item-title">
                        <span>{ticket.title}</span>
                        <MessageSquare 
                          size={16} 
                          className="chat-icon" 
                          onClick={() => setActiveTicketChat(ticket.id)}
                        />
                      </div>
                    </td>
                    <td className="cell-person">
                      <div className="person-avatar">
                        {ticket.assigned_to ? ticket.assigned_to.charAt(0) : 'U'}
                      </div>
                    </td>
                    <td className="cell-status">
                      <div className={`status-pill ${getStatusColor(ticket.status)}`}>
                        {ticket.status}
                        <div className="corner-fold"></div>
                      </div>
                    </td>
                    <td className="cell-priority">
                      <div className={`status-pill ${getStatusColor(ticket.priority)}`}>
                        {ticket.priority}
                      </div>
                    </td>
                    <td className="cell-date">{ticket.created_at}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Chat Slide-out Panel */}
      <div className={`chat-slide-panel ${activeTicketChat ? 'open' : ''}`}>
        <div className="chat-header">
          <h3>Updates for {activeTicketChat}</h3>
          <button className="icon-btn" onClick={() => setActiveTicketChat(null)}>
            <X size={20} />
          </button>
        </div>
        <div className="chat-content">
          {chatLoading ? (
            <div style={{ padding: '20px', textAlign: 'center' }}>Loading comments...</div>
          ) : comments.length === 0 ? (
            <div style={{ padding: '20px', textAlign: 'center', color: 'var(--text-secondary)' }}>No comments yet.</div>
          ) : (
            comments.map((comment: any) => (
              <div className="dummy-comment" key={comment.id}>
                <div className="avatar">{comment.author_name ? comment.author_name.charAt(0).toUpperCase() : 'U'}</div>
                <div className="comment-body">
                  <div className="comment-meta">
                    <span className="author">{comment.author_name || 'User'}</span>
                    <span className="time">{comment.created_at}</span>
                  </div>
                  <p>{comment.content}</p>
                </div>
              </div>
            ))
          )}
        </div>
        <div className="chat-input-area">
          <textarea 
            placeholder="Write an update..." 
            value={newComment}
            onChange={(e) => setNewComment(e.target.value)}
          ></textarea>
          <button className="btn-primary send-btn" onClick={handleSendComment}>
            <Send size={16} /> Send
          </button>
        </div>
      </div>
      {activeTicketChat && (
        <div className="chat-overlay" onClick={() => setActiveTicketChat(null)}></div>
      )}
    </div>
  );
}
