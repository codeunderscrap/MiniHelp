import { useState } from 'react';
import { UploadCloud, CheckCircle2 } from 'lucide-react';
import './CreateTicket.css';

const DEPARTMENTS = [
  { id: 'it', name: 'IT Support', icon: '💻', desc: 'Hardware, software, network' },
  { id: 'hr', name: 'Human Resources', icon: '👥', desc: 'Payroll, benefits, policies' },
  { id: 'facilities', name: 'Facilities', icon: '🏢', desc: 'Building, maintenance' },
  { id: 'finance', name: 'Finance', icon: '💰', desc: 'Expenses, billing' }
];

export function CreateTicket() {
  const [step, setStep] = useState(1);
  const [selectedDept, setSelectedDept] = useState('');

  return (
    <div className="create-ticket">
      <div className="wizard-header">
        <h1>Create New Ticket</h1>
        <div className="steps-indicator">
          <div className={`step ${step >= 1 ? 'active' : ''}`}>
            <div className="step-circle">1</div>
            <span>Department</span>
          </div>
          <div className={`step-line ${step >= 2 ? 'active' : ''}`}></div>
          <div className={`step ${step >= 2 ? 'active' : ''}`}>
            <div className="step-circle">2</div>
            <span>Details</span>
          </div>
        </div>
      </div>

      <div className="wizard-content glass">
        {step === 1 && (
          <div className="step-1">
            <h2>Select Department</h2>
            <p>Which team can help you with your issue?</p>
            
            <div className="dept-grid">
              {DEPARTMENTS.map(dept => (
                <div 
                  key={dept.id} 
                  className={`dept-card ${selectedDept === dept.id ? 'selected' : ''}`}
                  onClick={() => setSelectedDept(dept.id)}
                >
                  <div className="dept-icon">{dept.icon}</div>
                  <h3>{dept.name}</h3>
                  <p>{dept.desc}</p>
                  {selectedDept === dept.id && <CheckCircle2 className="check-icon" />}
                </div>
              ))}
            </div>

            <div className="wizard-actions">
              <button 
                className="btn-primary" 
                disabled={!selectedDept}
                onClick={() => setStep(2)}
              >
                Next Step
              </button>
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="step-2">
            <h2>Ticket Details</h2>
            <p>Provide as much information as possible.</p>

            <form className="ticket-form" onSubmit={(e) => { e.preventDefault(); alert('Ticket created!'); }}>
              <div className="form-group">
                <label>Issue Title *</label>
                <input type="text" placeholder="Brief summary of the issue" required className="form-input" />
              </div>
              
              <div className="form-row">
                <div className="form-group">
                  <label>Priority</label>
                  <select className="form-input">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Category</label>
                  <select className="form-input">
                    <option value="software">Software</option>
                    <option value="hardware">Hardware</option>
                    <option value="access">Access/Permissions</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>

              <div className="form-group">
                <label>Description *</label>
                <textarea rows={6} placeholder="Detailed explanation of the issue..." required className="form-input"></textarea>
              </div>

              <div className="form-group">
                <label>Attachments</label>
                <div className="file-upload-zone">
                  <UploadCloud size={32} />
                  <p>Drag & drop files here, or click to select</p>
                  <small>Max file size: 10MB</small>
                </div>
              </div>

              <div className="wizard-actions">
                <button type="button" className="btn-secondary" onClick={() => setStep(1)}>Back</button>
                <button type="submit" className="btn-primary">Submit Ticket</button>
              </div>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}
