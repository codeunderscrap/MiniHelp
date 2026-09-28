import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useAuthStore } from '../store';
import { UploadCloud, CheckCircle2, Briefcase } from 'lucide-react';
import { useEffect } from 'react';
import './CreateTicket.css';



export function CreateTicket() {
  const user = useAuthStore(state => state.user);
  const [step, setStep] = useState(1);
  const [selectedDept, setSelectedDept] = useState('');
  const [departments, setDepartments] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  
  // Form State
  const [title, setTitle] = useState('');
  const [priority, setPriority] = useState('low');
  const [category, setCategory] = useState('software');
  const [description, setDescription] = useState('');
  const [dynamicFields, setDynamicFields] = useState<any[]>([]);
  const [customValues, setCustomValues] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/departments.php').then(res => {
      if (res.data?.success) setDepartments(res.data.data);
    });
  }, []);


  const handleNextStep = async () => {
    if (!selectedDept) return;
    try {
      const res = await api.get(`/fields.php?department_id=${selectedDept}`);
      const catRes = await api.get(`/categories.php?department_id=${selectedDept}`);
      if (catRes.data?.success) {
        setCategories(catRes.data.data);
        if (catRes.data.data.length > 0) {
          setCategory(catRes.data.data[0].name);
        } else {
          setCategory('General');
        }
      }
      if (res.data && res.data.success) {
        setDynamicFields(res.data.data);
      }
    } catch (err) {
      console.error("Failed to load dynamic fields", err);
    }
    setStep(2);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDept || !title || !description || !user?.id) return;
    
    setIsSubmitting(true);
    try {
      const payload = {
        title,
        description,
        priority,
        category,
        department_id: selectedDept,
        creator_id: user.id,
        custom_values: customValues
      };

      const fileInput = document.getElementById('file-upload') as HTMLInputElement;
      const file = fileInput?.files?.[0];

      let res;
      if (file) {
        const formData = new FormData();
        formData.append('data', JSON.stringify(payload));
        formData.append('attachment', file);
        // By setting Content-Type to undefined, Axios will automatically figure out it's FormData 
        // and set the correct multipart boundary. We must delete the default application/json.
        res = await api.post('/tickets.php', formData, {
          headers: { 'Content-Type': undefined }
        });
      } else {
        res = await api.post('/tickets.php', payload);
      }

      if (res.data && res.data.success) {
        alert('Ticket created successfully!');
        window.dispatchEvent(new CustomEvent('refresh-notifications'));
          navigate('/tickets');
      } else {
        alert('Error: ' + res.data.error);
      }
    } catch (err: any) {
      alert('Error creating ticket: ' + (err.response?.data?.error || err.message));
    } finally {
      setIsSubmitting(false);
    }
  };

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
                {departments.map(dept => (
                  <div 
                    key={dept.id} 
                    className={`dept-card ${selectedDept === dept.id ? 'selected' : ''}`}
                    onClick={() => setSelectedDept(dept.id)}
                  >
                    <div className="dept-icon"><Briefcase size={20} strokeWidth={1.5} /></div>
                    <h3>{dept.name}</h3>
                    <p>{dept.description || 'General inquiries'}</p>
                    {selectedDept === dept.id && <CheckCircle2 size={20} strokeWidth={1.5} className="check-icon" />}
                  </div>
                ))}
              </div>

            <div className="wizard-actions">
              <button 
                className="btn-primary" 
                disabled={!selectedDept}
                onClick={handleNextStep}
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

            <form className="ticket-form" onSubmit={handleSubmit}>
              <div className="form-group">
                <label>Issue Title *</label>
                <input 
                  type="text" 
                  placeholder="Brief summary of the issue" 
                  required 
                  className="form-input"
                  value={title}
                  onChange={e => setTitle(e.target.value)}
                />
              </div>
              
              <div className="form-row">
                <div className="form-group">
                  <label>Priority</label>
                  <select className="form-input" value={priority} onChange={e => setPriority(e.target.value)}>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Category</label>
                  <select className="form-input" value={category} onChange={e => setCategory(e.target.value)}>{categories.length > 0 ? categories.map(cat => (<option key={cat.id || cat.name} value={cat.name}>{cat.name}</option>)) : <option value="General">General</option>}</select>
                </div>
              </div>

              <div className="form-group">
                <label>Description *</label>
                <textarea 
                  rows={6} 
                  placeholder="Detailed explanation of the issue..." 
                  required 
                  className="form-input"
                  value={description}
                  onChange={e => setDescription(e.target.value)}
                ></textarea>
              </div>

              {dynamicFields.length > 0 && (
                <div className="dynamic-fields-section" style={{ padding: '20px', background: 'var(--bg-secondary)', borderRadius: '8px', border: '1px solid var(--border)' }}>
                  <h3 style={{ margin: '0 0 16px 0', fontSize: '1rem' }}>Department Specific Details</h3>
                  <div className="form-row" style={{ flexWrap: 'wrap' }}>
                    {dynamicFields.map((field) => (
                      <div className="form-group" key={field.id} style={{ minWidth: '45%' }}>
                        <label>{field.field_label} {field.is_required ? '*' : ''}</label>
                        {field.field_type === 'dropdown' ? (
                          <select 
                            className="form-input" 
                            required={field.is_required}
                            value={customValues[field.id] || ''}
                            onChange={(e) => setCustomValues({...customValues, [field.id]: e.target.value})}
                          >
                            <option value="">Select...</option>
                            {field.options && JSON.parse(field.options).map((opt: string) => (
                              <option key={opt} value={opt}>{opt}</option>
                            ))}
                          </select>
                        ) : field.field_type === 'textarea' ? (
                          <textarea 
                            className="form-input" 
                            rows={3}
                            required={field.is_required}
                            value={customValues[field.id] || ''}
                            onChange={(e) => setCustomValues({...customValues, [field.id]: e.target.value})}
                          ></textarea>
                        ) : (
                          <input 
                            type="text" 
                            className="form-input" 
                            required={field.is_required}
                            value={customValues[field.id] || ''}
                            onChange={(e) => setCustomValues({...customValues, [field.id]: e.target.value})}
                          />
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="form-group">
                <label>Attachments</label>
                <div 
                  className="file-upload-zone" 
                  onClick={() => document.getElementById('file-upload')?.click()}
                  style={{ cursor: 'pointer' }}
                >
                  <UploadCloud size={20} strokeWidth={1.5} />
                  <p id="file-name-display">Drag & drop files here, or click to select</p>
                  <small>Max file size: 10MB</small>
                  <input 
                    type="file" 
                    id="file-upload" 
                    style={{ display: 'none' }} 
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      const display = document.getElementById('file-name-display');
                      if (display && file) {
                        display.innerText = file.name;
                      }
                    }}
                  />
                </div>
              </div>

              <div className="wizard-actions">
                <button type="button" className="btn-secondary" onClick={() => setStep(1)}>Back</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>
                  {isSubmitting ? 'Submitting...' : 'Submit Ticket'}
                </button>
              </div>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}


