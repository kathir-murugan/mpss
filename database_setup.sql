-- MPSS | Management Performance & Salary System
-- Database Setup Script for MySQL

CREATE DATABASE IF NOT EXISTS mpss_db;
USE mpss_db;

-- 1. Users Table (Authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('CEO', 'Manager', 'Employee') NOT NULL,
    employee_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Employees Table (Core Profile)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    position VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    dob DATE DEFAULT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    emergency_contact VARCHAR(100),
    blood_group VARCHAR(5),
    address TEXT,
    base_salary DECIMAL(10,2) NOT NULL,
    join_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('active', 'inactive') DEFAULT 'active',
    kpi_score DECIMAL(5,2) DEFAULT 0,
    tasks_completed INT DEFAULT 0,
    total_tasks INT DEFAULT 0,
    weekend_hours INT DEFAULT 0,
    attendance_pct DECIMAL(5,2) DEFAULT 0,
    profile_pic VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME DEFAULT NULL,
    check_out_time TIME DEFAULT NULL,
    total_hours DECIMAL(5,2) DEFAULT NULL,
    status ENUM('present', 'absent', 'late', 'half-day') DEFAULT 'present',
    notes TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, date)
);

-- 4. Performance Table (Monthly Scores)
CREATE TABLE IF NOT EXISTS performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    kpi_score INT DEFAULT 0,
    tasks_completed INT DEFAULT 0,
    total_tasks INT DEFAULT 0,
    weekend_hours INT DEFAULT 0,
    rating VARCHAR(20),
    manager_review DECIMAL(3,1),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 5. Salary History Table (Payouts)
CREATE TABLE IF NOT EXISTS salary_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    base_salary DECIMAL(10,2),
    increment_amount DECIMAL(10,2) DEFAULT 0,
    performance_bonus DECIMAL(10,2) DEFAULT 0,
    weekend_bonus DECIMAL(10,2) DEFAULT 0,
    hra DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) NOT NULL,
    rating VARCHAR(20),
    payment_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    payment_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX (month_year)
);

-- 6. Manual Salary Table (Manager Overrides)
CREATE TABLE IF NOT EXISTS manual_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    increment_amount DECIMAL(10,2) DEFAULT 0,
    performance_bonus DECIMAL(10,2) DEFAULT 0,
    weekend_bonus DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    total_salary DECIMAL(10,2) NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_manual_salary (employee_id, month_year)
);

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('salary', 'performance', 'general', 'alert', 'info', 'success', 'warning') DEFAULT 'general',
    icon VARCHAR(50) DEFAULT 'bell',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 8. Bulk Upload History
CREATE TABLE IF NOT EXISTS upload_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(100) NOT NULL,
    records_processed INT DEFAULT 0,
    errors INT DEFAULT 0,
    error_details TEXT,
    status ENUM('Success', 'Partial Success', 'Failed') DEFAULT 'Success'
);

CREATE TABLE IF NOT EXISTS uploaded_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_id INT NOT NULL,
    employee_id INT NOT NULL,
    FOREIGN KEY (upload_id) REFERENCES upload_history(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 9. Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATE,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_by VARCHAR(100),
    progress INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 10. Pages Table (Dynamic Page Management)
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(100) NOT NULL,
    page_file VARCHAR(100) NOT NULL,
    page_title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-file',
    is_active TINYINT DEFAULT 1,
    display_order INT DEFAULT 0,
    requires_auth TINYINT DEFAULT 0,
    allowed_roles TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SEED DATA (Default Pages)
-- Password for all is their username + '123' (e.g., ceo123)
-- In a real app, use password_hash() in PHP
INSERT INTO users (username, password, email, role) VALUES 
('ceo', 'ceo123', 'ceo@mpss.com', 'CEO'),
('manager', 'manager123', 'manager@mpss.com', 'Manager'),
('employee', 'employee123', 'employee@mpss.com', 'Employee');

-- SEED DATA (Initial Employees)
INSERT INTO employees (name, department, position, base_salary, kpi_score, email, phone, emergency_contact, join_date, profile_pic) VALUES 
('John Doe', 'IT', 'Senior Developer', 45000, 85, 'john.doe@company.com', '+91 98765 43210', '+91 99887 66554', '2023-01-15', 'https://ui-avatars.com/api/?name=John+Doe&size=150&background=6366f1&color=fff&bold=true'),
('Jane Smith', 'HR', 'HR Executive', 35000, 78, 'jane.smith@company.com', '+91 98765 43211', '+91 99887 66555', '2023-03-20', NULL),
('Mike Johnson', 'Sales', 'Sales Manager', 55000, 92, 'mike.johnson@company.com', '+91 98765 43212', '+91 99887 66556', '2022-11-10', NULL),
('Sarah Williams', 'Finance', 'Accountant', 40000, 71, 'sarah.williams@company.com', '+91 98765 43213', '+91 99887 66557', '2023-06-05', NULL);

-- UPDATE CEO/Employee Link
UPDATE users SET employee_id = 1 WHERE username = 'employee';

-- SEED DATA (Tasks)
INSERT INTO tasks (employee_id, title, deadline, status, priority, assigned_by, progress) VALUES 
(1, 'Complete project documentation', '2026-03-10', 'in_progress', 'high', 'Sarah Wilson', 75),
(1, 'Fix login page bug', '2026-03-08', 'completed', 'medium', 'Sarah Wilson', 100),
(1, 'Team meeting', '2026-03-06', 'pending', 'low', 'Sarah Wilson', 0);

-- SEED DATA (Notifications)
INSERT INTO notifications (employee_id, title, message, type, icon, is_read) VALUES 
(1, 'Salary Slip', 'Your salary slip for February is ready', 'success', 'wallet', 0),
(1, 'Team Meeting', 'Team meeting tomorrow at 10 AM', 'info', 'users', 0),
(1, 'Performance Review', 'Performance review scheduled for next week', 'warning', 'star', 1);

-- SEED DATA (Pages - Dynamic Page Management)
INSERT INTO pages (page_name, page_file, page_title, description, icon, is_active, display_order, requires_auth, allowed_roles) VALUES 
('Home', 'home.php', 'Home - MPSS', 'Landing page for the system', 'fa-home', 1, 1, 0, NULL),
('Login', 'login.php', 'Login - MPSS', 'User authentication page', 'fa-sign-in-alt', 1, 2, 0, NULL),
('Register', 'register.php', 'Register - MPSS', 'User registration page', 'fa-user-plus', 1, 3, 0, NULL),
('Admin Dashboard', 'admin.php', 'CEO Dashboard - MPSS', 'CEO strategic dashboard', 'fa-chart-pie', 1, 4, 1, 'CEO'),
('Employee Management', 'employee.php', 'HR Manager - MPSS', 'HR manager employee management', 'fa-users', 1, 5, 1, 'CEO,Manager'),
('User Dashboard', 'user.php', 'Employee Portal - MPSS', 'Employee self-service portal', 'fa-user', 1, 6, 1, 'CEO,Manager,Employee');
