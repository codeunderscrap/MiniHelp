import { useState, useEffect } from 'react';
import { api } from '../api';
import './Settings.css';
import { X } from 'lucide-react';

interface Department {
  id: string;
  name: string;
  code?: string;
  description: string;
}

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  department: string;
}

export function Settings() {
  const [activeTab, setActiveTab] = useState('departments');
  const [departments, setDepartments] = useState<Department[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);

  // New User state
  const [isNewUserModalOpen, setIsNewUserModalOpen] = useState(false);
  const [newUser, setNewUser] = useState({
    name: '',
    email: '',
    password: '',
    role: 'User',
    department: ''
  });
  const [isSubmitting, setIsSubmitting] = useState(false);

  // SLA state
  const [slaConfig, setSlaConfig] = useState<any[]>([]);
  const [slaLoading, setSlaLoading] = useState(false);

  const dummyDepts = [
    { id: '1', name: 'IT Support', code: 'IT', description: 'Handles all technical issues' },
    { id: '2', name: 'Human Resources', code: 'HR', description: 'Handles employee requests' },
  ];

  const dummyUsers = [
    { id: '1', name: 'Alice Smith', email: 'alice@minimines.com', role: 'Admin', department: 'IT' },
    { id: '2', name: 'Bob Jones', email: 'bob@minimines.com', role: 'Agent', department: 'HR' },
    { id: '3', name: 'Charlie Day', email: 'charlie@minimines.com', role: 'User', department: 'Finance' },
  ];

  const fetchDepts = async () => {
    try {
      const res = await api.get('/departments.php');
      if (res.data && res.data.success) {
        setDepartments(res.data.data);
      } else {
        setDepartments(dummyDepts);
      }
    } catch (err) {
      console.error('API Error, using dummy data', err);
      setDepartments(dummyDepts);
    } finally {
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const res = await api.get('/users.php');
      if (res.data && res.data.success) {
        setUsers(res.data.data);
      } else {
        setUsers(dummyUsers);
      }
    } catch (err) {
      console.error('API Error, using dummy data', err);
      setUsers(dummyUsers);
    } finally {
      setLoading(false);
    }
  };

  const fetchSla = async () => {
    setSlaLoading(true);
    try {
      const res = await api.get('/sla.php');
      if (res.data && res.data.success) {
        setSlaConfig(res.data.data);
      } else {
        setSlaConfig([
          { priority: 'Low', response_time: 24, resolution_time: 72 },
          { priority: 'Medium', response_time: 12, resolution_time: 48 },
          { priority: 'High', response_time: 4, resolution_time: 24 },
          { priority: 'Critical', response_time: 1, resolution_time: 4 },
        ]);
      }
    } catch (err) {
      console.error('API Error fetching SLA', err);
      setSlaConfig([
        { priority: 'Low', response_time: 24, resolution_time: 72 },
        { priority: 'Medium', response_time: 12, resolution_time: 48 },
        { priority: 'High', response_time: 4, resolution_time: 24 },
        { priority: 'Critical', response_time: 1, resolution_time: 4 },
      ]);
    } finally {
      setSlaLoading(false);
    }
  };

  useEffect(() => {
    setLoading(true);
    if (activeTab === 'departments') {
      fetchDepts();
    } else if (activeTab === 'users') {
      fetchUsers();
    } else if (activeTab === 'sla') {
      fetchSla();
      setLoading(false);
    } else {
      setLoading(false);
    }
  }, [activeTab]);

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      // Create payload strictly with name, email, password, role as requested
      const payload = {
        name: newUser.name,
        email: newUser.email,
        password: newUser.password,
        role: newUser.role,
        department: newUser.department
      };
      
      const res = await api.post('/users.php', payload);
      
      if (res.data && res.data.success) {
        setIsNewUserModalOpen(false);
        setNewUser({ name: '', email: '', password: '', role: 'User', department: '' });
        fetchUsers();
        alert('User added successfully!');
      } else {
        console.error('API Error creating user:', res.data);
        alert(res.data?.error || 'Failed to add user. API returned an error.');
      }
    } catch (err: any) {
      console.error('API Error creating user', err);
      alert(err.response?.data?.error || err.message || 'Failed to add user. Check console for details.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSaveSla = async () => {
    setIsSubmitting(true);
    try {
      await api.post('/sla.php', { rules: slaConfig });
      alert('SLA Config saved successfully!');
    } catch (err) {
      console.error('API Error saving SLA', err);
      alert('Failed to save SLA config');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="settings-page">
      <div className="settings-header">
        <h1>Administration</h1>
        <p>Manage workspaces, teams, and system configurations.</p>
      </div>

      <div className="settings-content glass">
        <div className="settings-sidebar">
          <ul className="settings-nav">
            <li className={activeTab === 'general' ? 'active' : ''} onClick={() => setActiveTab('general')}>General</li>
            <li className={activeTab === 'departments' ? 'active' : ''} onClick={() => setActiveTab('departments')}>Departments</li>
            <li className={activeTab === 'users' ? 'active' : ''} onClick={() => setActiveTab('users')}>Users & Roles</li>
            <li className={activeTab === 'sla' ? 'active' : ''} onClick={() => setActiveTab('sla')}>SLA Config</li>
          </ul>
        </div>

        <div className="settings-panel">
          {activeTab === 'departments' && (
            <div>
              <div className="panel-header">
                <h2>Departments</h2>
                <button className="btn-primary">Add Department</button>
              </div>
              
              <table className="settings-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={4}>Loading...</td></tr>
                  ) : (
                    departments.map(dept => (
                      <tr key={dept.id}>
                        <td>{dept.name}</td>
                        <td>{dept.code || 'N/A'}</td>
                        <td>{dept.description}</td>
                        <td>
                          <button className="btn-link">Edit</button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'users' && (
            <div>
              <div className="panel-header">
                <h2>Users & Roles</h2>
                <button className="btn-primary" onClick={() => setIsNewUserModalOpen(true)}>Add User</button>
              </div>
              
              <table className="settings-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={5}>Loading...</td></tr>
                  ) : (
                    users.map(user => (
                      <tr key={user.id}>
                        <td>{user.name}</td>
                        <td>{user.email}</td>
                        <td>
                          <span className={`role-badge role-${user.role.toLowerCase()}`}>
                            {user.role}
                          </span>
                        </td>
                        <td>{user.department}</td>
                        <td>
                          <button className="btn-link">Edit</button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'sla' && (
            <div>
              <div className="panel-header">
                <h2>SLA Configuration</h2>
                <button className="btn-primary" onClick={handleSaveSla} disabled={isSubmitting}>
                  {isSubmitting ? 'Saving...' : 'Save Changes'}
                </button>
              </div>
              
              <div className="sla-config-form">
                {slaLoading ? (
                  <p>Loading SLA rules...</p>
                ) : (
                  <table className="settings-table">
                    <thead>
                      <tr>
                        <th>Priority</th>
                        <th>Response Time (Hours)</th>
                        <th>Resolution Time (Hours)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {slaConfig.map((rule, index) => (
                        <tr key={index}>
                          <td>
                            <span className={`priority-badge priority-${rule.priority.toLowerCase()}`}>
                              {rule.priority}
                            </span>
                          </td>
                          <td>
                            <input 
                              type="number" 
                              className="form-input" 
                              value={rule.response_time} 
                              onChange={(e) => {
                                const newConfig = [...slaConfig];
                                newConfig[index].response_time = parseInt(e.target.value) || 0;
                                setSlaConfig(newConfig);
                              }}
                            />
                          </td>
                          <td>
                            <input 
                              type="number" 
                              className="form-input" 
                              value={rule.resolution_time} 
                              onChange={(e) => {
                                const newConfig = [...slaConfig];
                                newConfig[index].resolution_time = parseInt(e.target.value) || 0;
                                setSlaConfig(newConfig);
                              }}
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {activeTab !== 'departments' && activeTab !== 'users' && activeTab !== 'sla' && (
            <div className="placeholder-content">
              <h3>{activeTab.charAt(0).toUpperCase() + activeTab.slice(1)} Settings</h3>
              <p>Configuration options will appear here.</p>
            </div>
          )}
        </div>
      </div>

      {/* New User Modal */}
      {isNewUserModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content glass">
            <div className="modal-header">
              <h2>Add New User</h2>
              <button className="icon-btn" onClick={() => setIsNewUserModalOpen(false)}>
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleCreateUser} className="modal-body">
              <div className="form-group">
                <label>Name</label>
                <input 
                  type="text" 
                  required 
                  className="form-input"
                  value={newUser.name}
                  onChange={e => setNewUser({...newUser, name: e.target.value})}
                />
              </div>
              <div className="form-group">
                <label>Email</label>
                <input 
                  type="email" 
                  required 
                  className="form-input"
                  value={newUser.email}
                  onChange={e => setNewUser({...newUser, email: e.target.value})}
                />
              </div>
              <div className="form-group">
                <label>Password</label>
                <input 
                  type="password" 
                  required 
                  className="form-input"
                  value={newUser.password}
                  onChange={e => setNewUser({...newUser, password: e.target.value})}
                />
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Role</label>
                  <select 
                    required 
                    className="form-input"
                    value={newUser.role}
                    onChange={e => setNewUser({...newUser, role: e.target.value})}
                  >
                    <option value="User">User</option>
                    <option value="Agent">Agent</option>
                    <option value="Admin">Admin</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Department</label>
                  <select 
                    className="form-input"
                    value={newUser.department}
                    onChange={e => setNewUser({...newUser, department: e.target.value})}
                  >
                    <option value="">None</option>
                    <option value="IT">IT Support</option>
                    <option value="HR">Human Resources</option>
                    <option value="Finance">Finance</option>
                  </select>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn-secondary" onClick={() => setIsNewUserModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={isSubmitting}>
                  {isSubmitting ? 'Submitting...' : 'Add User'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
