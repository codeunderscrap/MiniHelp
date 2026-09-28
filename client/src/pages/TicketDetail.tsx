import React, { useEffect, useState, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api';
import { useAuthStore } from '../store';
import { ArrowLeft, Clock, MessageSquare, Send, Paperclip, Info, ShieldAlert, CheckCircle, Tag } from 'lucide-react';

export function TicketDetail() {
  const { id } = useParams();
  const user = useAuthStore(state => state.user);
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [newComment, setNewComment] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const chatEndRef = useRef<HTMLDivElement>(null);

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
    const interval = setInterval(fetchTicketDetails, 5000);
    return () => clearInterval(interval);
  }, [id]);

  useEffect(() => {
    if (chatEndRef.current) {
      chatEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [ticket?.comments]); // Scroll on new comment

    const handleSendComment = async () => {
    if ((!newComment.trim() && !attachment) || !user || isSubmitting) return;
    setIsSubmitting(true);
    try {
      let res;
      if (attachment) {
        const formData = new FormData();
        formData.append('ticket_id', id || '');
        formData.append('content', newComment);
        formData.append('attachment', attachment);
        res = await api.post('/comments.php', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
      } else {
        res = await api.post('/comments.php', {
          ticket_id: id,
          user_id: user.id,
          content: newComment
        });
      }
      
      if (res.data && res.data.success) {
        setNewComment('');
        setAttachment(null);
        fetchTicketDetails();
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderCommentContent = (text: string) => {
    const fileRegex = /\[FILE:\/\/(.*?)\|(.*?)\]/g;
    const match = fileRegex.exec(text);
    if (match) {
      const url = match[1];
      const name = match[2];
      const cleanText = text.replace(fileRegex, '').trim();
      const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(name);
      
      return (
        <div className="flex flex-col gap-2">
          {cleanText && <span>{cleanText}</span>}
          {isImage ? (
            <a href={url} target="_blank" rel="noopener noreferrer" className="block mt-1">
              <img src={url} alt={name} className="max-w-full h-auto max-h-48 rounded-md border border-white/10" />
            </a>
          ) : (
            <a href={url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 p-2 bg-black/10 rounded-md text-sm underline mt-1">
              <Paperclip size={14} /> {name}
            </a>
          )}
        </div>
      );
    }
    return <span>{text}</span>;
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

  if (loading) return (
    <div className="flex-1 flex items-center justify-center h-full">
      <div className="flex flex-col items-center gap-3">
        <div className="w-8 h-8 rounded-full border-4 border-emerald-500 border-t-transparent animate-spin"></div>
        <p className="text-[var(--text-secondary)]">Loading Ticket Details...</p>
      </div>
    </div>
  );
  if (!ticket) return <div className="p-10 text-center text-[var(--text-secondary)]">Ticket not found.</div>;

  const isResolver = user?.role === 'admin' || user?.role === 'dept_head' || user?.role === 'agent';
  const getStatusColor = (status: string) => {
    switch(status) {
      case 'open': return 'bg-sky-500/20 text-black border-sky-500/30';
      case 'in_progress': return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
      case 'resolved': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
      case 'closed': return 'bg-slate-500/20 text-[var(--text-secondary)] border-slate-500/30';
      default: return 'bg-slate-500/20 text-[var(--text-secondary)] border-slate-500/30';
    }
  };
  const getPriorityColor = (priority: string) => {
    switch(priority) {
      case 'low': return 'bg-slate-500/20 text-[var(--text-secondary)]';
      case 'medium': return 'bg-sky-500/20 text-black';
      case 'high': return 'bg-amber-500/20 text-amber-400';
      case 'critical': return 'bg-red-500/20 text-red-400';
      default: return 'bg-slate-500/20 text-[var(--text-secondary)]';
    }
  };

  return (
    <div className="flex flex-col h-[calc(100vh-60px)] md:h-[calc(100vh-72px)] -m-4 sm:-m-8 bg-[var(--bg-primary)]">
      {/* Top Navbar for Chat */}
      <div className="h-16 bg-[var(--bg-secondary)] border-b border-[var(--border)] px-4 flex items-center justify-between shrink-0 z-10 shadow-sm">
        <div className="flex items-center gap-3">
          <Link to="/tickets" className="p-2 -ml-2 rounded-full hover:bg-white/5 text-[var(--text-primary)] transition-colors">
            <ArrowLeft size={20} />
          </Link>
          <div className="flex flex-col">
            <h1 className="text-[var(--text-primary)] font-semibold text-[15px] leading-tight line-clamp-1">{ticket.title}</h1>
            <span className="text-[var(--text-secondary)] text-xs">#{ticket.ticket_number || ticket.id} â€¢ {ticket.department_name}</span>
          </div>
        </div>
        <div className="flex items-center">
          {isResolver ? (
            <select 
              className={`text-xs font-semibold px-3 py-1.5 rounded-full border appearance-none outline-none cursor-pointer ${getStatusColor(ticket.status)}`}
              value={ticket.status}
              onChange={(e) => handleStatusChange(e.target.value)}
            >
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          ) : (
            <span className={`text-xs font-semibold px-3 py-1 rounded-full border ${getStatusColor(ticket.status)}`}>
              {ticket.status.toUpperCase()}
            </span>
          )}
        </div>
      </div>

      <div className="flex flex-1 flex-col md:flex-row overflow-hidden relative">
        {/* Left Side: Ticket Details (Desktop) / Collapsed Info (Mobile) */}
        <div className="w-full md:w-[350px] lg:w-[400px] shrink-0 bg-[var(--bg-secondary)] border-b md:border-b-0 md:border-r border-[var(--border)] overflow-y-auto flex flex-col hide-scrollbar max-h-[30vh] md:max-h-full">
          
          <div className="p-5 border-b border-[var(--border)]">
            <h2 className="text-lg text-[var(--text-primary)] font-medium mb-3">Ticket Information</h2>
            <div className="flex flex-wrap gap-2 mb-4">
              <span className={`text-xs px-2.5 py-1 rounded-md font-medium ${getPriorityColor(ticket.priority)} flex items-center gap-1`}>
                <ShieldAlert size={14} /> {ticket.priority.toUpperCase()} Priority
              </span>
              <span className="text-xs px-2.5 py-1 rounded-md font-medium bg-[var(--bg-tertiary)] text-[var(--text-primary)] flex items-center gap-1">
                <Tag size={14} /> {ticket.category}
              </span>
            </div>
            
            <div className="space-y-4">
              <div>
                <span className="text-xs text-[var(--text-secondary)] block mb-1">Description</span>
                <p className="text-sm text-[var(--text-primary)] bg-[var(--bg-secondary)] p-3 rounded-lg leading-relaxed whitespace-pre-wrap">{ticket.description}</p>
              </div>

              {ticket.custom_fields && ticket.custom_fields.length > 0 && (
                <div className="bg-[var(--bg-secondary)] p-3 rounded-lg space-y-2">
                  <span className="text-xs text-emerald-500 font-semibold uppercase tracking-wider block mb-2">Specifics</span>
                  {ticket.custom_fields.map((cf: any, idx: number) => (
                    <div key={idx} className="flex flex-col">
                      <span className="text-xs text-[var(--text-secondary)]">{cf.field_label}</span>
                      <span className="text-sm text-[var(--text-primary)] font-medium">{cf.field_value}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="p-5 border-b border-[var(--border)]">
            <h3 className="text-sm text-[var(--text-secondary)] mb-3 flex items-center gap-2"><Info size={16} /> Details</h3>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm text-[var(--text-secondary)]">Requester</span>
                <span className="text-sm text-[var(--text-primary)] font-medium">{ticket.creator_name}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-[var(--text-secondary)]">Assignee</span>
                <span className="text-sm text-[var(--text-primary)] font-medium">{ticket.assignee_name || <span className="text-[var(--text-tertiary)] italic">Unassigned</span>}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-[var(--text-secondary)]">Created</span>
                <span className="text-sm text-[var(--text-primary)]">{new Date(ticket.created_at).toLocaleDateString()}</span>
              </div>
            </div>
          </div>

          {ticket.attachments && ticket.attachments.length > 0 && (
            <div className="p-5 border-b border-[var(--border)]">
              <h3 className="text-sm text-[var(--text-secondary)] mb-3 flex items-center gap-2"><Paperclip size={16} /> Attachments</h3>
              <div className="space-y-2">
                {ticket.attachments.map((att: any, idx: number) => (
                  <a key={idx} href={att.file_path} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 p-2 rounded-md bg-[var(--bg-secondary)] hover:bg-[var(--bg-tertiary)] transition-colors text-sm text-emerald-400 line-clamp-1">
                    <Paperclip size={14} className="shrink-0" /> {att.file_name}
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Right Side: Chat Window */}
        <div className="flex-1 flex flex-col bg-[var(--bg-primary)] relative" >
          
          <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            {/* System Start Message */}
            <div className="flex justify-center mb-6">
              <div className="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] text-xs px-4 py-1.5 rounded-full shadow-sm text-center">
                Ticket created on {new Date(ticket.created_at).toLocaleString()}
              </div>
            </div>

            {ticket.comments && ticket.comments.length > 0 ? (
              ticket.comments.map((comment: any) => {
                const isMe = comment.user_id === user?.id;
                return (
                  <div className={`flex flex-col ${isMe ? 'items-end' : 'items-start'}`} key={comment.id}>
                    <div className="max-w-[85%] sm:max-w-[70%] group">
                      {!isMe && (
                        <span className="text-xs text-[var(--text-secondary)] ml-1 mb-1 block">{comment.user_name}</span>
                      )}
                      <div className={`relative px-3 sm:px-4 py-2 sm:py-2.5 shadow-sm text-[15px] leading-relaxed break-words
                        ${isMe 
                          ? 'bg-[var(--accent-primary)] text-[var(--bg-primary)] rounded-l-xl rounded-tr-xl rounded-br-sm' 
                          : 'bg-[var(--bg-secondary)] text-[var(--text-primary)] rounded-r-xl rounded-tl-xl rounded-bl-sm'}
                      `}>
                        {renderCommentContent(comment.content)}
                        <span className="float-right mt-2 ml-4 text-[10px] text-[var(--text-primary)]/50 flex items-center">
                          {new Date(comment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          {isMe && <CheckCircle size={12} className="ml-1 text-black" />}
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })
            ) : (
              <div className="flex flex-col items-center justify-center h-[50%] text-center opacity-60">
                <div className="w-16 h-16 bg-[var(--bg-secondary)] rounded-full flex items-center justify-center mb-4">
                  <MessageSquare size={32} className="text-[var(--text-secondary)]" />
                </div>
                <p className="text-[var(--text-secondary)] text-sm">No discussion yet.<br/>Type a message below to start.</p>
              </div>
            )}
            <div ref={chatEndRef} />
          </div>

                      {/* Bottom Chat Input */}
            {ticket.status !== 'closed' ? (
              <div className="bg-[var(--bg-secondary)] p-3 sm:px-4 flex items-end gap-2 shrink-0 z-10 w-full relative">
                
                {attachment && (
                  <div className="absolute bottom-[100%] left-4 bg-[var(--bg-tertiary)] border border-[var(--border)] p-2 rounded-t-lg text-sm flex items-center gap-2 mb-1">
                    <Paperclip size={14} className="text-[var(--accent-primary)]" />
                    <span className="truncate max-w-[200px] text-[var(--text-primary)]">{attachment.name}</span>
                    <button onClick={() => setAttachment(null)} className="text-red-400 hover:text-red-500 ml-2">×</button>
                  </div>
                )}

                <label className="p-3 cursor-pointer text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors shrink-0">
                  <Paperclip size={20} />
                  <input type="file" className="hidden" onChange={(e) => { if(e.target.files && e.target.files[0]) setAttachment(e.target.files[0]); e.target.value = ''; }} />
                </label>

                <div className="flex-1 bg-[var(--bg-tertiary)] rounded-xl flex items-end">
                  <textarea 
                    className="w-full bg-transparent text-[var(--text-primary)] px-4 py-3 max-h-[120px] min-h-[44px] focus:outline-none resize-none hide-scrollbar placeholder:text-[var(--text-secondary)]"
                    placeholder="Type a message (Press Enter)" 
                    value={newComment}
                    onChange={(e) => {
                      setNewComment(e.target.value);
                      e.target.style.height = 'auto';
                      e.target.style.height = (e.target.scrollHeight) + 'px';
                    }}
                    onKeyDown={handleKeyDown}
                    rows={1}
                    disabled={isSubmitting}
                  />
                </div>
                <button 
  className={`p-3 rounded-full flex items-center justify-center shrink-0 transition-all ${(newComment.trim() || attachment) && !isSubmitting ? 'bg-[#00a884] text-[var(--bg-primary)] cursor-pointer' : 'bg-transparent text-[var(--text-secondary)] pointer-events-none'}`}
  onClick={handleSendComment}
  disabled={isSubmitting}
>
  {isSubmitting ? <div className="w-5 h-5 rounded-full border-2 border-white border-t-transparent animate-spin"></div> : <Send size={20} className={(newComment.trim() || attachment) ? 'ml-1' : ''} />}
</button>
              </div>
          ) : (
            <div className="bg-[var(--bg-secondary)] p-4 text-center border-t border-[var(--border)]">
              <p className="text-[var(--text-secondary)] text-sm flex items-center justify-center gap-2">
                <ShieldAlert size={16} /> This ticket is closed. Discussion is locked.
              </p>
            </div>
          )}

        </div>
      </div>
    </div>
  );
}








