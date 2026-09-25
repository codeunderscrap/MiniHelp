-- Create database if not exists
CREATE DATABASE IF NOT EXISTS minimines_helpdesk;
USE minimines_helpdesk;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dept_head', 'agent', 'employee') DEFAULT 'employee',
    department_id INT NULL,
    avatar_url VARCHAR(255) NULL,
    mmos_sub VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mmos_sub (mmos_sub)
);

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ticket Categories (Problem Types per Department)
CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Tickets table
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'assigned', 'in_progress', 'waiting', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    department_id INT NOT NULL,
    creator_id INT NOT NULL,
    assignee_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (creator_id) REFERENCES users(id),
    FOREIGN KEY (assignee_id) REFERENCES users(id)
);

-- Comments table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Ticket Attachments
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Ticket History (Tracking timeline)
CREATE TABLE IF NOT EXISTS ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Form Fields (Dynamic questions per department)
CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'textarea', 'dropdown') DEFAULT 'text',
    options JSON NULL,
    is_required BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Ticket Custom Values (Answers to dynamic questions)
CREATE TABLE IF NOT EXISTS ticket_custom_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    field_id INT NOT NULL,
    field_value TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE CASCADE
);

-- Push Subscriptions (Web Push API)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- In-App Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

-- System Settings (Admin configured sounds, etc.)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

-- Insert dummy departments
INSERT IGNORE INTO departments (id, name, code, description) VALUES 
(1, 'Information Technology', 'IT', 'Computers, Access, Networks'),
(2, 'Human Resources', 'HR', 'Payroll, Leaves, Onboarding'),
(3, 'Finance & Accounts', 'FIN', 'Expenses, Billing, Invoices'),
(4, 'Sales & BD', 'SALES', 'Client Issues, CRM'),
(5, 'Purchase', 'PUR', 'Procurement, Vendor Management'),
(6, 'Stores & Logistics', 'LOG', 'Inventory, Shipping, Tracking')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Seed Dynamic Form Fields for IT
INSERT IGNORE INTO form_fields (id, department_id, field_label, field_type, options, is_required) VALUES
(1, 1, 'Device Type', 'dropdown', '["Laptop", "Desktop", "Mobile", "Printer", "Other"]', TRUE),
(2, 1, 'Operating System', 'dropdown', '["Windows", "macOS", "Linux", "iOS", "Android"]', FALSE),
(3, 1, 'Error Message', 'textarea', NULL, FALSE);

-- Seed Dynamic Form Fields for HR
INSERT IGNORE INTO form_fields (id, department_id, field_label, field_type, options, is_required) VALUES
(4, 2, 'Request Type', 'dropdown', '["Leave Approval", "Payroll Query", "Onboarding", "Grievance"]', TRUE),
(5, 2, 'Employee ID', 'text', NULL, TRUE);

-- Seed Default Notification Sounds
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('sound_low', '/notification.mp3'),
('sound_medium', '/notification.mp3'),
('sound_high', '/notification.mp3'),
('sound_critical', '/notification.mp3');

-- Insert default admin user (password is 'password123')
INSERT IGNORE INTO users (name, email, password_hash, role, department_id) VALUES 
('Admin User', 'admin@minimines.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);
