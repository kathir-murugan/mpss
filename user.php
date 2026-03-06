<?php
session_start();

// Check session and role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Employee") {
    // Redirect to login page if not authenticated
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['role'] ?? 'Employee';

// You would typically fetch real data from database here
// For now, we'll use empty arrays that will be populated from your database
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3730a3;
            --primary-light: #818cf8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning-light: #fed7aa;
            --danger-light: #fee2e2;
            --info-light: #dbeafe;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* Main Container */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
        }

        .header-left p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .date-time {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            color: white;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .notification-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem 0.5rem 0.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-info {
            color: white;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-role {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        /* Quick Actions Bar */
        .quick-actions-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
            flex-wrap: wrap;
        }

        .quick-action-btn {
            flex: 1;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            color: white;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }

        .stat-icon.present {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-icon.tasks {
            background: var(--info-light);
            color: var(--info);
        }

        .stat-icon.hours {
            background: var(--warning-light);
            color: var(--warning);
        }

        .stat-icon.kpi {
            background: #f3e8ff;
            color: #9333ea;
        }

        .stat-content h3 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Check-in Card */
        .checkin-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .checkin-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .checkin-time {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: var(--primary-light);
            border-radius: var(--radius-lg);
            color: white;
        }

        .checkin-time .time {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .checkin-time .label {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .checkin-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 3px var(--success-light);
        }

        .checkin-status span {
            font-weight: 600;
            color: var(--gray-700);
        }

        .checkin-status small {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .checkin-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(67, 97, 238, 0.3);
        }

        .btn-outline {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Main Grid Layout */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h2 i {
            color: var(--primary);
        }

        .card-badge {
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        /* Task List */
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .task-item:hover {
            background: var(--gray-200);
            transform: translateX(5px);
        }

        .task-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .priority-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .priority-high {
            background: var(--danger);
            box-shadow: 0 0 0 3px var(--danger-light);
        }

        .priority-medium {
            background: var(--warning);
            box-shadow: 0 0 0 3px var(--warning-light);
        }

        .priority-low {
            background: var(--success);
            box-shadow: 0 0 0 3px var(--success-light);
        }

        .task-details h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .task-meta i {
            margin-right: 0.25rem;
        }

        .task-progress {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .progress-bar {
            width: 100px;
            height: 6px;
            background: var(--gray-300);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
        }

        .task-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }

        .status-completed {
            background: var(--success-light);
            color: var(--success);
        }

        .status-in_progress {
            background: var(--info-light);
            color: var(--info);
        }

        .status-pending {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Profile Info */
        .profile-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            position: relative;
            border: 4px solid var(--primary-light);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--success);
            border: 3px solid white;
        }

        .profile-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .profile-header p {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-500);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label i {
            width: 16px;
            color: var(--primary);
        }

        .info-value {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.875rem;
        }

        /* Secondary Grid */
        .secondary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Leave Grid */
        .leave-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .leave-card {
            text-align: center;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--radius);
        }

        .leave-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .leave-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        /* Performance Card */
        .kpi-container {
            margin-bottom: 1.5rem;
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .kpi-bar {
            height: 8px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }

        .kpi-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
        }

        .performance-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
        }

        .perf-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .perf-stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Action Message */
        .action-message {
            background: var(--success-light);
            color: var(--success);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .action-message i {
            font-size: 1.25rem;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Footer */
        .footer {
            margin-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            padding: 1rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--gray-800);
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Notification List */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .notification-item.unread {
            background: var(--primary-light);
            color: white;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .icon-success {
            background: var(--success-light);
            color: var(--success);
        }

        .icon-info {
            background: var(--info-light);
            color: var(--info);
        }

        .icon-warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            font-size: 0.75rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Salary Modal */
        .salary-summary {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .salary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px dashed var(--gray-200);
        }

        .salary-row:last-child {
            border-bottom: none;
        }

        .salary-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--gray-200);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }

            .header-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .quick-actions-bar {
                flex-direction: column;
            }

            .quick-action-btn {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .checkin-card {
                flex-direction: column;
                text-align: center;
            }

            .checkin-info {
                flex-direction: column;
                gap: 1rem;
            }

            .checkin-buttons {
                width: 100%;
                flex-direction: column;
            }

            .checkin-buttons .btn {
                width: 100%;
            }

            .secondary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-left">
                <h1>Welcome back, <?= htmlspecialchars(explode(' ', $employee_name)[0]) ?>! 👋</h1>
                <p>Here's what's happening with your work today</p>
            </div>
            <div class="header-right">
                <div class="date-time" id="liveDateTime">
                    <?= date('l, F j, Y - h:i A') ?>
                </div>
                <div class="notification-btn" onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </div>
                <div class="user-profile" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?= strtoupper(substr($employee_name, 0, 2)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($employee_name) ?></div>
                        <div class="user-role"><?= htmlspecialchars($employee_role) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions-bar">
            <button class="quick-action-btn" onclick="loadSalaryDetails()">
                <i class="fas fa-file-invoice"></i>
                <span>Salary Slip</span>
            </button>
            <button class="quick-action-btn" onclick="applyLeave()">
                <i class="fas fa-calendar-minus"></i>
                <span>Apply Leave</span>
            </button>
            <button class="quick-action-btn" onclick="requestWFH()">
                <i class="fas fa-home"></i>
                <span>Request WFH</span>
            </button>
            <button class="quick-action-btn" onclick="viewTeam()">
                <i class="fas fa-users"></i>
                <span>Team</span>
            </button>
            <button class="quick-action-btn" onclick="contactHR()">
                <i class="fas fa-headset"></i>
                <span>Contact HR</span>
            </button>
        </div>

        <!-- Action Message Container -->
        <div id="actionMessage" style="display: none;"></div>

        <!-- Check-in Card -->
        <div class="checkin-card">
            <div class="checkin-info">
                <div class="checkin-time">
                    <span class="time" id="currentTime">--:--:--</span>
                    <span class="label">Current Time</span>
                </div>
                <div class="checkin-status" id="checkinStatus">
                    <div class="status-indicator" id="statusIndicator"></div>
                    <div id="statusText">
                        <span>Not checked in</span>
                        <br>
                        <small>Click Check In to start</small>
                    </div>
                </div>
            </div>
            <div class="checkin-buttons">
                <button type="button" onclick="handleCheckIn()" class="btn btn-primary" id="checkInBtn">
                    <i class="fas fa-sign-in-alt"></i> Check In
                </button>
                <button type="button" onclick="handleCheckOut()" class="btn btn-outline" id="checkOutBtn">
                    <i class="fas fa-sign-out-alt"></i> Check Out
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid" id="statsGrid">
            <!-- Stats will be loaded dynamically -->
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3 id="presentDays">0</h3>
                    <p>Present Days</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon tasks">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <h3 id="completedTasks">0</h3>
                    <p>Tasks Completed</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon hours">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3 id="monthlyHours">0</h3>
                    <p>Monthly Hours</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon kpi">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3 id="kpiScore">0%</h3>
                    <p>KPI Score</p>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Tasks Card -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-tasks"></i>
                        My Tasks
                    </h2>
                    <span class="card-badge" id="pendingTasksCount">0 pending</span>
                </div>
                <div class="task-list" id="taskList">
                    <!-- Tasks will be loaded dynamically -->
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No tasks assigned</p>
                    </div>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($employee_name) ?>&size=150&background=4361ee&color=fff&bold=true" alt="Profile">
                        <div class="profile-status"></div>
                    </div>
                    <h3 id="employeeName"><?= htmlspecialchars($employee_name) ?></h3>
                    <p id="employeePosition"><?= htmlspecialchars($employee_role) ?></p>
                </div>

                <div class="info-list" id="employeeInfo">
                    <!-- Employee info will be loaded dynamically -->
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-id-card"></i> Employee ID</span>
                        <span class="info-value" id="empId"><?= htmlspecialchars($employee_id) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-building"></i> Department</span>
                        <span class="info-value" id="empDepartment">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                        <span class="info-value" id="empEmail">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                        <span class="info-value" id="empPhone">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user-tie"></i> Manager</span>
                        <span class="info-value" id="empManager">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Grid -->
        <div class="secondary-grid">
            <!-- Leave Balance Card -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-calendar-minus"></i>
                        Leave Balance
                    </h2>
                    <span class="card-badge" id="totalLeaveDays">0 days left</span>
                </div>
                <div class="leave-grid" id="leaveBalance">
                    <!-- Leave balance will be loaded dynamically -->
                    <div class="leave-card">
                        <div class="leave-count">0</div>
                        <div class="leave-label">Annual</div>
                    </div>
                    <div class="leave-card">
                        <div class="leave-count">0</div>
                        <div class="leave-label">Sick</div>
                    </div>
                    <div class="leave-card">
                        <div class="leave-count">0</div>
                        <div class="leave-label">Casual</div>
                    </div>
                </div>
            </div>

            <!-- Performance Card -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Performance
                    </h2>
                    <span class="card-badge" id="performanceRating">-</span>
                </div>
                <div class="kpi-container">
                    <div class="kpi-header">
                        <span>KPI Score</span>
                        <span id="kpiScoreValue">0%</span>
                    </div>
                    <div class="kpi-bar">
                        <div class="kpi-fill" id="kpiBar" style="width: 0%;"></div>
                    </div>
                </div>
                <div class="performance-stats">
                    <div>
                        <div class="perf-stat-value" id="completionRate">0%</div>
                        <div class="perf-stat-label">Completion</div>
                    </div>
                    <div>
                        <div class="perf-stat-value" id="attendanceRate">0%</div>
                        <div class="perf-stat-label">Attendance</div>
                    </div>
                    <div>
                        <div class="perf-stat-value" id="managerRating">0</div>
                        <div class="perf-stat-label">Rating</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© 2026 Employee Portal. All rights reserved. | Version 2.0</p>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal" id="notificationsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <span class="close-modal" onclick="closeModal('notificationsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="notification-list" id="notificationList">
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Details Modal -->
    <div class="modal" id="salaryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-wallet"></i> Salary Details</h2>
                <span class="close-modal" onclick="closeModal('salaryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="salary-summary" id="salaryDetails">
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>Loading salary details...</p>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button class="btn btn-primary" style="flex: 1;" onclick="downloadSalarySlip()">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="btn btn-outline" style="flex: 1;" onclick="printSalarySlip()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live time update
        function updateDateTime() {
            const now = new Date();
            
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeStr;
            
            const dateTimeStr = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('liveDateTime').textContent = dateTimeStr;
        }

        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Show action message
        function showActionMessage(message, type = 'success') {
            const messageDiv = document.getElementById('actionMessage');
            messageDiv.innerHTML = `
                <div class="action-message" style="background: ${type === 'success' ? 'var(--success-light)' : 'var(--danger-light)'}; color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }

        // Handle Check In
        function handleCheckIn() {
            const checkInBtn = document.getElementById('checkInBtn');
            checkInBtn.innerHTML = '<span class="loading-spinner"></span> Checking in...';
            checkInBtn.disabled = true;
            
            // Make AJAX call to your backend
            fetch('api/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'check_in' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showActionMessage(data.message);
                    updateCheckinStatus(data);
                } else {
                    showActionMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showActionMessage('Error checking in. Please try again.', 'error');
            })
            .finally(() => {
                checkInBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
                checkInBtn.disabled = false;
            });
        }

        // Handle Check Out
        function handleCheckOut() {
            const checkOutBtn = document.getElementById('checkOutBtn');
            checkOutBtn.innerHTML = '<span class="loading-spinner"></span> Checking out...';
            checkOutBtn.disabled = true;
            
            // Make AJAX call to your backend
            fetch('api/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'check_out' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showActionMessage(data.message);
                    updateCheckinStatus(data);
                } else {
                    showActionMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showActionMessage('Error checking out. Please try again.', 'error');
            })
            .finally(() => {
                checkOutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
                checkOutBtn.disabled = false;
            });
        }

        // Update check-in status
        function updateCheckinStatus(data) {
            const statusIndicator = document.getElementById('statusIndicator');
            const statusText = document.getElementById('statusText');
            
            if (data.status === 'checked_in') {
                statusIndicator.style.background = 'var(--success)';
                statusIndicator.style.boxShadow = '0 0 0 3px var(--success-light)';
                statusText.innerHTML = `
                    <span>Checked in at ${data.check_in_time}</span>
                    <br>
                    <small>Working for ${data.working_hours}</small>
                `;
            } else {
                statusIndicator.style.background = 'var(--gray-400)';
                statusIndicator.style.boxShadow = '0 0 0 3px var(--gray-200)';
                statusText.innerHTML = `
                    <span>Not checked in</span>
                    <br>
                    <small>Click Check In to start</small>
                `;
            }
        }

        // Modal functions
        function showNotifications() {
            document.getElementById('notificationsModal').style.display = 'flex';
            loadNotifications();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function loadSalaryDetails() {
            document.getElementById('salaryModal').style.display = 'flex';
            
            // Load salary details via AJAX
            fetch('api/salary.php')
                .then(response => response.json())
                .then(data => {
                    const salaryDetails = document.getElementById('salaryDetails');
                    if (data.length === 0) {
                        salaryDetails.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-file-invoice"></i>
                                <p>No salary details available</p>
                            </div>
                        `;
                    } else {
                        let html = '';
                        data.forEach(item => {
                            html += `
                                <div class="salary-row">
                                    <span>${item.label}</span>
                                    <span>${item.value}</span>
                                </div>
                            `;
                        });
                        salaryDetails.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error loading salary details:', error);
                });
        }

        function loadNotifications() {
            // Load notifications via AJAX
            fetch('api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const notificationList = document.getElementById('notificationList');
                    const badge = document.getElementById('notificationBadge');
                    
                    if (data.length === 0) {
                        notificationList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications</p>
                            </div>
                        `;
                        badge.style.display = 'none';
                    } else {
                        let html = '';
                        let unreadCount = 0;
                        
                        data.forEach(notif => {
                            if (!notif.read) unreadCount++;
                            html += `
                                <div class="notification-item ${!notif.read ? 'unread' : ''}">
                                    <div class="notification-icon icon-${notif.type}">
                                        <i class="fas fa-${notif.icon}"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>${notif.message}</p>
                                        <div class="notification-time">
                                            <i class="far fa-clock"></i> ${notif.time}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        notificationList.innerHTML = html;
                        
                        if (unreadCount > 0) {
                            badge.textContent = unreadCount;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }

        // Load dashboard data
        function loadDashboardData() {
            // Load employee stats
            fetch('api/stats.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('presentDays').textContent = data.present_days;
                    document.getElementById('completedTasks').textContent = data.completed_tasks;
                    document.getElementById('monthlyHours').textContent = data.monthly_hours + 'h';
                    document.getElementById('kpiScore').textContent = data.kpi_score + '%';
                    document.getElementById('kpiScoreValue').textContent = data.kpi_score + '%';
                    document.getElementById('kpiBar').style.width = data.kpi_score + '%';
                    document.getElementById('completionRate').textContent = data.completion_rate + '%';
                    document.getElementById('attendanceRate').textContent = data.attendance_rate + '%';
                    document.getElementById('managerRating').textContent = data.manager_rating;
                    document.getElementById('performanceRating').textContent = data.performance_rating;
                });

            // Load tasks
            fetch('api/tasks.php')
                .then(response => response.json())
                .then(data => {
                    const taskList = document.getElementById('taskList');
                    const pendingCount = document.getElementById('pendingTasksCount');
                    
                    if (data.length === 0) {
                        taskList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <p>No tasks assigned</p>
                            </div>
                        `;
                        pendingCount.textContent = '0 pending';
                    } else {
                        let html = '';
                        let pending = 0;
                        
                        data.forEach(task => {
                            if (task.status !== 'completed') pending++;
                            html += `
                                <div class="task-item">
                                    <div class="task-info">
                                        <div class="priority-indicator priority-${task.priority}"></div>
                                        <div class="task-details">
                                            <h4>${task.title}</h4>
                                            <div class="task-meta">
                                                <span><i class="far fa-calendar"></i> ${task.deadline}</span>
                                                <span><i class="far fa-user"></i> ${task.assigned_by}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="task-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: ${task.progress}%;"></div>
                                        </div>
                                        <span class="task-status status-${task.status}">
                                            ${task.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                            `;
                        });
                        
                        taskList.innerHTML = html;
                        pendingCount.textContent = pending + ' pending';
                    }
                });

            // Load employee info
            fetch('api/employee-info.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('empDepartment').textContent = data.department;
                    document.getElementById('empEmail').textContent = data.email;
                    document.getElementById('empPhone').textContent = data.phone;
                    document.getElementById('empManager').textContent = data.manager;
                    document.getElementById('employeePosition').textContent = data.position;
                });

            // Load leave balance
            fetch('api/leave-balance.php')
                .then(response => response.json())
                .then(data => {
                    const leaveGrid = document.getElementById('leaveBalance');
                    const totalDays = document.getElementById('totalLeaveDays');
                    
                    let html = '';
                    let total = 0;
                    
                    data.forEach(item => {
                        total += item.remaining;
                        html += `
                            <div class="leave-card">
                                <div class="leave-count">${item.remaining}</div>
                                <div class="leave-label">${item.type}</div>
                            </div>
                        `;
                    });
                    
                    leaveGrid.innerHTML = html;
                    totalDays.textContent = total + ' days left';
                });

            // Load check-in status
            fetch('api/attendance-status.php')
                .then(response => response.json())
                .then(data => {
                    updateCheckinStatus(data);
                });
        }

        // Load data on page load
        document.addEventListener('DOMContentLoaded', loadDashboardData);

        // Refresh data every 5 minutes
        setInterval(loadDashboardData, 300000);

        // Quick action functions
        function applyLeave() {
            window.location.href = 'apply-leave.php';
        }

        function requestWFH() {
            window.location.href = 'request-wfh.php';
        }

        function viewTeam() {
            window.location.href = 'team.php';
        }

        function contactHR() {
            window.location.href = 'contact-hr.php';
        }

        function downloadSalarySlip() {
            window.location.href = 'download-salary.php';
        }

        function printSalarySlip() {
            window.print();
        }

        function toggleUserMenu() {
            // Implement user menu dropdown
            console.log('Toggle user menu');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>