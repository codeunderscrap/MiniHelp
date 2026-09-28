import React, { useEffect, useState, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api';
import { useAuthStore } from '../store';
import { ArrowLeft, Clock, MessageSquare, Send, Paperclip, Info, ShieldAlert, CheckCircle, Tag } from 'lucide-react';
import './TicketDetail.css';

export function TicketDetail() {
  const { id } = useParams();
  const user = useAuthStore(state => state.user);
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [newComment, setNewComment] = useState('');
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
    if (!newComment.trim() || !user) return;
    try {
      const res = await api.post('/comments.php', {
        ticket_id: id,
        user_id: user.id,
        content: newComment
      });
      if (res.data && res.data.success) {
        setNewComment('');
        fetchTicketDetails();
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

  if (loading) return (
    <div className="flex-1 flex items-center justify-center h-full">
      <div className="flex flex-col items-center gap-3">
        <div className="w-8 h-8 rounded-full border-4 border-emerald-500 border-t-transparent animate-spin"></div>
        <p className="text-slate-400">Loading Ticket Details...</p>
      </div>
    </div>
  );
  if (!ticket) return <div className="p-10 text-center text-slate-400">Ticket not found.</div>;

  const isResolver = user?.role === 'admin' || user?.role === 'dept_head' || user?.role === 'agent';
  const getStatusColor = (status: string) => {
    switch(status) {
      case 'open': return 'bg-sky-500/20 text-sky-400 border-sky-500/30';
      case 'in_progress': return 'bg-amber-500/20 text-amber-400 border-amber-500/30';
      case 'resolved': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
      case 'closed': return 'bg-slate-500/20 text-slate-400 border-slate-500/30';
      default: return 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    }
  };
  const getPriorityColor = (priority: string) => {
    switch(priority) {
      case 'low': return 'bg-slate-500/20 text-slate-400';
      case 'medium': return 'bg-sky-500/20 text-sky-400';
      case 'high': return 'bg-amber-500/20 text-amber-400';
      case 'critical': return 'bg-red-500/20 text-red-400';
      default: return 'bg-slate-500/20 text-slate-400';
    }
  };

  return (
    <div className="flex flex-col h-[calc(100vh-60px)] md:h-[calc(100vh-72px)] -m-4 sm:-m-8 bg-[#0b141a]">
      {/* Top Navbar for Chat */}
      <div className="h-16 bg-[#202c33] border-b border-[#2f3b43] px-4 flex items-center justify-between shrink-0 z-10 shadow-sm">
        <div className="flex items-center gap-3">
          <Link to="/tickets" className="p-2 -ml-2 rounded-full hover:bg-white/5 text-slate-300 transition-colors">
            <ArrowLeft size={20} />
          </Link>
          <div className="flex flex-col">
            <h1 className="text-white font-semibold text-[15px] leading-tight line-clamp-1">{ticket.title}</h1>
            <span className="text-slate-400 text-xs">#{ticket.ticket_number || ticket.id} • {ticket.department_name}</span>
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
        <div className="w-full md:w-[350px] lg:w-[400px] shrink-0 bg-[#111b21] border-b md:border-b-0 md:border-r border-[#2f3b43] overflow-y-auto flex flex-col hide-scrollbar max-h-[30vh] md:max-h-full">
          
          <div className="p-5 border-b border-[#2f3b43]">
            <h2 className="text-lg text-white font-medium mb-3">Ticket Information</h2>
            <div className="flex flex-wrap gap-2 mb-4">
              <span className={`text-xs px-2.5 py-1 rounded-md font-medium ${getPriorityColor(ticket.priority)} flex items-center gap-1`}>
                <ShieldAlert size={14} /> {ticket.priority.toUpperCase()} Priority
              </span>
              <span className="text-xs px-2.5 py-1 rounded-md font-medium bg-slate-800 text-slate-300 flex items-center gap-1">
                <Tag size={14} /> {ticket.category}
              </span>
            </div>
            
            <div className="space-y-4">
              <div>
                <span className="text-xs text-slate-400 block mb-1">Description</span>
                <p className="text-sm text-slate-200 bg-[#202c33] p-3 rounded-lg leading-relaxed whitespace-pre-wrap">{ticket.description}</p>
              </div>

              {ticket.custom_fields && ticket.custom_fields.length > 0 && (
                <div className="bg-[#202c33] p-3 rounded-lg space-y-2">
                  <span className="text-xs text-emerald-500 font-semibold uppercase tracking-wider block mb-2">Specifics</span>
                  {ticket.custom_fields.map((cf: any, idx: number) => (
                    <div key={idx} className="flex flex-col">
                      <span className="text-xs text-slate-400">{cf.field_label}</span>
                      <span className="text-sm text-slate-200 font-medium">{cf.field_value}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="p-5 border-b border-[#2f3b43]">
            <h3 className="text-sm text-slate-400 mb-3 flex items-center gap-2"><Info size={16} /> Details</h3>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-400">Requester</span>
                <span className="text-sm text-white font-medium">{ticket.creator_name}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-400">Assignee</span>
                <span className="text-sm text-white font-medium">{ticket.assignee_name || <span className="text-slate-500 italic">Unassigned</span>}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-400">Created</span>
                <span className="text-sm text-slate-300">{new Date(ticket.created_at).toLocaleDateString()}</span>
              </div>
            </div>
          </div>

          {ticket.attachments && ticket.attachments.length > 0 && (
            <div className="p-5 border-b border-[#2f3b43]">
              <h3 className="text-sm text-slate-400 mb-3 flex items-center gap-2"><Paperclip size={16} /> Attachments</h3>
              <div className="space-y-2">
                {ticket.attachments.map((att: any, idx: number) => (
                  <a key={idx} href={att.file_path} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 p-2 rounded-md bg-[#202c33] hover:bg-[#2a3942] transition-colors text-sm text-emerald-400 line-clamp-1">
                    <Paperclip size={14} className="shrink-0" /> {att.file_name}
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Right Side: Chat Window */}
        <div className="flex-1 flex flex-col bg-[#0b141a] relative" style={{ backgroundImage: 'url("https://web.whatsapp.com/img/bg-chat-tile-dark_a4be512e7195b6b733d9110b408f075d.png")', opacity: 0.95, backgroundRepeat: 'repeat' }}>
          
          <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            {/* System Start Message */}
            <div className="flex justify-center mb-6">
              <div className="bg-[#182229] text-[#8696a0] text-xs px-4 py-1.5 rounded-full shadow-sm text-center">
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
                        <span className="text-xs text-slate-400 ml-1 mb-1 block">{comment.user_name}</span>
                      )}
                      <div className={`relative px-3 sm:px-4 py-2 sm:py-2.5 shadow-sm text-[15px] leading-relaxed break-words
                        ${isMe 
                          ? 'bg-[#005c4b] text-[#e9edef] rounded-l-xl rounded-tr-xl rounded-br-sm' 
                          : 'bg-[#202c33] text-[#e9edef] rounded-r-xl rounded-tl-xl rounded-bl-sm'}
                      `}>
                        {comment.content}
                        <span className="float-right mt-2 ml-4 text-[10px] text-white/50 flex items-center">
                          {new Date(comment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          {isMe && <CheckCircle size={12} className="ml-1 text-sky-400" />}
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })
            ) : (
              <div className="flex flex-col items-center justify-center h-[50%] text-center opacity-60">
                <div className="w-16 h-16 bg-[#202c33] rounded-full flex items-center justify-center mb-4">
                  <MessageSquare size={32} className="text-[#8696a0]" />
                </div>
                <p className="text-[#8696a0] text-sm">No discussion yet.<br/>Type a message below to start.</p>
              </div>
            )}
            <div ref={chatEndRef} />
          </div>

          {/* Bottom Chat Input */}
          {ticket.status !== 'closed' ? (
            <div className="bg-[#202c33] p-3 sm:px-4 flex items-end gap-2 shrink-0 z-10 w-full">
              <div className="flex-1 bg-[#2a3942] rounded-xl flex items-end">
                <textarea 
                  className="w-full bg-transparent text-white px-4 py-3 max-h-[120px] min-h-[44px] focus:outline-none resize-none hide-scrollbar placeholder:text-[#8696a0]"
                  placeholder="Type a message (Press Enter)" 
                  value={newComment}
                  onChange={(e) => {
                    setNewComment(e.target.value);
                    e.target.style.height = 'auto';
                    e.target.style.height = (e.target.scrollHeight) + 'px';
                  }}
                  onKeyDown={handleKeyDown}
                  rows={1}
                />
              </div>
              <button 
                className={`p-3 rounded-full flex items-center justify-center shrink-0 transition-all ${newComment.trim() ? 'bg-[#00a884] text-white' : 'bg-transparent text-[#8696a0] pointer-events-none'}`}
                onClick={handleSendComment}
              >
                <Send size={20} className={newComment.trim() ? 'ml-1' : ''} />
              </button>
            </div>
          ) : (
            <div className="bg-[#202c33] p-4 text-center border-t border-[#2f3b43]">
              <p className="text-[#8696a0] text-sm flex items-center justify-center gap-2">
                <ShieldAlert size={16} /> This ticket is closed. Discussion is locked.
              </p>
            </div>
          )}

        </div>
      </div>
    </div>
  );
}
