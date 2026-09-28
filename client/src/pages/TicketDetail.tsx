import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api';
import { useAuthStore } from '../store';
import { ArrowLeft, Clock, MessageSquare, Send, Paperclip } from 'lucide-react';
import './TicketDetail.css';

export function TicketDetail() {
  const { id } = useParams();
  const user = useAuthStore(state => state.user);
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [newComment, setNewComment] = useState('');

  const fetchTicketDetails = async () => {
    try {
      const res = await api.get(`/tickets.php?id=${id}`);
      if (res.data && res.data.success) {
        setTicket(res.data.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTicketDetails();
    
    // Auto-refresh chat every 5 seconds
    const interval = setInterval(() => {
      fetchTicketDetails();
    }, 5000);
    
    return () => clearInterval(interval);
  }, [id]);

  const handleSendComment = async () => {
    if (!newComment.trim() || !user) return;
    try {
      const res = await api.post('/comments.php', {
        ticket_id: id,
        user_id: user.id,
        content: newComment
      });
      if (res.data && res.data.success) {
        setNewComment('');
        fetchTicketDetails(); // Refresh to get new comment and history
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendComment();
    }
  };

  const handleStatusChange = async (status: string) => {
    try {
      await api.patch(`/tickets.php?id=${id}`, { status });
      fetchTicketDetails();
    } catch (err) {
      console.error(err);
    }
  };

  if (loading) return <div style={{ padding: '40px', textAlign: 'center' }}>Loading Ticket Details...</div>;
  if (!ticket) return <div style={{ padding: '40px', textAlign: 'center' }}>Ticket not found.</div>;

  return (
    <div className="ticket-detail-page">
      <div className="ticket-detail-header">
        <Link to="/tickets" className="back-link"><ArrowLeft size={18} /> Back to Tickets</Link>
        <div className="header-actions">
          {(user?.role === 'admin' || user?.role === 'dept_head' || user?.role === 'agent') && (
            <select 
              className="status-selector" 
              value={ticket.status}
              onChange={(e) => handleStatusChange(e.target.value)}
            >
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          )}
        </div>
      </div>

      <div className="ticket-detail-grid">
        <div className="ticket-main-col">
          <div className="ticket-card glass">
            <h2>{ticket.title}</h2>
            <div className="ticket-meta">
              <span className={`status-badge status-${ticket.status}`}>{ticket.status.toUpperCase()}</span>
              <span className={`priority-badge priority-${ticket.priority}`}>{ticket.priority.toUpperCase()}</span>
              <span><strong>ID:</strong> {ticket.ticket_number || ticket.id}</span>
              <span><strong>Created:</strong> {new Date(ticket.created_at).toLocaleString()}</span>
            </div>
            
            <div className="ticket-description">
              <h3>Description</h3>
              <p>{ticket.description}</p>
            </div>

            {ticket.custom_fields && ticket.custom_fields.length > 0 && (
              <div className="ticket-custom-fields">
                <h3>Department Specifics</h3>
                <div className="fields-grid">
                  {ticket.custom_fields.map((cf: any, idx: number) => (
                    <div key={idx} className="field-item">
                      <span className="field-label">{cf.field_label}:</span>
                      <span className="field-value">{cf.field_value}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {ticket.attachments && ticket.attachments.length > 0 && (
              <div className="ticket-attachments">
                <h3>Attachments</h3>
                <div className="attachments-list">
                  {ticket.attachments.map((att: any, idx: number) => (
                    <a key={idx} href={att.file_path} target="_blank" rel="noopener noreferrer" className="attachment-link">
                      <Paperclip size={16} /> {att.file_name}
                    </a>
                  ))}
                </div>
              </div>
            )}
          </div>

          <div className="chat-section glass">
            <h3><MessageSquare size={18} /> Discussion</h3>
            <div className="comments-list">
              {ticket.comments && ticket.comments.length > 0 ? (
                ticket.comments.map((comment: any) => (
                  <div className="comment-bubble" key={comment.id}>
                    {comment.avatar_url ? (
                      <img src={comment.avatar_url} alt="Avatar" className="comment-avatar" style={{ objectFit: 'cover' }} />
                    ) : (
                      <div className="comment-avatar">{comment.user_name ? comment.user_name.charAt(0).toUpperCase() : 'U'}</div>
                    )}
                    <div className="comment-content">
                      <div className="comment-meta">
                        <strong>{comment.user_name}</strong>
                        <span className="time">{new Date(comment.created_at).toLocaleString()}</span>
                      </div>
                      <p>{comment.content}</p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="no-comments">No comments yet. Start the conversation!</p>
              )}
            </div>

            {ticket.status !== 'closed' && (
              <div className="chat-input-area">
                <textarea placeholder="Type your message... (Press Enter to send)" value={newComment} onChange={(e) => setNewComment(e.target.value)} onKeyDown={handleKeyDown} />
                <button className="btn-primary" onClick={handleSendComment}>
                  <Send size={18} /> Send
                </button>
              </div>
            )}
          </div>
        </div>

        <div className="ticket-side-col">
          <div className="info-card glass">
            <h3>Ticket Info</h3>
            <div className="info-row">
              <span className="label">Department:</span>
              <span className="value">{ticket.department_name}</span>
            </div>
            <div className="info-row">
              <span className="label">Requester:</span>
              <span className="value">{ticket.creator_name}</span>
            </div>
            {ticket.queue_position && (
              <div className="info-row queue-highlight">
                <span className="label">Queue Position:</span>
                <span className="value">#{ticket.queue_position}</span>
              </div>
            )}
          </div>

          <div className="timeline-card glass">
            <h3><Clock size={18} /> History & Timeline</h3>
            <div className="timeline-list">
              {ticket.history && ticket.history.length > 0 ? (
                ticket.history.map((hist: any, idx: number) => (
                  <div className="timeline-item" key={idx}>
                    <div className="timeline-dot"></div>
                    <div className="timeline-content">
                      <p>{hist.action}</p>
                      <span className="time">{new Date(hist.created_at).toLocaleString()} - {hist.user_name}</span>
                    </div>
                  </div>
                ))
              ) : (
                <p className="no-history">No history recorded.</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

