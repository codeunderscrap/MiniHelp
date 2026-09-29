import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useAuthStore } from '../store';
import { Briefcase, CheckCircle2, UploadCloud, AlertCircle } from 'lucide-react';

export function CreateTicket() {
  const user = useAuthStore(state => state.user);
  const [departments, setDepartments] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  
  // Form State
  const [selectedDept, setSelectedDept] = useState('');
  const [title, setTitle] = useState('');
  const [priority, setPriority] = useState('low');
  const [category, setCategory] = useState('General');
  const [description, setDescription] = useState('');
  const [dynamicFields, setDynamicFields] = useState<any[]>([]);
  const [customValues, setCustomValues] = useState<Record<string, string>>({});
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadingFields, setLoadingFields] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/departments.php').then(res => {
      if (res.data?.success) setDepartments(res.data.data);
    });
  }, []);

  // Fetch dynamic fields when department changes
  useEffect(() => {
    if (!selectedDept) {
      setDynamicFields([]);
      setCategories([]);
      return;
    }
    const fetchFields = async () => {
      setLoadingFields(true);
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
        if (res.data?.success) {
          setDynamicFields(res.data.data);
        }
      } catch (err) {
        console.error("Failed to load dynamic fields", err);
      } finally {
        setLoadingFields(false);
      }
    };
    fetchFields();
  }, [selectedDept]);

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
        res = await api.post('/tickets.php', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
      } else {
        res = await api.post('/tickets.php', payload);
      }

      if (res.data?.success) {
        navigate('/tickets');
      } else {
        if (typeof res.data === 'string') { alert('Server Error: ' + res.data.substring(0, 100)); } else { alert('Error: ' + (res.data?.error || 'Unknown error')); }
      }
    } catch (err: any) {
      alert('Error creating ticket: ' + (err.response?.data?.error || err.message));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="mb-8 text-center sm:text-left">
        <h1 className="text-3xl font-bold text-[var(--text-primary)] mb-2">Create New Ticket</h1>
        <p className="text-[var(--text-secondary)]">Describe your issue and we'll get the right team on it.</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-8">
        
        {/* Department Selection */}
        <div className="bg-[var(--bg-secondary)] backdrop-blur-xl border border-[var(--border)] rounded-2xl p-6 shadow-xl">
          <h2 className="text-xl font-semibold text-[var(--text-primary)] mb-4 flex items-center gap-2">
            <Briefcase className="text-[var(--accent-primary)]" size={20} />
            Select Department
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {departments.map(dept => (
              <div 
                key={dept.id} 
                onClick={() => setSelectedDept(dept.id)}
                className={`relative flex items-center p-4 rounded-xl cursor-pointer transition-all duration-200 border-2 ${selectedDept === dept.id ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/10 shadow-lg shadow-[var(--accent-primary)]/10' : 'border-[var(--border)] bg-[var(--bg-tertiary)] hover:bg-[var(--bg-tertiary)] hover:border-[var(--border)]'}`}
              >
                <div className={`p-3 rounded-lg mr-4 ${selectedDept === dept.id ? 'bg-[var(--accent-primary)] text-[var(--bg-primary)]' : 'bg-[var(--bg-quaternary)] text-[var(--text-primary)]'}`}>
                  <Briefcase size={20} />
                </div>
                <div>
                  <h3 className="font-medium text-[var(--text-primary)]">{dept.name}</h3>
                  <p className="text-xs text-[var(--text-secondary)] line-clamp-1">{dept.description || 'General inquiries'}</p>
                </div>
                {selectedDept === dept.id && (
                  <CheckCircle2 className="absolute top-3 right-3 text-[var(--accent-primary)]" size={18} />
                )}
              </div>
            ))}
          </div>
        </div>

        {selectedDept && (
          <div className="bg-[var(--bg-secondary)] backdrop-blur-xl border border-[var(--border)] rounded-2xl p-6 shadow-xl space-y-6 animate-in fade-in slide-in-from-top-4 duration-500">
            <h2 className="text-xl font-semibold text-[var(--text-primary)] mb-4">Ticket Details</h2>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text-primary)] mb-1">Issue Title <span className="text-red-500">*</span></label>
              <input 
                type="text" 
                placeholder="Brief summary of the issue" 
                required 
                className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-xl px-4 py-3 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 focus:border-[var(--accent-primary)] transition-all"
                value={title}
                onChange={e => setTitle(e.target.value)}
              />
            </div>
            
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-[var(--text-primary)] mb-1">Priority</label>
                <select 
                  className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-xl px-4 py-3 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 transition-all appearance-none" 
                  value={priority} 
                  onChange={e => setPriority(e.target.value)}
                  style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2394a3b8\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundPosition: 'right 1rem center', backgroundRepeat: 'no-repeat', backgroundSize: '1.5em 1.5em' }}
                >
                  <option value="low">Low (Routine)</option>
                  <option value="medium">Medium (Impedes work)</option>
                  <option value="high">High (Urgent)</option>
                  <option value="critical">Critical (Blocker)</option>
                </select>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text-primary)] mb-1">Problem Type</label>
                <select 
                  className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-xl px-4 py-3 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 transition-all appearance-none" 
                  value={category} 
                  onChange={e => setCategory(e.target.value)}
                  style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2394a3b8\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundPosition: 'right 1rem center', backgroundRepeat: 'no-repeat', backgroundSize: '1.5em 1.5em' }}
                >
                  {categories.length > 0 ? categories.map(cat => (
                    <option key={cat.id || cat.name} value={cat.name}>{cat.name}</option>
                  )) : <option value="General">General</option>}
                </select>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-[var(--text-primary)] mb-1">Description <span className="text-red-500">*</span></label>
              <textarea 
                rows={5} 
                placeholder="Please provide as much detail as possible..." 
                required 
                className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-xl px-4 py-3 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 focus:border-[var(--accent-primary)] transition-all resize-y"
                value={description}
                onChange={e => setDescription(e.target.value)}
              ></textarea>
            </div>

            {loadingFields && (
              <div className="flex items-center text-[var(--accent-primary)] text-sm gap-2">
                <div className="w-4 h-4 rounded-full border-2 border-[var(--accent-primary)] border-t-transparent animate-spin"></div>
                Loading department specifics...
              </div>
            )}

            {!loadingFields && dynamicFields.length > 0 && (
              <div className="p-5 bg-[var(--bg-tertiary)] rounded-xl border border-[var(--border)] space-y-4">
                <h3 className="text-sm font-semibold text-[var(--accent-primary)] uppercase tracking-wider mb-2 flex items-center gap-2">
                  <AlertCircle size={16} /> Department Specific Questions
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                  {dynamicFields.map((field) => (
                    <div key={field.id} className="col-span-1">
                      <label className="block text-sm font-medium text-[var(--text-primary)] mb-1">
                        {field.field_label} {Boolean(field.is_required) && <span className="text-red-500">*</span>}
                      </label>
                      {field.field_type === 'dropdown' ? (
                        <select 
                          className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-lg px-4 py-2.5 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 appearance-none" 
                          required={field.is_required}
                          value={customValues[field.id] || ''}
                          onChange={(e) => setCustomValues({...customValues, [field.id]: e.target.value})}
                          style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2394a3b8\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundPosition: 'right 0.75rem center', backgroundRepeat: 'no-repeat', backgroundSize: '1.2em 1.2em' }}
                        >
                          <option value="">Select...</option>
                          {field.options && JSON.parse(field.options).map((opt: string) => (
                            <option key={opt} value={opt}>{opt}</option>
                          ))}
                        </select>
                      ) : field.field_type === 'textarea' ? (
                        <textarea 
                          className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-lg px-4 py-2.5 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50 resize-y" 
                          rows={2}
                          required={field.is_required}
                          value={customValues[field.id] || ''}
                          onChange={(e) => setCustomValues({...customValues, [field.id]: e.target.value})}
                        ></textarea>
                      ) : (
                        <input 
                          type="text" 
                          className="w-full bg-[var(--bg-tertiary)] border border-[var(--border)] rounded-lg px-4 py-2.5 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)]/50" 
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

            <div>
              <label className="block text-sm font-medium text-[var(--text-primary)] mb-2">Attachments (Optional)</label>
              <div 
                className="w-full border-2 border-dashed border-[var(--border-hover)] hover:border-[var(--accent-primary)] bg-[var(--bg-tertiary)] hover:bg-[var(--bg-quaternary)] transition-all rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer group"
                onClick={() => document.getElementById('file-upload')?.click()}
              >
                <div className="p-4 bg-[var(--bg-tertiary)] rounded-full group-hover:scale-110 transition-transform duration-200 mb-3">
                  <UploadCloud size={24} className="text-[var(--accent-primary)]" />
                </div>
                <p id="file-name-display" className="text-[var(--text-primary)] font-medium text-center">Click to browse or drag & drop</p>
                <p className="text-[var(--text-tertiary)] text-sm mt-1">Images, PDFs up to 10MB</p>
                <input 
                  type="file" 
                  id="file-upload" 
                  className="hidden" 
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    const display = document.getElementById('file-name-display');
                    if (display && file) {
                      display.innerText = file.name;
                      display.classList.add('text-[var(--accent-primary)]');
                    }
                  }}
                />
              </div>
            </div>

            <div className="pt-4 flex justify-end">
              <button 
                type="submit" 
                disabled={isSubmitting}
                className="bg-[var(--accent-primary)] hover:bg-[var(--accent-primary)] text-[var(--bg-primary)] font-medium px-8 py-3 rounded-xl transition-all shadow-lg shadow-lg active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center min-w-[150px]"
              >
                {isSubmitting ? (
                  <span className="flex items-center gap-2">
                    <div className="w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin"></div>
                    Submitting...
                  </span>
                ) : 'Submit Ticket'}
              </button>
            </div>
          </div>
        )}
      </form>
    </div>
  );
}





