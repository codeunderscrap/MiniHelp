import { useState, useEffect } from 'react';
import { api } from '../api';
import './Settings.css';
import { X, Trash2, Edit } from 'lucide-react';

interface Department {
  id: string;
  name: string;
  code?: string;
  description: string;
  notification_emails?: string | null;
}

interface Category { id: string; name: string; department_id: string; department_name?: string; }

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  department: string;
  department_id?: string;
}

export function Settings() {
  const [activeTab, setActiveTab] = useState('general');
  const [departments, setDepartments] = useState<Department[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isCategoryModalOpen, setIsCategoryModalOpen] = useState(false);
  const [editingCategoryId, setEditingCategoryId] = useState<string | null>(null);
  const [categoryForm, setCategoryForm] = useState({ name: "", department_id: "" });
  const [loading, setLoading] = useState(true);

  // User state
  const [isUserModalOpen, setIsUserModalOpen] = useState(false);
  const [editingUserId, setEditingUserId] = useState<string | null>(null);
  const [userForm, setUserForm] = useState({
    name: '', email: '', password: '', role: 'employee', department_id: ''
  });

  // Department state
  const [isDeptModalOpen, setIsDeptModalOpen] = useState(false);
  const [editingDeptId, setEditingDeptId] = useState<string | null>(null);
  const [deptForm, setDeptForm] = useState({
    name: '', code: '', description: '', notification_emails: ''
  });

  // Notifications & Automation state
  const [advancedSettings, setAdvancedSettings] = useState({
    sound_low: '/notification.mp3',
    sound_medium: '/notification.mp3',
    sound_high: '/notification.mp3',
    sound_critical: '/notification.mp3',
    sla_low: 48,
    sla_medium: 24,
    sla_high: 8,
    sla_critical: 2,
    rules: [] as any[]
  });

  const [isSubmitting, setIsSubmitting] = useState(false);

  const fetchDepts = async () => {
    try {
      const res = await api.get('/departments.php');
      if (res.data?.success) setDepartments(res.data.data);
    } catch (err) {}
  };

  const fetchCategories = async () => {
    try {
      const res = await api.get('/categories.php');
      if (res.data?.success) setCategories(res.data.data);
    } catch (err) {}
  };
  
  const fetchUsers = async () => {
    try {
      const res = await api.get('/users.php');
      if (res.data?.success) setUsers(res.data.data);
    } catch (err) {}
  };

    // Fields state
  const [fields, setFields] = useState<any[]>([]);
  const [selectedDeptForFields, setSelectedDeptForFields] = useState<string>('');
  const [isFieldModalOpen, setIsFieldModalOpen] = useState(false);
  const [editingFieldId, setEditingFieldId] = useState<string | null>(null);
  const emptyFieldForm = {
    field_label: '', field_type: 'text', is_required: 0, options: '',
    help_text: '', sort_order: '', show_if_field_id: '', show_if_value: ''
  };
  const [fieldForm, setFieldForm] = useState(emptyFieldForm);

  const parseFieldOptions = (f: any): string[] => {
    try { const o = f && f.options ? JSON.parse(f.options) : []; return Array.isArray(o) ? o : []; } catch { return []; }
  };
  const fieldById = (id: any) => fields.find(x => String(x.id) === String(id));
  const conditionSummary = (f: any) => {
    if (f.show_if_field_id == null) return '-';
    const parent = fieldById(f.show_if_field_id);
    const vals = String(f.show_if_value || '').split('|').map(v => v.trim()).filter(Boolean);
    return 'Shown when "' + (parent ? parent.field_label : '(deleted question)') + '" = ' + vals.join(' or ');
  };
  const moveField = async (index: number, dir: -1 | 1) => {
    const target = index + dir;
    if (target < 0 || target >= fields.length) return;
    const ids = fields.map(x => x.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    try {
      const res = await api.put('/fields.php', { reorder: ids });
      if (!res.data?.success) alert(res.data?.error || 'Failed');
    } catch (err) {}
    if (selectedDeptForFields) fetchFields(selectedDeptForFields);
  };

  const fetchFields = async (deptId: string) => {
    try {
      const res = await api.get('/fields.php?department_id=' + deptId);
      if (res.data?.success) setFields(res.data.data);
    } catch (err) {}
  };

  const fetchAdvancedSettings = async () => {
    try {
      const res = await api.get('/settings.php');
      if (res.data?.success && res.data.data) {
        setAdvancedSettings(prev => ({ ...prev, ...res.data.data }));
      }
    } catch (err) {}
  };

  useEffect(() => {
    setLoading(true);
    fetchDepts();
    if (activeTab === 'users') fetchUsers();
    if (activeTab === 'categories') fetchCategories();
    if (activeTab === 'fields' && selectedDeptForFields) fetchFields(selectedDeptForFields);
    if (activeTab === 'notifications' || activeTab === 'automation') fetchAdvancedSettings();
    setLoading(false);
  }, [activeTab]);

    // ---- FIELD CRUD ----
  const openFieldModal = (f?: any) => {
    if (f) {
      setEditingFieldId(f.id);
      setFieldForm({
        field_label: f.field_label,
        field_type: f.field_type,
        is_required: f.is_required ? 1 : 0,
        options: parseFieldOptions(f).join(', '),
        help_text: f.help_text || '',
        sort_order: f.sort_order != null ? String(f.sort_order) : '',
        show_if_field_id: f.show_if_field_id != null ? String(f.show_if_field_id) : '',
        show_if_value: f.show_if_value || ''
      });
    } else {
      setEditingFieldId(null);
      setFieldForm({ ...emptyFieldForm, sort_order: String(fields.length > 0 ? Math.max(...fields.map(x => Number(x.sort_order) || 0)) + 1 : 1) });
    }
    setIsFieldModalOpen(true);
  };

  const handleDeleteField = async (id: string) => {
    const dependents = fields.filter(x => String(x.show_if_field_id) === String(id));
    const warn = dependents.length > 0
      ? '\n\n' + dependents.length + ' other question(s) only show depending on this one and will then always be shown.'
      : '';
    if (!window.confirm('Delete this field?' + warn)) return;
    try {
      const res = await api.delete('/fields.php?id=' + id);
      if (res.data?.success) {
        if (selectedDeptForFields) fetchFields(selectedDeptForFields);
      } else alert(res.data?.error || 'Failed');
    } catch (err) {}
  };

  const handleSaveField = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDeptForFields) return;
    setIsSubmitting(true);
    
    // Parse options if dropdown
    let parsedOptions = null;
    if (fieldForm.field_type === 'dropdown' && fieldForm.options) {
        parsedOptions = JSON.stringify(fieldForm.options.split(',').map(s => s.trim()).filter(s => s));
    }

    const payload = {
        department_id: selectedDeptForFields,
        field_label: fieldForm.field_label,
        field_type: fieldForm.field_type,
        is_required: fieldForm.is_required,
        options: parsedOptions,
        help_text: fieldForm.help_text.trim() === '' ? null : fieldForm.help_text,
        ...(fieldForm.sort_order !== '' ? { sort_order: Number(fieldForm.sort_order) } : {}),
        show_if_field_id: fieldForm.show_if_field_id === '' ? null : Number(fieldForm.show_if_field_id),
        show_if_value: fieldForm.show_if_field_id === '' ? null : fieldForm.show_if_value,
        id: editingFieldId
    };

    try {
      if (editingFieldId) {
        const res = await api.put('/fields.php', payload);
        if (res.data?.success) { setIsFieldModalOpen(false); fetchFields(selectedDeptForFields); }
        else alert(res.data?.error || 'Failed');
      } else {
        const res = await api.post('/fields.php', payload);
        if (res.data?.success) { setIsFieldModalOpen(false); fetchFields(selectedDeptForFields); }
        else alert(res.data?.error || 'Failed');
      }
    } catch (err: any) {
      alert(err?.response?.data?.error || 'Failed to save question');
    }
    setIsSubmitting(false);
  };

  // ---- CATEGORY CRUD ----
  const openCategoryModal = (cat?: Category) => {
    if (cat) { setEditingCategoryId(cat.id); setCategoryForm({ name: cat.name, department_id: cat.department_id }); }
    else { setEditingCategoryId(null); setCategoryForm({ name: "", department_id: "" }); }
    setIsCategoryModalOpen(true);
  };
  
  const handleSaveCategory = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      if (editingCategoryId) {
        // Not implemented edit for categories for simplicity, let's just do delete/add or implement edit if needed
      } else {
        const res = await api.post('/categories.php', categoryForm);
        if (res.data?.success) { alert("Category added"); setIsCategoryModalOpen(false); fetchCategories(); }
        else alert(res.data?.error || 'Failed');
      }
    } catch (err) {}
    setIsSubmitting(false);
  };

  const handleDeleteCategory = async (id: string) => {
    if (!confirm('Delete this problem type?')) return;
    try {
      const res = await api.delete('/categories.php?id=' + id);
      if (res.data?.success) fetchCategories();
      else alert(res.data?.error || 'Failed to delete');
    } catch(err) {}
  };
  
  // ---- USER CRUD ----
  const handleSaveUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      if (editingUserId) {
        const res = await api.patch(`/users.php?id=${editingUserId}`, userForm);
        if (res.data?.success) {
          alert('User updated successfully!');
          setIsUserModalOpen(false);
          fetchUsers();
        } else alert(res.data?.error || 'Failed to update user.');
      } else {
        const res = await api.post('/users.php', userForm);
        if (res.data?.success) {
          alert('User added successfully!');
          setIsUserModalOpen(false);
          fetchUsers();
        } else alert(res.data?.error || 'Failed to add user.');
      }
    } catch (err: any) {
      alert(err.response?.data?.error || err.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteUser = async (id: string) => {
    if (!window.confirm("Are you sure you want to delete this user?")) return;
    try {
      const res = await api.delete(`/users.php?id=${id}`);
      if (res.data?.success) fetchUsers();
      else alert(res.data?.error || 'Failed to delete');
    } catch (err: any) { alert(err.response?.data?.error || err.message); }
  };

  const openUserModal = (user?: User) => {
    if (user) {
      setEditingUserId(user.id);
      setUserForm({ name: user.name, email: user.email, password: '', role: user.role, department_id: user.department_id || '' });
    } else {
      setEditingUserId(null);
      setUserForm({ name: '', email: '', password: '', role: 'employee', department_id: '' });
    }
    setIsUserModalOpen(true);
  };

  // ---- DEPT CRUD ----
  const handleSaveDept = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      if (editingDeptId) {
        const res = await api.patch(`/departments.php?id=${editingDeptId}`, deptForm);
        if (res.data?.success) {
          alert('Department updated successfully!');
          setIsDeptModalOpen(false);
          fetchDepts();
        } else alert(res.data?.error || 'Failed to update department.');
      } else {
        const res = await api.post('/departments.php', deptForm);
        if (res.data?.success) {
          alert('Department created successfully!');
          setIsDeptModalOpen(false);
          fetchDepts();
        } else alert(res.data?.error || 'Failed to create department.');
      }
    } catch (err: any) {
      alert(err.response?.data?.error || err.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteDept = async (id: string) => {
    if (!window.confirm("Are you sure you want to delete this department?")) return;
    try {
      const res = await api.delete(`/departments.php?id=${id}`);
      if (res.data?.success) fetchDepts();
      else alert(res.data?.error || 'Failed to delete');
    } catch (err: any) { alert(err.response?.data?.error || err.message); }
  };

  const openDeptModal = (dept?: Department) => {
    if (dept) {
      setEditingDeptId(dept.id);
      setDeptForm({ name: dept.name, code: dept.code || '', description: dept.description || '', notification_emails: dept.notification_emails || '' });
    } else {
      setEditingDeptId(null);
      setDeptForm({ name: '', code: '', description: '', notification_emails: '' });
    }
    setIsDeptModalOpen(true);
  };

  // ---- CLEANUP & SETTINGS ----
  const handleCleanup = async () => {
    if (!window.confirm("Are you sure you want to delete all resolved and closed tickets older than 30 days?")) return;
    setIsSubmitting(true);
    try {
      const res = await api.post('/cleanup.php?days=30', {});
      if (res.data?.success) alert(res.data.message);
      else alert('Cleanup failed: ' + res.data.error);
    } catch (err: any) { alert(err.response?.data?.error || err.message); } finally { setIsSubmitting(false); }
  };

  const handleSaveAdvancedSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      const res = await api.post('/settings.php', advancedSettings);
      if (res.data?.success) alert('Advanced settings saved successfully!');
      else alert('Failed to save advancedSettings.');
    } catch (err: any) { alert(err.response?.data?.error || err.message); } finally { setIsSubmitting(false); }
  };

  return (
    <div className="settings-page">
      <div className="settings-header">
        <h1>Administration</h1>
        <p>Premium workspace configuration, team management, and cleanup rules.</p>
      </div>

      <div className="settings-content glass">
        <div className="settings-sidebar">
          <ul className="settings-nav">
            <li className={activeTab === 'general' ? 'active' : ''} onClick={() => setActiveTab('general')}>General & Data</li>
            <li className={activeTab === 'departments' ? 'active' : ''} onClick={() => setActiveTab('departments')}>Departments</li>
              <li className={activeTab === 'fields' ? 'active' : ''} onClick={() => setActiveTab('fields')}>Custom Questions</li>
             <li className={activeTab === 'categories' ? 'active' : ''} onClick={() => setActiveTab('categories')}>Problem Types</li>
            <li className={activeTab === 'users' ? 'active' : ''} onClick={() => setActiveTab('users')}>Users & Roles</li>
            <li className={activeTab === 'notifications' ? 'active' : ''} onClick={() => setActiveTab('notifications')}>Notifications</li>
            <li className={activeTab === 'automation' ? 'active' : ''} onClick={() => setActiveTab('automation')}>Automation & SLA</li>
          </ul>
        </div>

        <div className="settings-panel">
          {activeTab === 'general' && (
            <div>
              <div className="panel-header"><h2>General Settings & Cleanup</h2></div>
              <div className="settings-card" style={{ background: 'var(--bg-tertiary)', padding: '20px', borderRadius: '8px', border: '1px solid var(--border)' }}>
                <h3 style={{ margin: '0 0 10px 0', color: 'var(--text-primary)' }}>Automatic Database Cleanup</h3>
                <p style={{ margin: '0 0 20px 0', color: 'var(--text-secondary)' }}>
                  To save resources, the system automatically marks resolved/closed issues for deletion after 30 days. You can manually trigger a cleanup of all old database values and file attachments right now.
                </p>
                <button className="btn-primary" style={{ backgroundColor: '#e74c3c' }} onClick={handleCleanup} disabled={isSubmitting}>
                  <Trash2 size={18} style={{ marginRight: '8px' }} />
                  {isSubmitting ? 'Cleaning...' : 'Delete Old Closed Tickets (> 30 days)'}
                </button>
              </div>
            </div>
          )}

          {activeTab === 'notifications' && (
            <div>
              <div className="panel-header"><h2>Custom Advanced settings</h2></div>
              <form onSubmit={handleSaveAdvancedSettings} className="settings-card" style={{ background: 'var(--bg-tertiary)', padding: '20px', borderRadius: '8px', border: '1px solid var(--border)' }}>
                <p style={{ margin: '0 0 20px 0', color: 'var(--text-secondary)' }}>Set a custom URL or relative path (like `/notification.mp3`) for each priority level.</p>
                
                {['low', 'medium', 'high', 'critical'].map((priority) => (
                  <div className="form-group" key={priority}>
                    <label style={{ textTransform: 'capitalize' }}>{priority} Priority Sound (URL or File)</label>
                    <div style={{ display: 'flex', gap: '10px' }}>
                      <input 
                        className="form-input" 
                        placeholder="Current URL or Base64"
                        value={(advancedSettings as any)[`sound_${priority}`]?.substring(0, 50) + ((advancedSettings as any)[`sound_${priority}`]?.length > 50 ? '...' : '')} 
                        onChange={(e) => setAdvancedSettings({...advancedSettings, [`sound_${priority}`]: e.target.value})}
                        style={{ flex: 1, backgroundColor: 'var(--bg-secondary)', cursor: 'not-allowed' }}
                        title="If using a file, this will display a truncated base64 string. To reset, type a URL like /notification.mp3"
                      />
                      <input 
                        type="file" 
                        accept=".mp3,.wav,.ogg" 
                        id={`file_${priority}`} 
                        style={{ display: 'none' }}
                        onChange={async (e) => {
                          if (e.target.files && e.target.files[0]) {
                            const formData = new FormData();
                            formData.append('sound', e.target.files[0]);
                            try {
                              const res = await api.post('/upload_sound.php', formData, {
                                headers: { 'Content-Type': 'multipart/form-data' }
                              });
                              if (res.data?.success) {
                                setAdvancedSettings({...advancedSettings, [`sound_${priority}`]: res.data.sound_url});
                                alert('Sound file loaded! Click Save to apply.');
                              } else {
                                alert(res.data?.error || 'Upload failed');
                              }
                            } catch (err) { alert('Error uploading sound file'); }
                          }
                        }}
                      />
                      <button 
                        type="button" 
                        className="btn-secondary" 
                        onClick={() => document.getElementById(`file_${priority}`)?.click()}
                      >
                        Upload File
                      </button>
                    </div>
                  </div>
                ))}

                <button type="submit" className="btn-primary" disabled={isSubmitting}>
                  {isSubmitting ? 'Saving...' : 'Save Sound Settings'}
                </button>
              </form>
            </div>
          )}

          {activeTab === 'automation' && (
            <div>
              <div className="panel-header"><h2>Service Level Agreements (SLA) & Rules</h2></div>
              <form onSubmit={handleSaveAdvancedSettings} className="settings-card" style={{ background: 'var(--bg-tertiary)', padding: '20px', borderRadius: '8px', border: '1px solid var(--border)', marginBottom: '20px' }}>
                <h3 style={{ margin: '0 0 10px 0', color: 'var(--text-primary)' }}>SLA Target Response Time (Hours)</h3>
                <p style={{ margin: '0 0 20px 0', color: 'var(--text-secondary)' }}>Set the expected maximum time to resolve tickets based on their priority.</p>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
                  {['low', 'medium', 'high', 'critical'].map((priority) => (
                    <div className="form-group" key={priority}>
                      <label style={{ textTransform: 'capitalize' }}>{priority} Priority (Hrs)</label>
                      <input 
                        type="number"
                        min="1"
                        className="form-input" 
                        value={(advancedSettings as any)[`sla_${priority}`]} 
                        onChange={(e) => setAdvancedSettings({...advancedSettings, [`sla_${priority}`]: parseInt(e.target.value) || 0})}
                      />
                    </div>
                  ))}
                </div>
                <button type="submit" className="btn-primary" disabled={isSubmitting} style={{ marginTop: '15px' }}>
                  {isSubmitting ? 'Saving...' : 'Save SLA Settings'}
                </button>
              </form>

              <div className="settings-card" style={{ background: 'var(--bg-tertiary)', padding: '20px', borderRadius: '8px', border: '1px solid var(--border)' }}>
                <h3 style={{ margin: '0 0 10px 0', color: 'var(--text-primary)' }}>Auto-Priority Rules</h3>
                <p style={{ margin: '0 0 20px 0', color: 'var(--text-secondary)' }}>Automatically upgrade ticket priority when created by specific users or departments. (Feature processing implementation...)</p>
                
                <table className="settings-table">
                  <thead><tr><th>If Requester Email is...</th><th>Then Priority becomes...</th><th>Action</th></tr></thead>
                  <tbody>
                    {(advancedSettings.rules || []).map((rule: any, i: number) => (
                      <tr key={i}>
                        <td>{rule.email}</td>
                        <td style={{ textTransform: 'capitalize' }}>{rule.priority}</td>
                        <td>
                          <button type="button" className="icon-btn text-danger" onClick={() => {
                            const newRules = [...advancedSettings.rules];
                            newRules.splice(i, 1);
                            setAdvancedSettings({...advancedSettings, rules: newRules});
                          }}><Trash2 size={16} /></button>
                        </td>
                      </tr>
                    ))}
                    <tr>
                      <td>
                        <input id="new_rule_email" placeholder="e.g. boss@m-mines.in" className="form-input" style={{ padding: '8px' }} />
                      </td>
                      <td>
                        <select id="new_rule_priority" className="form-input" style={{ padding: '8px' }}>
                          <option value="high">High</option>
                          <option value="critical">Critical</option>
                        </select>
                      </td>
                      <td>
                        <button type="button" className="btn-primary" style={{ padding: '8px 12px' }} onClick={() => {
                            const email = (document.getElementById('new_rule_email') as HTMLInputElement).value;
                            const priority = (document.getElementById('new_rule_priority') as HTMLSelectElement).value;
                            if (email) {
                                setAdvancedSettings({
                                    ...advancedSettings, 
                                    rules: [...(advancedSettings.rules || []), { email, priority }]
                                });
                                (document.getElementById('new_rule_email') as HTMLInputElement).value = '';
                            }
                        }}>Add Rule</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                
                <div style={{ marginTop: '15px', color: 'var(--text-secondary)', fontSize: '13px' }}>
                  * Remember to click <b>Save SLA Settings</b> above to persist rule changes.
                </div>
              </div>
            </div>
          )}

          {activeTab === 'fields' && (
              <div>
                <div className="panel-header">
                  <h2>Custom Department Questions</h2>
                  <div style={{display: 'flex', gap: '10px'}}>
                    <select className="form-input" value={selectedDeptForFields} onChange={(e) => { setSelectedDeptForFields(e.target.value); if(e.target.value) fetchFields(e.target.value); }}>
                        <option value="">Select Department...</option>
                        {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </select>
                    <button className="btn-primary" onClick={() => openFieldModal()} disabled={!selectedDeptForFields}>Add Question</button>
                  </div>
                </div>
                {selectedDeptForFields ? (
                    <div className="table-responsive">
                      <table className="settings-table">
                        <thead><tr><th style={{ width: '90px' }}>Order</th><th>Label</th><th>Type</th><th>Required</th><th>Options (if dropdown)</th><th>Condition</th><th style={{ width: '100px' }}>Actions</th></tr></thead>
                        <tbody>
                          {fields.map((f, idx) => (
                            <tr key={f.id}>
                              <td>
                                <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                                  <span>{f.sort_order}</span>
                                  <button className="icon-btn" title="Move up" disabled={idx === 0} onClick={() => moveField(idx, -1)}>&#9650;</button>
                                  <button className="icon-btn" title="Move down" disabled={idx === fields.length - 1} onClick={() => moveField(idx, 1)}>&#9660;</button>
                                </div>
                              </td>
                              <td>{f.field_label}{f.help_text ? <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', whiteSpace: 'pre-line' }}>{f.help_text}</div> : null}</td>
                              <td>{f.field_type}</td>
                              <td>{f.is_required ? 'Yes' : 'No'}</td>
                              <td>{parseFieldOptions(f).join(', ') || '-'}</td>
                              <td style={{ fontSize: '0.8rem' }}>{conditionSummary(f)}</td>
                              <td>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                  <button className="icon-btn" onClick={() => openFieldModal(f)}><Edit size={16} /></button>
                                  <button className="icon-btn text-danger" onClick={() => handleDeleteField(f.id)}><Trash2 size={16} color="var(--status-open)" /></button>
                                </div>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                ) : (
                    <p style={{marginTop: '20px', color: 'var(--text-secondary)'}}>Please select a department above to view or add custom questions.</p>
                )}
              </div>
            )}

            {activeTab === 'departments' && (
            <div>
              <div className="panel-header">
                <h2>Departments</h2>
                <button className="btn-primary" onClick={() => openDeptModal()}>Add Department</button>
              </div>
              <div className="table-responsive">
                <table className="settings-table">
                  <thead><tr><th>Name</th><th>Code</th><th>Description</th><th style={{ width: '100px' }}>Actions</th></tr></thead>
                  <tbody>
                    {loading ? <tr><td colSpan={4}>Loading...</td></tr> : departments.map(dept => (
                      <tr key={dept.id}>
                        <td>{dept.name}</td><td>{dept.code || 'N/A'}</td><td>{dept.description}</td>
                        <td>
                          <div style={{ display: 'flex', gap: '8px' }}>
                            <button className="icon-btn" onClick={() => openDeptModal(dept)}><Edit size={16} /></button>
                            <button className="icon-btn text-danger" onClick={() => handleDeleteDept(dept.id)}><Trash2 size={16} color="var(--status-open)" /></button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'categories' && (
            <div>
              <div className="panel-header">
                <h2>Problem Types (Categories)</h2>
                <button className="btn-primary" onClick={() => openCategoryModal()}>Add Type</button>
              </div>
              <div className="table-responsive">
                <table className="settings-table">
                  <thead><tr><th>Name</th><th>Department</th><th style={{ width: '100px' }}>Actions</th></tr></thead>
                  <tbody>
                    {loading ? <tr><td colSpan={3}>Loading...</td></tr> : categories.map(cat => (
                      <tr key={cat.id}>
                        <td>{cat.name}</td><td>{cat.department_name}</td>
                        <td>
                          <button className="icon-btn text-danger" onClick={() => handleDeleteCategory(cat.id)}><Trash2 size={16} color="var(--status-open)" /></button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
          
          {activeTab === 'users' && (
            <div>
              <div className="panel-header">
                <h2>Users & Roles</h2>
                <button className="btn-primary" onClick={() => openUserModal()}>Add User</button>
              </div>
              <div className="table-responsive">
                <table className="settings-table">
                  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th style={{ width: '100px' }}>Actions</th></tr></thead>
                  <tbody>
                    {loading ? <tr><td colSpan={5}>Loading...</td></tr> : users.map(user => (
                      <tr key={user.id}>
                        <td>{user.name}</td><td>{user.email}</td>
                        <td><span className={`role-badge role-${user.role?.toLowerCase()}`}>{user.role === 'agent' ? 'Specialist' : user.role === 'dept_head' ? 'Department Head' : user.role}</span></td>
                        <td>{user.department || '-'}</td>
                        <td>
                          <div style={{ display: 'flex', gap: '8px' }}>
                            <button className="icon-btn" onClick={() => openUserModal(user)}><Edit size={16} /></button>
                            <button className="icon-btn text-danger" onClick={() => handleDeleteUser(user.id)}><Trash2 size={16} color="var(--status-open)" /></button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Category Modal */}
      {isCategoryModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content glass">
            <div className="modal-header">
              <h2>Add Problem Type</h2>
              <button className="icon-btn" onClick={() => setIsCategoryModalOpen(false)}><X size={20} /></button>
            </div>
            <form onSubmit={handleSaveCategory}>
              <div className="form-group">
                <label>Department</label>
                <select required className="form-input" value={categoryForm.department_id} onChange={e => setCategoryForm({...categoryForm, department_id: e.target.value})}>
                  <option value="">Select Department</option>
                  {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Problem Type Name (e.g. Hardware Issue)</label>
                <input required className="form-input" value={categoryForm.name} onChange={e => setCategoryForm({...categoryForm, name: e.target.value})} />
              </div>
              <div className="modal-actions">
                <button type="button" className="btn-secondary" onClick={() => setIsCategoryModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>{isSubmitting ? 'Saving...' : 'Add Type'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
      
      {/* User Modal */}
      {isUserModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content glass">
            <div className="modal-header">
              <h2>{editingUserId ? 'Edit User Account' : 'Create New User Account'}</h2>
              <button className="icon-btn" onClick={() => setIsUserModalOpen(false)}><X size={20} /></button>
            </div>
            <form onSubmit={handleSaveUser}>
              <div className="form-group">
                <label>Full Name</label>
                <input required className="form-input" value={userForm.name} onChange={e => setUserForm({...userForm, name: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Email Address</label>
                <input required type="email" className="form-input" value={userForm.email} onChange={e => setUserForm({...userForm, email: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Password {editingUserId ? '(Leave blank to keep current)' : ''}</label>
                <input type="password" required={!editingUserId} className="form-input" value={userForm.password} onChange={e => setUserForm({...userForm, password: e.target.value})} />
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Role</label>
                  <select required className="form-input" value={userForm.role} onChange={e => setUserForm({...userForm, role: e.target.value})}>
                    <option value="employee">Employee</option>
                    <option value="agent">Specialist</option>
                    <option value="dept_head">Department Head</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Department (Source of Truth)</label>
                  <select className="form-input" value={userForm.department_id} onChange={e => setUserForm({...userForm, department_id: e.target.value})}>
                    <option value="">None (Global)</option>
                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                </div>
              </div>
              <div className="modal-actions">
                <button type="button" className="btn-secondary" onClick={() => setIsUserModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>{isSubmitting ? 'Saving...' : 'Save Account'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dept Modal */}
      {isFieldModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div className="modal-header">
              <h2>{editingFieldId ? 'Edit Question' : 'Add Question'}</h2>
              <button className="icon-btn" onClick={() => setIsFieldModalOpen(false)}><X size={20} /></button>
            </div>
            <form onSubmit={handleSaveField} className="modal-form">
              <div className="form-group">
                <label>Question Label *</label>
                <input required type="text" className="form-input" value={fieldForm.field_label} onChange={e => setFieldForm({...fieldForm, field_label: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Field Type</label>
                <select className="form-input" value={fieldForm.field_type} onChange={e => setFieldForm({...fieldForm, field_type: e.target.value})}>
                    <option value="text">Text Input</option>
                    <option value="textarea">Multi-line Text (Textarea)</option>
                    <option value="dropdown">Dropdown Options</option>
                    <option value="date">Date</option>
                    <option value="number">Number</option>
                </select>
              </div>
              {fieldForm.field_type === 'dropdown' && (
                  <div className="form-group">
                    <label>Dropdown Options (comma separated) *</label>
                    <input required type="text" className="form-input" placeholder="e.g. ERP Not Working, Internet Issue" value={fieldForm.options} onChange={e => setFieldForm({...fieldForm, options: e.target.value})} />
                  </div>
              )}
              <div className="form-group">
                <label>Help text (optional, shown under the question; line breaks are kept)</label>
                <textarea className="form-input" rows={3} value={fieldForm.help_text} onChange={e => setFieldForm({...fieldForm, help_text: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Sort order (lower shows first)</label>
                <input type="number" className="form-input" value={fieldForm.sort_order} onChange={e => setFieldForm({...fieldForm, sort_order: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Show only when</label>
                <select className="form-input" value={fieldForm.show_if_field_id} onChange={e => setFieldForm({...fieldForm, show_if_field_id: e.target.value, show_if_value: ''})}>
                    <option value="">Always show</option>
                    {[...fields].filter(x => String(x.id) !== String(editingFieldId)).sort((a, b) => (a.field_type === 'dropdown' ? 0 : 1) - (b.field_type === 'dropdown' ? 0 : 1)).map(x => (
                      <option key={x.id} value={x.id}>{x.field_label}{x.field_type === 'dropdown' ? '' : ' (' + x.field_type + ')'}</option>
                    ))}
                </select>
                {fieldForm.show_if_field_id !== '' && (() => {
                  const parent = fieldById(fieldForm.show_if_field_id);
                  const parentOptions = parent ? parseFieldOptions(parent) : [];
                  const selected = fieldForm.show_if_value.split('|').map(v => v.trim()).filter(Boolean);
                  if (parent && parent.field_type === 'dropdown' && parentOptions.length > 0) {
                    return (
                      <div style={{ marginTop: '8px', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>Show when the answer is any of:</span>
                        {parentOptions.map(opt => (
                          <label key={opt} style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: 0 }}>
                            <input type="checkbox" checked={selected.includes(opt)} onChange={e => {
                              const next = e.target.checked ? [...selected, opt] : selected.filter(v => v !== opt);
                              setFieldForm({...fieldForm, show_if_value: parentOptions.filter(o => next.includes(o)).concat(next.filter(v => !parentOptions.includes(v))).join('|')});
                            }} />
                            {opt}
                          </label>
                        ))}
                      </div>
                    );
                  }
                  return (
                    <input required type="text" className="form-input" style={{ marginTop: '8px' }} placeholder="Accepted answers, separated by | (e.g. Yes|Maybe)" value={fieldForm.show_if_value} onChange={e => setFieldForm({...fieldForm, show_if_value: e.target.value})} />
                  );
                })()}
              </div>
              <div className="form-group" style={{flexDirection: 'row', alignItems: 'center', gap: '10px'}}>
                <input type="checkbox" id="req" checked={fieldForm.is_required === 1} onChange={e => setFieldForm({...fieldForm, is_required: e.target.checked ? 1 : 0})} />
                <label htmlFor="req" style={{marginBottom: 0}}>Is this question required? (only enforced when the question is shown)</label>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn-secondary" onClick={() => setIsFieldModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>{isSubmitting ? 'Saving...' : 'Save Question'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {isDeptModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content glass">
            <div className="modal-header">
              <h2>{editingDeptId ? 'Edit Department' : 'Create New Department'}</h2>
              <button className="icon-btn" onClick={() => setIsDeptModalOpen(false)}><X size={20} /></button>
            </div>
            <form onSubmit={handleSaveDept}>
              <div className="form-group">
                <label>Department Name</label>
                <input required className="form-input" value={deptForm.name} onChange={e => setDeptForm({...deptForm, name: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Department Code (e.g. IT, HR)</label>
                <input required className="form-input" value={deptForm.code} onChange={e => setDeptForm({...deptForm, code: e.target.value})} />
              </div>
              <div className="form-group">
                <label>Description</label>
                <textarea className="form-input" rows={3} value={deptForm.description} onChange={e => setDeptForm({...deptForm, description: e.target.value})}></textarea>
              </div>
              <div className="form-group">
                <label>Notification emails (comma separated)</label>
                <input type="text" className="form-input" placeholder="it-team@example.com, lead@example.com" value={deptForm.notification_emails} onChange={e => setDeptForm({...deptForm, notification_emails: e.target.value})} />
              </div>
              <div className="modal-actions">
                <button type="button" className="btn-secondary" onClick={() => setIsDeptModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>{isSubmitting ? 'Saving...' : 'Save Department'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}












