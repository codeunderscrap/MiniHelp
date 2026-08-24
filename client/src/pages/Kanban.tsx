import { useState } from 'react';
import './Kanban.css';

const initialTickets = [
  { id: 'TKT-1024', title: 'Cannot access internal CRM', status: 'Open', priority: 'High' },
  { id: 'TKT-1025', title: 'Need access to Github', status: 'Open', priority: 'Medium' },
  { id: 'TKT-1023', title: 'Request for new software license', status: 'In Progress', priority: 'Medium' },
  { id: 'TKT-1022', title: 'VPN connection dropping', status: 'Resolved', priority: 'Critical' },
];

export function Kanban() {
  const [tickets, setTickets] = useState(initialTickets);
  const [draggedTicketId, setDraggedTicketId] = useState<string | null>(null);

  const columns = ['Open', 'In Progress', 'Resolved'];

  const handleDragStart = (e: React.DragEvent, id: string) => {
    setDraggedTicketId(id);
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  };

  const handleDrop = (e: React.DragEvent, status: string) => {
    e.preventDefault();
    if (draggedTicketId) {
      setTickets(tickets.map(t => 
        t.id === draggedTicketId ? { ...t, status } : t
      ));
      setDraggedTicketId(null);
    }
  };

  return (
    <div className="kanban-page">
      <div className="page-header">
        <h1>Kanban Board</h1>
        <p>Drag and drop tickets to update their status.</p>
      </div>

      <div className="kanban-board">
        {columns.map(col => (
          <div 
            key={col} 
            className="kanban-col glass"
            onDragOver={handleDragOver}
            onDrop={(e) => handleDrop(e, col)}
          >
            <div className="col-header">
              <h3>{col}</h3>
              <span className="col-count">
                {tickets.filter(t => t.status === col).length}
              </span>
            </div>
            <div className="col-content">
              {tickets.filter(t => t.status === col).map(ticket => (
                <div 
                  key={ticket.id} 
                  className="kanban-card"
                  draggable
                  onDragStart={(e) => handleDragStart(e, ticket.id)}
                >
                  <div className="card-top">
                    <span className="mono ticket-id">{ticket.id}</span>
                    <span className={`priority-indicator p-${ticket.priority.toLowerCase()}`}></span>
                  </div>
                  <h4>{ticket.title}</h4>
                  <div className="card-bottom">
                    <span className={`priority-badge priority-${ticket.priority.toLowerCase()}`}>
                      {ticket.priority}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
