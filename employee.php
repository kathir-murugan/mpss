<?php
session_start();

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Manager") {
    header("Location: login.php");
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'mpss_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get logged in user info
$manager_name = $_SESSION['employee_name'] ?? 'Manager';
$manager_id = $_SESSION['user_id'] ?? 0;

// Create tables if they don't exist
try {
    // Employees table
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(50) NOT NULL,
        position VARCHAR(100) NOT NULL,
        base_salary DECIMAL(10,2) NOT NULL,
        kpi_score DECIMAL(5,2) DEFAULT 0,
        email VARCHAR(100),
        phone VARCHAR(20),
        join_date DATE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        check_in_time TIME,
        check_out_time TIME,
        total_hours DECIMAL(5,2),
        status ENUM('present', 'absent', 'late', 'half-day') DEFAULT 'present',
        notes TEXT,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (employee_id, date)
    )");

} catch(PDOException $e) {
    // Tables might already exist
}

// Handle POST requests
$message = '';
$message_type = '';

// Add Employee
if (isset($_POST['add_employee'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO employees (name, department, position, base_salary, email, phone, join_date) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['department'],
            $_POST['position'],
            $_POST['base_salary'],
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['join_date'] ?? date('Y-m-d')
        ]);
        $message = "Employee added successfully!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Update Employee
if (isset($_POST['update_employee'])) {
    try {
        $stmt = $pdo->prepare("UPDATE employees SET 
                               name = ?, department = ?, position = ?, 
                               base_salary = ?, email = ?, phone = ?, status = ?
                               WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['department'],
            $_POST['position'],
            $_POST['base_salary'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['status'],
            $_POST['employee_id']
        ]);
        $message = "Employee updated successfully!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Delete Employee
if (isset($_POST['delete_employee'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$_POST['employee_id']]);
        $message = "Employee deleted successfully!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Mark Attendance
if (isset($_POST['mark_attendance'])) {
    try {
        // Calculate total hours if both times provided
        $total_hours = null;
        if (!empty($_POST['check_in_time']) && !empty($_POST['check_out_time'])) {
            $in = strtotime($_POST['check_in_time']);
            $out = strtotime($_POST['check_out_time']);
            $total_hours = round(($out - $in) / 3600, 2);
        }

        // Check if attendance exists for this date
        $check = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
        $check->execute([$_POST['employee_id'], $_POST['attendance_date']]);
        
        if ($check->fetch()) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE attendance SET 
                                   check_in_time = ?, check_out_time = ?, 
                                   total_hours = ?, status = ?, notes = ?
                                   WHERE employee_id = ? AND date = ?");
            $stmt->execute([
                $_POST['check_in_time'] ?: null,
                $_POST['check_out_time'] ?: null,
                $total_hours,
                $_POST['attendance_status'],
                $_POST['notes'] ?? null,
                $_POST['employee_id'],
                $_POST['attendance_date']
            ]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO attendance 
                                   (employee_id, date, check_in_time, check_out_time, total_hours, status, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['employee_id'],
                $_POST['attendance_date'],
                $_POST['check_in_time'] ?: null,
                $_POST['check_out_time'] ?: null,
                $total_hours,
                $_POST['attendance_status'],
                $_POST['notes'] ?? null
            ]);
        }
        
        $message = "Attendance marked successfully!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get dashboard statistics
$stats = [
    'total_employees' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'late_today' => 0,
    'avg_kpi' => 0,
    'total_salary' => 0
];

try {
    // Total active employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    $stats['total_employees'] = $stmt->fetch()['count'] ?? 0;
    
    // Today's attendance
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY status");
    $stmt->execute([$today]);
    $attendance_today = $stmt->fetchAll();
    
    foreach ($attendance_today as $row) {
        if ($row['status'] == 'present') $stats['present_today'] = $row['count'];
        if ($row['status'] == 'late') $stats['late_today'] = $row['count'];
    }
    
    $stats['absent_today'] = $stats['total_employees'] - $stats['present_today'] - $stats['late_today'];
    
    // Average KPI
    $stmt = $pdo->query("SELECT AVG(kpi_score) as avg FROM employees WHERE kpi_score > 0");
    $stats['avg_kpi'] = round($stmt->fetch()['avg'] ?? 0, 1);
    
    // Total payroll
    $stmt = $pdo->query("SELECT SUM(base_salary) as total FROM employees WHERE status = 'active'");
    $stats['total_salary'] = $stmt->fetch()['total'] ?? 0;
    
} catch(PDOException $e) {
    // Tables might be empty
}

// Get all employees
$employees = [];
try {
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY id DESC");
    $employees = $stmt->fetchAll();
} catch(PDOException $e) {
    $employees = [];
}

// Get today's attendance details
$today_attendance = [];
try {
    $stmt = $pdo->prepare("SELECT a.*, e.name, e.department 
                           FROM attendance a 
                           JOIN employees e ON a.employee_id = e.id 
                           WHERE a.date = ? 
                           ORDER BY a.check_in_time DESC");
    $stmt->execute([date('Y-m-d')]);
    $today_attendance = $stmt->fetchAll();
} catch(PDOException $e) {
    $today_attendance = [];
}

// Get attendance history
$attendance_history = [];
try {
    $stmt = $pdo->query("SELECT a.*, e.name, e.department 
                         FROM attendance a 
                         JOIN employees e ON a.employee_id = e.id 
                         ORDER BY a.date DESC, a.check_in_time DESC 
                         LIMIT 50");
    $attendance_history = $stmt->fetchAll();
} catch(PDOException $e) {
    $attendance_history = [];
}

// Handle AJAX request for employee details
if (isset($_GET['action']) && $_GET['action'] == 'get_employee') {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        echo json_encode($employee ?: ['error' => 'Employee not found']);
    } else {
        echo json_encode(['error' => 'Invalid ID']);
    }
    exit;
}

// Handle report generation
$report_data = [];
if (isset($_GET['from_date']) && isset($_GET['to_date'])) {
    try {
        $stmt = $pdo->prepare("SELECT 
            e.id, e.name, e.department,
            COUNT(a.id) as total_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_days,
            SUM(a.total_hours) as total_hours
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id 
                AND a.date BETWEEN ? AND ?
            WHERE e.status = 'active'
            GROUP BY e.id
            ORDER BY e.name");
        $stmt->execute([$_GET['from_date'], $_GET['to_date']]);
        $report_data = $stmt->fetchAll();
    } catch(PDOException $e) {
        $report_data = [];
    }
}

// Function to get rating based on KPI
function getRating($score) {
    if ($score >= 90) return "Excellent";
    if ($score >= 75) return "Good";
    if ($score >= 60) return "Average";
    return "Needs Improvement";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: white;
            height: 70px;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--gray-100);
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .menu-btn {
            font-size: 20px;
            color: var(--gray-600);
            cursor: pointer;
            display: none;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            width: 260px;
            height: calc(100vh - 70px);
            background: white;
            box-shadow: var(--shadow);
            transition: left 0.3s;
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar.hide {
            left: -260px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--gray-600);
            cursor: pointer;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-item i {
            width: 20px;
        }

        .sidebar-item:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .sidebar-item.active {
            background: var(--primary);
            color: white;
        }

        /* Main Content */
        .main {
            margin-left: 260px;
            padding: 90px 30px 30px;
            transition: margin-left 0.3s;
        }

        .main.expand {
            margin-left: 0;
        }

        /* Sections */
        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            color: white;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .page-header p {
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { background: #e0e7ff; color: var(--primary); }
        .stat-icon.green { background: #d1fae5; color: var(--success); }
        .stat-icon.red { background: #fee2e2; color: var(--danger); }
        .stat-icon.yellow { background: #fed7aa; color: var(--warning); }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray-500);
            font-size: 13px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-800);
        }

        .card-header h3 i {
            color: var(--primary);
        }

        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            background: var(--gray-50);
            padding: 12px 15px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        td {
            padding: 12px 15px;
            font-size: 13px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-600);
        }

        tr:hover td {
            background: var(--gray-50);
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge.excellent { background: #d1fae5; color: #059669; }
        .badge.good { background: #dbeafe; color: #2563eb; }
        .badge.average { background: #fed7aa; color: #b45309; }
        .badge.poor { background: #fee2e2; color: #b91c1c; }
        .badge.active { background: #d1fae5; color: #059669; }
        .badge.inactive { background: #fee2e2; color: #b91c1c; }
        .badge.present { background: #d1fae5; color: #059669; }
        .badge.late { background: #fed7aa; color: #b45309; }
        .badge.absent { background: #fee2e2; color: #b91c1c; }
        .badge.half-day { background: #dbeafe; color: #2563eb; }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: all 0.3s;
        }

        .action-btn.edit { background: #dbeafe; color: #2563eb; }
        .action-btn.delete { background: #fee2e2; color: #b91c1c; }
        .action-btn.view { background: #e0e7ff; color: var(--primary); }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Alerts */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            animation: slideIn 0.3s;
        }

        .alert.success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #059669;
        }

        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #b91c1c;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .close-modal {
            font-size: 20px;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-300);
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .menu-btn {
                display: block;
            }
            
            .sidebar {
                left: -260px;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Date Filter */
        .date-filter {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        /* Print Styles */
        @media print {
            .navbar, .sidebar, .btn, .action-btns, .card-header .btn {
                display: none;
            }
            
            .main {
                margin-left: 0;
                padding: 20px;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <div class="logo">
        <i class="fas fa-chart-line"></i>
        <span>MPSS Manager</span>
    </div>
    
    <div class="user-menu">
        <div class="user-profile" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <?= strtoupper(substr($manager_name, 0, 2)) ?>
            </div>
            <span><?= htmlspecialchars($manager_name) ?></span>
        </div>
        <div class="menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-menu">
        <div class="sidebar-item active" data-section="dashboard">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </div>
        <div class="sidebar-item" data-section="employees">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </div>
        <div class="sidebar-item" data-section="attendance">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
        </div>
        <div class="sidebar-item" data-section="reports">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </div>
        <div class="sidebar-item" onclick="window.location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main" id="main">

    <!-- Alert Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>" id="alertMessage">
            <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
            <span style="margin-left: auto; cursor: pointer;" onclick="this.parentElement.remove()">×</span>
        </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <div id="dashboard-section" class="section active">
        <div class="page-header">
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?= htmlspecialchars($manager_name) ?>! Here's what's happening today.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total_employees'] ?></h3>
                    <p>Total Employees</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['present_today'] ?></h3>
                    <p>Present Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['absent_today'] ?></h3>
                    <p>Absent Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['avg_kpi'] ?>%</h3>
                    <p>Avg KPI Score</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Today's Attendance</h3>
                <span class="badge" style="background: var(--gray-100);"><?= date('d M Y') ?></span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($today_attendance)): ?>
                            <?php foreach ($today_attendance as $att): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($att['name'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($att['department'] ?? '') ?></td>
                                    <td><?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '-' ?></td>
                                    <td><?= $att['check_out_time'] ? date('h:i A', strtotime($att['check_out_time'])) : '-' ?></td>
                                    <td><?= $att['total_hours'] ? $att['total_hours'] . 'h' : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $att['status'] ?? '' ?>">
                                            <?= ucfirst($att['status'] ?? '') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No attendance records for today</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Employees Section -->
    <div id="employees-section" class="section">
        <div class="page-header">
            <h1>Employee Management</h1>
            <p>Add, edit, and manage employee records</p>
        </div>

        <!-- Add Employee Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Add New Employee</h3>
            </div>
            <form method="post" class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" required>
                        <option value="">Select Department</option>
                        <option value="IT">Information Technology</option>
                        <option value="HR">Human Resources</option>
                        <option value="Sales">Sales</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Finance">Finance</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position *</label>
                    <input type="text" name="position" required placeholder="e.g. Senior Developer">
                </div>
                <div class="form-group">
                    <label>Base Salary (₹) *</label>
                    <input type="number" name="base_salary" required placeholder="Enter monthly salary">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="employee@company.com">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="+91 98765 43210">
                </div>
                <div class="form-group">
                    <label>Join Date</label>
                    <input type="date" name="join_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="add_employee" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Employee
                    </button>
                </div>
            </form>
        </div>

        <!-- Employee List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Employee Directory</h3>
                <span class="badge" style="background: var(--gray-100);"><?= count($employees) ?> Records</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>KPI</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employees)): ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td>#<?= $emp['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($emp['department']) ?></td>
                                    <td><?= htmlspecialchars($emp['position']) ?></td>
                                    <td>₹<?= number_format($emp['base_salary']) ?></td>
                                    <td>
                                        <?php if (!empty($emp['kpi_score'])): ?>
                                            <span class="badge <?= strtolower(getRating($emp['kpi_score'])) ?>">
                                                <?= $emp['kpi_score'] ?>%
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $emp['status'] ?>">
                                            <?= ucfirst($emp['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn view" onclick="viewEmployee(<?= $emp['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn edit" onclick="editEmployee(<?= $emp['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete" onclick="deleteEmployee(<?= $emp['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No employees found. Add your first employee above.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attendance Section -->
    <div id="attendance-section" class="section">
        <div class="page-header">
            <h1>Attendance Management</h1>
            <p>Track and manage employee attendance</p>
        </div>

        <!-- Mark Attendance Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-pen"></i> Mark Attendance</h3>
            </div>
            <form method="post" class="form-grid">
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="employee_id" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <?php if ($emp['status'] == 'active'): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="attendance_status" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="half-day">Half Day</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Check In Time</label>
                    <input type="time" name="check_in_time">
                </div>
                <div class="form-group">
                    <label>Check Out Time</label>
                    <input type="time" name="check_out_time">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="mark_attendance" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </form>
        </div>

        <!-- Attendance History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Attendance</h3>
                <span class="badge" style="background: var(--gray-100);">Last 50 Records</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendance_history)): ?>
                            <?php foreach ($attendance_history as $att): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($att['date'])) ?></td>
                                    <td><strong><?= htmlspecialchars($att['name'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($att['department'] ?? '') ?></td>
                                    <td><?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '-' ?></td>
                                    <td><?= $att['check_out_time'] ? date('h:i A', strtotime($att['check_out_time'])) : '-' ?></td>
                                    <td><?= $att['total_hours'] ? $att['total_hours'] . 'h' : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $att['status'] ?? '' ?>">
                                            <?= ucfirst($att['status'] ?? '') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No attendance records found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <div id="reports-section" class="section">
        <div class="page-header">
            <h1>Attendance Reports</h1>
            <p>Generate and view attendance reports</p>
        </div>

        <!-- Date Filter -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Select Date Range</h3>
            </div>
            <form method="get" class="date-filter">
                <div class="form-group" style="flex: 1;">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?= $_GET['from_date'] ?? date('Y-m-01') ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?= $_GET['to_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Generate Report
                    </button>
                </div>
                <?php if (!empty($report_data)): ?>
                    <div class="form-group">
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                        <button type="button" class="btn" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Report Results -->
        <?php if (!empty($report_data)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> Attendance Report</h3>
                    <span class="badge" style="background: var(--gray-100);">
                        <?= date('d M Y', strtotime($_GET['from_date'])) ?> - <?= date('d M Y', strtotime($_GET['to_date'])) ?>
                    </span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Present</th>
                                <th>Late</th>
                                <th>Half Day</th>
                                <th>Total Days</th>
                                <th>Total Hours</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $working_days = count(array_unique(array_column($attendance_history, 'date')));
                            $working_days = max($working_days, 1);
                            ?>
                            <?php foreach ($report_data as $row): ?>
                                <?php 
                                $total_days = $row['present_days'] + $row['late_days'] + $row['half_days'];
                                $attendance_percent = round(($total_days / $working_days) * 100);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['department']) ?></td>
                                    <td style="color: var(--success); font-weight: bold;"><?= $row['present_days'] ?? 0 ?></td>
                                    <td style="color: var(--warning); font-weight: bold;"><?= $row['late_days'] ?? 0 ?></td>
                                    <td style="color: var(--info); font-weight: bold;"><?= $row['half_days'] ?? 0 ?></td>
                                    <td><?= $total_days ?></td>
                                    <td><?= $row['total_hours'] ? round($row['total_hours'], 1) . 'h' : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $attendance_percent >= 90 ? 'excellent' : ($attendance_percent >= 75 ? 'good' : ($attendance_percent >= 60 ? 'average' : 'poor')) ?>">
                                            <?= $attendance_percent ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Employee</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="employee_id" id="edit_id">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Department *</label>
                <select name="department" id="edit_department" required>
                    <option value="IT">IT</option>
                    <option value="HR">HR</option>
                    <option value="Sales">Sales</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Finance">Finance</option>
                    <option value="Operations">Operations</option>
                </select>
            </div>
            <div class="form-group">
                <label>Position *</label>
                <input type="text" name="position" id="edit_position" required>
            </div>
            <div class="form-group">
                <label>Base Salary (₹) *</label>
                <input type="number" name="base_salary" id="edit_salary" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" id="edit_phone">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="btn-group">
                <button type="submit" name="update_employee" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Employee
                </button>
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Employee Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-trash"></i> Confirm Delete</h3>
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
        </div>
        <p style="margin: 20px 0; text-align: center;">Are you sure you want to delete this employee? This action cannot be undone.</p>
        <form method="post" style="text-align: center;">
            <input type="hidden" name="employee_id" id="delete_id">
            <div class="btn-group" style="justify-content: center;">
                <button type="submit" name="delete_employee" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
                <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Employee Modal -->
<div class="modal" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> Employee Details</h3>
            <span class="close-modal" onclick="closeViewModal()">&times;</span>
        </div>
        <div id="viewContent" style="line-height: 2;">
            Loading...
        </div>
    </div>
</div>

<script>
// Sidebar Toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('hide');
    document.getElementById('main').classList.toggle('expand');
}

// Section Switching
document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
    item.addEventListener('click', function() {
        // Remove active from all sidebar items
        document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        // Hide all sections
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        
        // Show selected section
        const sectionId = this.dataset.section + '-section';
        document.getElementById(sectionId).classList.add('active');
        
        // Close sidebar on mobile
        if (window.innerWidth <= 768) {
            toggleSidebar();
        }
    });
});

// View Employee
function viewEmployee(id) {
    const modal = document.getElementById('viewModal');
    const content = document.getElementById('viewContent');
    
    content.innerHTML = '<div style="text-align: center;"><i class="fas fa-spinner fa-spin" style="font-size: 30px;"></i><br>Loading...</div>';
    modal.style.display = 'flex';
    
    fetch('?action=get_employee&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="color: var(--danger);">Error: ' + data.error + '</p>';
            } else {
                content.innerHTML = `
                    <div style="display: grid; gap: 10px;">
                        <p><strong>ID:</strong> #${data.id}</p>
                        <p><strong>Name:</strong> ${data.name}</p>
                        <p><strong>Department:</strong> ${data.department}</p>
                        <p><strong>Position:</strong> ${data.position}</p>
                        <p><strong>Base Salary:</strong> ₹${parseInt(data.base_salary).toLocaleString()}</p>
                        <p><strong>KPI Score:</strong> ${data.kpi_score || 'Not set'}</p>
                        <p><strong>Email:</strong> ${data.email || 'Not provided'}</p>
                        <p><strong>Phone:</strong> ${data.phone || 'Not provided'}</p>
                        <p><strong>Join Date:</strong> ${data.join_date || 'Not set'}</p>
                        <p><strong>Status:</strong> <span class="badge ${data.status}">${data.status}</span></p>
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="color: var(--danger);">Error loading employee details</p>';
        });
}

// Edit Employee
function editEmployee(id) {
    fetch('?action=get_employee&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_department').value = data.department;
                document.getElementById('edit_position').value = data.position;
                document.getElementById('edit_salary').value = data.base_salary;
                document.getElementById('edit_email').value = data.email || '';
                document.getElementById('edit_phone').value = data.phone || '';
                document.getElementById('edit_status').value = data.status;
                
                document.getElementById('editModal').style.display = 'flex';
            }
        })
        .catch(error => {
            alert('Error loading employee data');
        });
}

// Delete Employee
function deleteEmployee(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

// Close Modals
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Auto-hide alert after 5 seconds
setTimeout(() => {
    const alert = document.getElementById('alertMessage');
    if (alert) {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);

// Export to Excel
function exportToExcel() {
    const from = document.querySelector('[name="from_date"]')?.value || '';
    const to = document.querySelector('[name="to_date"]')?.value || '';
    window.location.href = 'export_report.php?from=' + from + '&to=' + to;
}

// Toggle User Menu
function toggleUserMenu() {
    // Implement user menu dropdown
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key closes modals
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
        closeViewModal();
    }
});

// Responsive sidebar
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('hide');
        document.getElementById('main').classList.remove('expand');
    }
});
</script>

</body>
</html>