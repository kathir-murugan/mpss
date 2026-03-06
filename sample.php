<?php
session_start();

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'employee_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// ==================== DATABASE SETUP ====================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Create users table with roles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('ceo', 'manager', 'employee') NOT NULL,
            department VARCHAR(50),
            position VARCHAR(100),
            manager_id INT NULL,
            profile_pic VARCHAR(255) DEFAULT 'default.jpg',
            last_login DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_role (role),
            INDEX idx_manager (manager_id)
        )
    ");
    
    // Create login_history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            login_time DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('success', 'failed') DEFAULT 'success',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Check if default users exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Create default users with hashed passwords
        $default_users = [
            // CEO
            ['ceo', 'ceo123', 'ceo@company.com', 'John Smith', 'ceo', 'Executive', 'Chief Executive Officer', null],
            // Managers
            ['it_manager', 'manager123', 'it.manager@company.com', 'Alice Johnson', 'manager', 'IT', 'IT Manager', null],
            ['hr_manager', 'manager123', 'hr.manager@company.com', 'Bob Wilson', 'manager', 'HR', 'HR Manager', null],
            ['sales_manager', 'manager123', 'sales.manager@company.com', 'Carol Brown', 'manager', 'Sales', 'Sales Manager', null],
            ['finance_manager', 'manager123', 'finance.manager@company.com', 'David Lee', 'manager', 'Finance', 'Finance Manager', null],
            // Employees
            ['john.doe', 'emp123', 'john.doe@company.com', 'John Doe', 'employee', 'IT', 'Developer', 2],
            ['jane.smith', 'emp123', 'jane.smith@company.com', 'Jane Smith', 'employee', 'HR', 'HR Executive', 3],
            ['mike.johnson', 'emp123', 'mike.johnson@company.com', 'Mike Johnson', 'employee', 'Sales', 'Sales Executive', 4],
            ['sarah.williams', 'emp123', 'sarah.williams@company.com', 'Sarah Williams', 'employee', 'IT', 'Senior Developer', 2],
            ['david.brown', 'emp123', 'david.brown@company.com', 'David Brown', 'employee', 'Sales', 'Sales Representative', 4],
            ['emily.davis', 'emp123', 'emily.davis@company.com', 'Emily Davis', 'employee', 'Finance', 'Accountant', 5]
        ];
        
        $insert = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($default_users as $user) {
            $hashed_password = password_hash($user[1], PASSWORD_DEFAULT);
            $insert->execute([$user[0], $hashed_password, $user[2], $user[3], $user[4], $user[5], $user[6], $user[7]]);
        }
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==================== LOGIN HANDLER ====================
$error = '';
$success = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Get user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        
        // Update last login
        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        
        // Log login history
        $log = $pdo->prepare("INSERT INTO login_history (user_id, login_time, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
        $log->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
        
        // Set remember me cookie (30 days)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/');
            // Store token in database (you'd need a remember_tokens table for this)
        }
        
        // Redirect based on role
        switch($user['role']) {
            case 'ceo':
                header("Location: ?page=ceo_dashboard");
                break;
            case 'manager':
                header("Location: ?page=manager_dashboard");
                break;
            case 'employee':
                header("Location: ?page=employee_dashboard");
                break;
        }
        exit();
    } else {
        $error = "Invalid username or password";
        
        // Log failed attempt
        $log = $pdo->prepare("INSERT INTO login_history (user_id, login_time, ip_address, user_agent, status) VALUES (NULL, NOW(), ?, ?, 'failed')");
        $log->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
    }
}

// ==================== LOGOUT HANDLER ====================
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=home");
    exit();
}

// ==================== CHECK IF LOGGED IN ====================
$current_user = null;
$page = $_GET['page'] ?? 'home';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user) {
        session_destroy();
    }
}

// ==================== HOME PAGE ====================
if ($page == 'home'):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPSS - Employee Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #1e3a8a 100%);
            animation: gradientBG 15s ease infinite;
            background-size: 400% 400%;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e3a8a;
        }
        
        .logo i {
            color: #fbbf24;
            margin-right: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #1e3a8a;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white !important;
            padding: 10px 25px;
            border-radius: 30px;
            transition: transform 0.3s !important;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            color: white !important;
        }
        
        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 0 5%;
            margin-top: 60px;
        }
        
        .hero-content {
            flex: 1;
            color: white;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
        }
        
        .btn {
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #fbbf24;
            color: #1e3a8a;
        }
        
        .btn-primary:hover {
            background: #f59e0b;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .btn-outline {
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #1e3a8a;
            transform: translateY(-3px);
        }
        
        .hero-image {
            flex: 1;
            text-align: center;
        }
        
        .hero-image img {
            max-width: 80%;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        /* Features Section */
        .features {
            padding: 80px 5%;
            background: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: #1e3a8a;
            margin-bottom: 15px;
        }
        
        .section-title p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: #f8fafc;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        
        .feature-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 15px;
        }
        
        .feature-card p {
            color: #64748b;
            line-height: 1.6;
        }
        
        .feature-link {
            display: inline-block;
            margin-top: 20px;
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Role Cards */
        .roles {
            padding: 80px 5%;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1000px;
            margin: 40px auto 0;
        }
        
        .role-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .role-card:hover {
            transform: translateY(-10px);
        }
        
        .role-card.ceo { border-top: 5px solid #fbbf24; }
        .role-card.manager { border-top: 5px solid #3b82f6; }
        .role-card.employee { border-top: 5px solid #10b981; }
        
        .role-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .role-card.ceo .role-icon { background: #fbbf24; }
        .role-card.manager .role-icon { background: #3b82f6; }
        .role-card.employee .role-icon { background: #10b981; }
        
        .role-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .role-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .role-card.ceo h3 { color: #fbbf24; }
        .role-card.manager h3 { color: #3b82f6; }
        .role-card.employee h3 { color: #10b981; }
        
        .role-card p {
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .role-btn {
            display: inline-block;
            padding: 10px 30px;
            border-radius: 30px;
            text-decoration: none;
            color: white;
            transition: transform 0.3s;
        }
        
        .role-card.ceo .role-btn { background: #fbbf24; }
        .role-card.manager .role-btn { background: #3b82f6; }
        .role-card.employee .role-btn { background: #10b981; }
        
        .role-btn:hover {
            transform: scale(1.05);
        }
        
        /* Stats Section */
        .stats {
            padding: 80px 5%;
            background: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1000px;
            margin: 40px auto 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #1e3a8a;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        
        /* Footer */
        .footer {
            background: #1e293b;
            color: white;
            padding: 60px 5% 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            color: #fbbf24;
        }
        
        .footer-section p {
            color: #cbd5e1;
            line-height: 1.6;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section ul li a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section ul li a:hover {
            color: #fbbf24;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .social-links a:hover {
            background: #fbbf24;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 40px;
            margin-top: 40px;
            border-top: 1px solid #334155;
            color: #94a3b8;
        }
        
        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 80px;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .cta-buttons {
                justify-content: center;
            }
            
            .roles-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <i class="fas fa-building"></i> MPSS
        </div>
        <div class="nav-links">
            <a href="?page=home">Home</a>
            <a href="#features">Features</a>
            <a href="#roles">Roles</a>
            <a href="#contact">Contact</a>
            <?php if ($current_user): ?>
                <a href="?page=dashboard" class="login-btn">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            <?php else: ?>
                <a href="?page=login" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Enterprise Employee Management System</h1>
            <p>Complete solution for CEO, Managers, and Employees. Streamline your workforce management with role-based access control.</p>
            <div class="cta-buttons">
                <?php if ($current_user): ?>
                    <a href="?page=dashboard" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="?page=login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Get Started
                    </a>
                <?php endif; ?>
                <a href="#features" class="btn btn-outline">
                    <i class="fas fa-play"></i> Watch Demo
                </a>
            </div>
        </div>
        <div class="hero-image">
            <i class="fas fa-chart-line" style="font-size: 15rem; color: rgba(255,255,255,0.2);"></i>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-title">
            <h2>Why Choose MPSS?</h2>
            <p>Comprehensive features for every role in your organization</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card" onclick="window.location.href='?page=ceo_features'">
                <div class="feature-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h3>CEO Dashboard</h3>
                <p>Strategic overview with company-wide analytics, department performance, and executive reports.</p>
                <a href="?page=ceo_features" class="feature-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="feature-card" onclick="window.location.href='?page=manager_features'">
                <div class="feature-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3>Manager Portal</h3>
                <p>Team management, task assignment, leave approvals, and performance tracking.</p>
                <a href="?page=manager_features" class="feature-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="feature-card" onclick="window.location.href='?page=employee_features'">
                <div class="feature-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3>Employee Portal</h3>
                <p>Check in/out, view tasks, request leave, access salary information and notifications.</p>
                <a href="?page=employee_features" class="feature-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>
    
    <!-- Roles Section -->
    <section class="roles" id="roles">
        <div class="section-title">
            <h2>Access Levels</h2>
            <p>Role-based dashboards tailored to your needs</p>
        </div>
        
        <div class="roles-grid">
            <div class="role-card ceo">
                <div class="role-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h3>CEO</h3>
                <p>Full company overview, strategic decisions, department analytics, and executive reports.</p>
                <a href="?page=login&role=ceo" class="role-btn">CEO Login</a>
            </div>
            
            <div class="role-card manager">
                <div class="role-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3>Manager</h3>
                <p>Team leadership, task management, leave approval, and performance reviews.</p>
                <a href="?page=login&role=manager" class="role-btn">Manager Login</a>
            </div>
            
            <div class="role-card employee">
                <div class="role-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3>Employee</h3>
                <p>Daily tasks, attendance, leave requests, and personal information management.</p>
                <a href="?page=login&role=employee" class="role-btn">Employee Login</a>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="stats">
        <div class="section-title">
            <h2>Trusted by Industry Leaders</h2>
            <p>Numbers that speak for themselves</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">500+</div>
                <div class="stat-label">Companies</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Employees</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">98%</div>
                <div class="stat-label">Satisfaction</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-building"></i> MPSS</h3>
                <p>Enterprise Employee Management System designed for modern organizations. Secure, scalable, and intuitive.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-github"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="?page=home">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#roles">Roles</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Resources</h3>
                <ul>
                    <li><a href="#">Documentation</a></li>
                    <li><a href="#">API Reference</a></li>
                    <li><a href="#">Support Center</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact Info</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> 123 Business Ave, NY</li>
                    <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                    <li><i class="fas fa-envelope"></i> info@mpss.com</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2026 MPSS Enterprises. All rights reserved. | Version 3.0</p>
        </div>
    </footer>
</body>
</html>
<?php
exit();

// ==================== LOGIN PAGE ====================
elseif ($page == 'login'):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MPSS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(-45deg, #667eea, #764ba2, #1e3a8a, #059669);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 450px;
        }
        
        .back-home {
            margin-bottom: 20px;
        }
        
        .back-home a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        
        .back-home a:hover {
            opacity: 1;
        }
        
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #1e3a8a;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #64748b;
        }
        
        .role-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 50px;
        }
        
        .role-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .role-tab.active {
            background: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .role-tab.ceo.active { color: #fbbf24; }
        .role-tab.manager.active { color: #3b82f6; }
        .role-tab.employee.active { color: #10b981; }
        
        .role-tab i {
            margin-right: 8px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #1e3a8a;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #4a5568;
        }
        
        .forgot a {
            color: #1e3a8a;
            text-decoration: none;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .demo-credentials {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #e2e8f0;
        }
        
        .demo-credentials h3 {
            color: #1e3a8a;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .cred-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .cred-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .cred-item .role {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cred-item .role.ceo { color: #fbbf24; }
        .cred-item .role.manager { color: #3b82f6; }
        .cred-item .role.employee { color: #10b981; }
        
        .cred-item .details {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .cred-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="back-home">
            <a href="?page=home"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="login-box">
            <div class="login-header">
                <h1><i class="fas fa-lock"></i> MPSS Login</h1>
                <p>Enter your credentials to access your dashboard</p>
            </div>
            
            <div class="role-tabs">
                <div class="role-tab ceo active" onclick="setRole('ceo')">
                    <i class="fas fa-crown"></i> CEO
                </div>
                <div class="role-tab manager" onclick="setRole('manager')">
                    <i class="fas fa-users-cog"></i> Manager
                </div>
                <div class="role-tab employee" onclick="setRole('employee')">
                    <i class="fas fa-user-tie"></i> Employee
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" id="username" placeholder="Enter your username" required>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-key"></i> Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
                
                <div class="remember-forgot">
                    <label class="remember">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <div class="forgot">
                        <a href="#">Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" name="login" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="demo-credentials">
                <h3>Demo Credentials</h3>
                <div class="cred-grid">
                    <div class="cred-item" onclick="fillCredentials('ceo', 'ceo123')">
                        <div class="role ceo">CEO</div>
                        <div class="details">ceo / ceo123</div>
                    </div>
                    <div class="cred-item" onclick="fillCredentials('it_manager', 'manager123')">
                        <div class="role manager">Manager</div>
                        <div class="details">it_manager / manager123</div>
                    </div>
                    <div class="cred-item" onclick="fillCredentials('john.doe', 'emp123')">
                        <div class="role employee">Employee</div>
                        <div class="details">john.doe / emp123</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function setRole(role) {
            document.querySelectorAll('.role-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update username placeholder based on role
            const usernameInput = document.getElementById('username');
            if (role === 'ceo') {
                usernameInput.placeholder = 'ceo';
            } else if (role === 'manager') {
                usernameInput.placeholder = 'it_manager';
            } else {
                usernameInput.placeholder = 'john.doe';
            }
        }
        
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
<?php
exit();

// ==================== CEO DASHBOARD ====================
elseif ($page == 'ceo_dashboard' && $current_user && $current_user['role'] == 'ceo'):
    // Get company statistics
    $total_employees = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
    $total_managers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
    $total_departments = $pdo->query("SELECT COUNT(DISTINCT department) FROM users WHERE department IS NOT NULL")->fetchColumn();
    
    // Recent logins
    $recent_logins = $pdo->query("
        SELECT l.*, u.username, u.full_name, u.role 
        FROM login_history l
        JOIN users u ON l.user_id = u.id
        WHERE l.status = 'success'
        ORDER BY l.login_time DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Department distribution
    $dept_stats = $pdo->query("
        SELECT department, COUNT(*) as count 
        FROM users 
        WHERE department IS NOT NULL 
        GROUP BY department
    ")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CEO Dashboard | MPSS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* CEO Dashboard Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1e3a8a, #312e81);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .logo i {
            color: #fbbf24;
            margin-right: 10px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            padding: 8px 20px;
            border-radius: 30px;
        }
        
        .role-badge {
            background: #fbbf24;
            color: #1e3a8a;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        .sidebar {
            width: 260px;
            background: white;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .sidebar-item:hover,
        .sidebar-item.active {
            background: #e0e7ff;
            color: #1e3a8a;
            border-left: 3px solid #fbbf24;
        }
        
        .main {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #1e3a8a, #312e81);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-info h3 {
            font-size: 2rem;
            color: #1e3a8a;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: #e0e7ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
            font-size: 1.5rem;
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            margin-bottom: 20px;
            color: #1e3a8a;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .back-home {
            display: inline-block;
            margin-bottom: 20px;
            color: #1e3a8a;
            text-decoration: none;
        }
        
        .back-home:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-crown"></i> MPSS - CEO Dashboard
        </div>
        <div class="user-menu">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?= htmlspecialchars($current_user['full_name']) ?></span>
                <span class="role-badge">CEO</span>
            </div>
            <a href="?page=home" class="logout-btn"><i class="fas fa-home"></i> Home</a>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="sidebar">
        <div class="sidebar-menu">
            <div class="sidebar-item active">
                <i class="fas fa-tachometer-alt"></i> Overview
            </div>
            <div class="sidebar-item">
                <i class="fas fa-users"></i> Employees
            </div>
            <div class="sidebar-item">
                <i class="fas fa-chart-line"></i> Analytics
            </div>
            <div class="sidebar-item">
                <i class="fas fa-building"></i> Departments
            </div>
            <div class="sidebar-item">
                <i class="fas fa-history"></i> Activity Log
            </div>
        </div>
    </div>
    
    <div class="main">
        <a href="?page=home" class="back-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
        
        <div class="welcome-card">
            <div>
                <h1>Welcome, <?= htmlspecialchars($current_user['full_name']) ?>!</h1>
                <p>Strategic overview of MPSS Enterprises</p>
            </div>
            <div>
                <p>Last Login: <?= $current_user['last_login'] ? date('d M Y H:i', strtotime($current_user['last_login'])) : 'First login' ?></p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $total_employees ?></h3>
                    <p>Total Employees</p>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $total_managers ?></h3>
                    <p>Managers</p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $total_departments ?></h3>
                    <p>Departments</p>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= count($recent_logins) ?></h3>
                    <p>Recent Logins</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-chart-pie"></i> Department Distribution</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($dept_stats as $dept): ?>
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                    <h3><?= htmlspecialchars($dept['department']) ?></h3>
                    <p style="font-size: 1.5rem; color: #1e3a8a;"><?= $dept['count'] ?></p>
                    <p>employees</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-history"></i> Recent Login Activity</h2>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Login Time</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logins as $login): ?>
                    <tr>
                        <td><?= htmlspecialchars($login['full_name']) ?></td>
                        <td><span style="color: <?= $login['role'] == 'ceo' ? '#fbbf24' : ($login['role'] == 'manager' ? '#3b82f6' : '#10b981') ?>;"><?= ucfirst($login['role']) ?></span></td>
                        <td><?= date('d M Y H:i:s', strtotime($login['login_time'])) ?></td>
                        <td><?= htmlspecialchars($login['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
exit();

// ==================== MANAGER DASHBOARD ====================
elseif ($page == 'manager_dashboard' && $current_user && $current_user['role'] == 'manager'):
    // Get team members
    $team = $pdo->prepare("SELECT * FROM users WHERE manager_id = ?");
    $team->execute([$current_user['id']]);
    $team = $team->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard | MPSS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo i {
            color: #fbbf24;
            margin-right: 10px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .role-badge {
            background: #fbbf24;
            color: #1e40af;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
        }
        
        .main {
            margin-top: 70px;
            padding: 30px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .back-home {
            display: inline-block;
            margin-bottom: 20px;
            color: #1e40af;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-users-cog"></i> MPSS - Manager Dashboard
        </div>
        <div class="user-menu">
            <span><?= htmlspecialchars($current_user['full_name']) ?></span>
            <span class="role-badge">Manager</span>
            <a href="?page=home" class="logout-btn"><i class="fas fa-home"></i> Home</a>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main">
        <a href="?page=home" class="back-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
        
        <div class="welcome-card">
            <h1>Welcome, <?= htmlspecialchars($current_user['full_name']) ?>!</h1>
            <p>Managing <?= count($team) ?> team members in <?= htmlspecialchars($current_user['department']) ?> department</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($team) ?></h3>
                <p>Team Members</p>
            </div>
            <div class="stat-card">
                <h3>0</h3>
                <p>Pending Approvals</p>
            </div>
        </div>
        
        <div class="section">
            <h2>My Team</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team as $member): ?>
                    <tr>
                        <td><?= htmlspecialchars($member['full_name']) ?></td>
                        <td><?= htmlspecialchars($member['position']) ?></td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
exit();

// ==================== EMPLOYEE DASHBOARD ====================
elseif ($page == 'employee_dashboard' && $current_user && $current_user['role'] == 'employee'):
    // Get manager info
    $manager = null;
    if ($current_user['manager_id']) {
        $mgr = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $mgr->execute([$current_user['manager_id']]);
        $manager = $mgr->fetch(PDO::FETCH_ASSOC);
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard | MPSS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo i {
            color: #fbbf24;
            margin-right: 10px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .role-badge {
            background: #fbbf24;
            color: #059669;
            padding: 3px 10px;
            border-radius: 15px;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
        }
        
        .main {
            margin-top: 70px;
            padding: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #059669;
        }
        
        .back-home {
            display: inline-block;
            margin-bottom: 20px;
            color: #059669;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-user-tie"></i> MPSS - Employee Portal
        </div>
        <div class="user-menu">
            <span><?= htmlspecialchars($current_user['full_name']) ?></span>
            <span class="role-badge">Employee</span>
            <a href="?page=home" class="logout-btn"><i class="fas fa-home"></i> Home</a>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main">
        <a href="?page=home" class="back-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
        
        <div class="welcome-card">
            <h1>Welcome, <?= htmlspecialchars($current_user['full_name']) ?>!</h1>
            <p><?= htmlspecialchars($current_user['position']) ?> in <?= htmlspecialchars($current_user['department']) ?> department</p>
        </div>
        
        <div class="profile-card">
            <h2><i class="fas fa-id-card"></i> My Profile</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($current_user['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?= htmlspecialchars($current_user['username']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($current_user['email']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($current_user['department']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Position</div>
                    <div class="info-value"><?= htmlspecialchars($current_user['position']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Manager</div>
                    <div class="info-value">
                        <?php if ($manager): ?>
                            <?= htmlspecialchars($manager['full_name']) ?>
                        <?php else: ?>
                            Not Assigned
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit();

// ==================== DEFAULT REDIRECT TO HOME ====================
else:
    header("Location: ?page=home");
    exit();
endif;
?><?php
session_start();

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'employee_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// ==================== DATABASE SETUP ====================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Create users table with roles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('ceo', 'manager', 'employee') NOT NULL,
            department VARCHAR(50),
            position VARCHAR(100),
            manager_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_role (role),
            INDEX idx_manager (manager_id)
        )
    ");
    
    // Create employees table (linked to users)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL,
            kpi_score DECIMAL(5,2) DEFAULT 0,
            tasks_completed INT DEFAULT 0,
            total_tasks INT DEFAULT 0,
            weekend_hours INT DEFAULT 0,
            attendance DECIMAL(5,2) DEFAULT 0,
            join_date DATE NOT NULL,
            phone VARCHAR(20),
            emergency_contact VARCHAR(20),
            address TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create attendance table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action ENUM('in', 'out') NOT NULL,
            timestamp DATETIME NOT NULL,
            date DATE NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, date)
        )
    ");
    
    // Create departments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            manager_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Create salary_history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS salary_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL,
            performance_bonus DECIMAL(10,2) DEFAULT 0,
            weekend_bonus DECIMAL(10,2) DEFAULT 0,
            total_salary DECIMAL(10,2) NOT NULL,
            month_year DATE NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_month (user_id, month_year)
        )
    ");
    
    // Create tasks table for managers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assigned_by INT NOT NULL,
            assigned_to INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            deadline DATE,
            status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            completed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_status (status)
        )
    ");
    
    // Create leave_requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            leave_type ENUM('annual', 'sick', 'personal', 'unpaid') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_status (user_id, status)
        )
    ");
    
    // Create notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read)
        )
    ");
    
    // Create performance_reviews table (for managers)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS performance_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            review_date DATE NOT NULL,
            kpi_score DECIMAL(5,2) NOT NULL,
            comments TEXT,
            next_review_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_review (user_id, review_date)
        )
    ");
    
    // Check if admin/CEO exists, if not create default users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ceo'");
    if ($stmt->fetchColumn() == 0) {
        // Create CEO
        $ceo_password = password_hash('ceo123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute(['ceo', $ceo_password, 'ceo@company.com', 'John Smith', 'ceo', 'Executive', 'Chief Executive Officer']);
        $ceo_id = $pdo->lastInsertId();
        
        // Create departments
        $departments = ['IT', 'HR', 'Sales', 'Finance', 'Marketing', 'Operations'];
        foreach ($departments as $dept) {
            $pdo->prepare("INSERT INTO departments (name) VALUES (?)")->execute([$dept]);
        }
        
        // Create managers
        $managers = [
            ['it_manager', 'it_manager@company.com', 'Alice Johnson', 'IT', 'IT Manager'],
            ['hr_manager', 'hr_manager@company.com', 'Bob Wilson', 'HR', 'HR Manager'],
            ['sales_manager', 'sales_manager@company.com', 'Carol Brown', 'Sales', 'Sales Manager'],
            ['finance_manager', 'finance_manager@company.com', 'David Lee', 'Finance', 'Finance Manager']
        ];
        
        $manager_ids = [];
        foreach ($managers as $index => $manager) {
            $pass = password_hash('manager123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$manager[0], $pass, $manager[1], $manager[2], 'manager', $manager[3], $manager[4]]);
            $manager_id = $pdo->lastInsertId();
            $manager_ids[$manager[3]] = $manager_id;
            
            // Update department with manager
            $pdo->prepare("UPDATE departments SET manager_id = ? WHERE name = ?")->execute([$manager_id, $manager[3]]);
        }
        
        // Create employees
        $employees = [
            ['john.doe', 'john.doe@company.com', 'John Doe', 'IT', 'Developer', 45000, 85, 45, 50, 12, 96, '2023-01-15', '9876543210', '9988776655'],
            ['jane.smith', 'jane.smith@company.com', 'Jane Smith', 'HR', 'HR Executive', 35000, 78, 38, 45, 8, 98, '2023-03-20', '9876543211', '9988776656'],
            ['mike.johnson', 'mike.johnson@company.com', 'Mike Johnson', 'Sales', 'Sales Executive', 40000, 82, 42, 50, 10, 95, '2023-02-10', '9876543212', '9988776657'],
            ['sarah.williams', 'sarah.williams@company.com', 'Sarah Williams', 'IT', 'Senior Developer', 55000, 92, 48, 50, 15, 97, '2022-11-05', '9876543213', '9988776658'],
            ['david.brown', 'david.brown@company.com', 'David Brown', 'Sales', 'Sales Representative', 38000, 75, 35, 45, 5, 94, '2023-04-12', '9876543214', '9988776659'],
            ['emily.davis', 'emily.davis@company.com', 'Emily Davis', 'Finance', 'Accountant', 42000, 88, 40, 45, 6, 99, '2023-05-18', '9876543215', '9988776660'],
            ['chris.wilson', 'chris.wilson@company.com', 'Chris Wilson', 'Marketing', 'Marketing Specialist', 41000, 79, 36, 42, 4, 96, '2023-06-22', '9876543216', '9988776661']
        ];
        
        foreach ($employees as $emp) {
            $pass = password_hash('employee123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$emp[0], $pass, $emp[1], $emp[2], 'employee', $emp[3], $emp[4], $manager_ids[$emp[3]] ?? null]);
            
            $user_id = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO employees (id, user_id, base_salary, kpi_score, tasks_completed, total_tasks, weekend_hours, attendance, join_date, phone, emergency_contact) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$user_id, $user_id, $emp[5], $emp[6], $emp[7], $emp[8], $emp[9], $emp[10], $emp[11], $emp[12], $emp[13]]);
            
            // Add some attendance records
            $dates = ['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'];
            foreach ($dates as $date) {
                $in_time = $date . ' 09:00:00';
                $out_time = $date . ' 18:00:00';
                $pdo->prepare("INSERT INTO attendance (user_id, action, timestamp, date) VALUES (?, 'in', ?, ?)")
                    ->execute([$user_id, $in_time, $date]);
                $pdo->prepare("INSERT INTO attendance (user_id, action, timestamp, date) VALUES (?, 'out', ?, ?)")
                    ->execute([$user_id, $out_time, $date]);
            }
            
            // Add notifications
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'success', 'Welcome to the team!', 'Your account has been created successfully.')")
                ->execute([$user_id]);
        }
        
        // Add some tasks
        $tasks = [
            [2, 3, 'Complete project documentation', 'Write comprehensive documentation for the new HR system', '2026-03-15', 'high'],
            [4, 1, 'Fix login page bug', 'Users are experiencing issues with the login page on mobile devices', '2026-03-10', 'urgent'],
            [2, 2, 'Prepare monthly report', 'Compile HR metrics for the monthly executive meeting', '2026-03-12', 'medium']
        ];
        
        foreach ($tasks as $task) {
            $pdo->prepare("INSERT INTO tasks (assigned_by, assigned_to, title, description, deadline, priority) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute($task);
        }
        
        // Add leave requests
        $leave_requests = [
            [3, 'annual', '2026-03-20', '2026-03-25', 'Family vacation', 'approved', 2],
            [5, 'sick', '2026-03-08', '2026-03-09', 'Flu', 'pending', null]
        ];
        
        foreach ($leave_requests as $leave) {
            $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute($leave);
        }
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==================== HANDLE LOGIN ====================
$login_error = '';
$success_message = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department'] = $user['department'];
        
        // Redirect based on role
        header("Location: " . $_SERVER['PHP_SELF'] . "?dashboard=" . $user['role']);
        exit();
    } else {
        $login_error = "Invalid username or password";
    }
}

// ==================== HANDLE LOGOUT ====================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==================== CHECK LOGIN ====================
$current_user = null;
$dashboard_type = $_GET['dashboard'] ?? '';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Override dashboard from session
    $dashboard_type = $current_user['role'];
}

// If not logged in and not showing login, show login page
if (!$current_user && !isset($_GET['dashboard'])) {
    $dashboard_type = 'login';
}

// ==================== ROLE-BASED FUNCTIONS ====================

// CEO Functions
function getCompanyStats($pdo) {
    $stats = [];
    
    // Total employees
    $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
    
    // Total managers
    $stats['total_managers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
    
    // Total departments
    $stats['total_departments'] = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    
    // Today's attendance
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = ? AND action = 'in'");
    $stmt->execute([$today]);
    $stats['present_today'] = $stmt->fetchColumn();
    
    // Pending leave requests
    $stats['pending_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
    
    // Total payroll (current month)
    $stats['total_payroll'] = $pdo->query("SELECT SUM(base_salary) FROM employees")->fetchColumn();
    
    // Average KPI
    $stats['avg_kpi'] = $pdo->query("SELECT AVG(kpi_score) FROM employees")->fetchColumn();
    
    // Department stats
    $dept_stats = $pdo->query("
        SELECT d.name, COUNT(u.id) as emp_count, AVG(e.kpi_score) as avg_kpi
        FROM departments d
        LEFT JOIN users u ON u.department = d.name AND u.role = 'employee'
        LEFT JOIN employees e ON u.id = e.user_id
        GROUP BY d.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stats['departments'] = $dept_stats;
    
    return $stats;
}

// Manager Functions
function getManagerTeam($pdo, $manager_id) {
    $stmt = $pdo->prepare("
        SELECT u.*, e.* 
        FROM users u
        JOIN employees e ON u.id = e.user_id
        WHERE u.manager_id = ?
    ");
    $stmt->execute([$manager_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getManagerPendingTasks($pdo, $manager_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name as assigned_to_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE t.assigned_by = ? AND t.status != 'completed'
        ORDER BY t.deadline ASC
    ");
    $stmt->execute([$manager_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getManagerTeamAttendance($pdo, $manager_id, $date) {
    $stmt = $pdo->prepare("
        SELECT u.full_name, 
               MAX(CASE WHEN a.action = 'in' THEN a.timestamp END) as check_in,
               MAX(CASE WHEN a.action = 'out' THEN a.timestamp END) as check_out
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        WHERE u.manager_id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$date, $manager_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Employee Functions
function getEmployeeDetails($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT u.*, e.*, d.name as department_name, 
               m.full_name as manager_name, m.email as manager_email
        FROM users u
        JOIN employees e ON u.id = e.user_id
        LEFT JOIN departments d ON u.department = d.name
        LEFT JOIN users m ON u.manager_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getEmployeeTasks($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name as assigned_by_name
        FROM tasks t
        JOIN users u ON t.assigned_by = u.id
        WHERE t.assigned_to = ?
        ORDER BY 
            CASE t.status
                WHEN 'pending' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'overdue' THEN 4
            END,
            t.deadline ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmployeeAttendance($pdo, $user_id, $month = null) {
    if (!$month) $month = date('Y-m');
    
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
        ORDER BY timestamp DESC
    ");
    $stmt->execute([$user_id, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmployeeLeaveRequests($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as approved_by_name
        FROM leave_requests l
        LEFT JOIN users u ON l.approved_by = u.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmployeeSalaryHistory($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM salary_history 
        WHERE user_id = ? 
        ORDER BY month_year DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmployeeNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== HANDLE POST ACTIONS ====================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $current_user) {
    
    // Handle Check In/Out
    if (isset($_POST['check_action'])) {
        $action = $_POST['check_action'];
        $timestamp = date('Y-m-d H:i:s');
        $date = date('Y-m-d');
        
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, action, timestamp, date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$current_user['id'], $action, $timestamp, $date]);
        
        $success_message = ($action == 'in') ? "✅ Checked in successfully" : "✅ Checked out successfully";
    }
    
    // Handle Leave Request
    if (isset($_POST['submit_leave'])) {
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = $_POST['reason'];
        
        $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$current_user['id'], $leave_type, $start_date, $end_date, $reason]);
        
        $success_message = "✅ Leave request submitted successfully";
    }
    
    // Handle Task Assignment (Manager)
    if (isset($_POST['assign_task']) && $current_user['role'] == 'manager') {
        $assigned_to = $_POST['assigned_to'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $deadline = $_POST['deadline'];
        $priority = $_POST['priority'];
        
        $stmt = $pdo->prepare("INSERT INTO tasks (assigned_by, assigned_to, title, description, deadline, priority) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user['id'], $assigned_to, $title, $description, $deadline, $priority]);
        
        // Notify employee
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'info', 'New Task Assigned', ?)")
            ->execute([$assigned_to, "You have been assigned a new task: $title"]);
        
        $success_message = "✅ Task assigned successfully";
    }
    
    // Handle Task Status Update (Employee)
    if (isset($_POST['update_task'])) {
        $task_id = $_POST['task_id'];
        $status = $_POST['status'];
        
        $completed_at = ($status == 'completed') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
        $stmt->execute([$status, $completed_at, $task_id]);
        
        $success_message = "✅ Task updated successfully";
    }
    
    // Handle Approve Leave (Manager/CEO)
    if (isset($_POST['approve_leave'])) {
        $leave_id = $_POST['leave_id'];
        $status = $_POST['approve_action'];
        
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $current_user['id'], $leave_id]);
        
        // Get user_id for notification
        $leave = $pdo->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
        $leave->execute([$leave_id]);
        $user_id = $leave->fetchColumn();
        
        // Notify employee
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, 'Leave Request Update', ?)")
            ->execute([$user_id, $status == 'approved' ? 'success' : 'danger', "Your leave request has been $status"]);
        
        $success_message = "✅ Leave request $status";
    }
    
    // Handle Add Employee (CEO only)
    if (isset($_POST['add_employee']) && $current_user['role'] == 'ceo') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $department = $_POST['department'];
        $position = $_POST['position'];
        $base_salary = $_POST['base_salary'];
        $join_date = $_POST['join_date'];
        $phone = $_POST['phone'];
        $manager_id = $_POST['manager_id'] ?: null;
        
        $password = password_hash('employee123', PASSWORD_DEFAULT);
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position, manager_id) VALUES (?, ?, ?, ?, 'employee', ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $full_name, $department, $position, $manager_id]);
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO employees (id, user_id, base_salary, join_date, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $user_id, $base_salary, $join_date, $phone]);
            
            $pdo->commit();
            $success_message = "✅ Employee added successfully";
        } catch (Exception $e) {
            $pdo->rollBack();
            $login_error = "Error adding employee: " . $e->getMessage();
        }
    }
    
    // Handle Add Manager (CEO only)
    if (isset($_POST['add_manager']) && $current_user['role'] == 'ceo') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $department = $_POST['department'];
        $position = $_POST['position'];
        
        $password = password_hash('manager123', PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, position) VALUES (?, ?, ?, ?, 'manager', ?, ?)");
            $stmt->execute([$username, $password, $email, $full_name, $department, $position]);
            $manager_id = $pdo->lastInsertId();
            
            // Update department with manager
            $pdo->prepare("UPDATE departments SET manager_id = ? WHERE name = ?")->execute([$manager_id, $department]);
            
            $success_message = "✅ Manager added successfully";
        } catch (Exception $e) {
            $login_error = "Error adding manager: " . $e->getMessage();
        }
    }
    
    // Handle Mark Notification Read
    if (isset($_POST['mark_read'])) {
        $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")->execute([$current_user['id']]);
    }
}

// ==================== GET NOTIFICATIONS ====================
$notifications = [];
if ($current_user) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
    $stmt->execute([$current_user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== RENDER LOGIN PAGE ====================
if ($dashboard_type == 'login' || !$current_user):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management System - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            max-width: 1200px;
            width: 90%;
            margin: 0 auto;
        }
        
        .login-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .login-header h1 {
            font-size: 42px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .login-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 30px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        
        .login-card:hover {
            transform: translateY(-10px);
        }
        
        .login-card.ceo {
            border-top: 5px solid #fbbf24;
        }
        
        .login-card.manager {
            border-top: 5px solid #3b82f6;
        }
        
        .login-card.employee {
            border-top: 5px solid #22c55e;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }
        
        .ceo .login-icon {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        
        .manager .login-icon {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .employee .login-icon {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }
        
        .login-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #1f2937;
        }
        
        .login-card p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        
        .login-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #374151;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .ceo .login-btn {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        
        .manager .login-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .employee .login-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }
        
        .login-btn:hover {
            opacity: 0.9;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .demo-credentials {
            margin-top: 40px;
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            color: white;
        }
        
        .demo-credentials h4 {
            margin-bottom: 15px;
        }
        
        .credential-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .credential-item {
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 10px;
        }
        
        .credential-item strong {
            display: block;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .login-grid {
                grid-template-columns: 1fr;
            }
            
            .credential-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-building"></i> MPSS Employee Management System</h1>
            <p>Select your role to login - Multi-level dashboard system</p>
        </div>
        
        <div class="login-grid">
            <!-- CEO Login -->
            <div class="login-card ceo">
                <div class="login-icon"><i class="fas fa-crown"></i></div>
                <h3>CEO</h3>
                <p>Strategic overview, company-wide analytics, department management</p>
                
                <form method="POST" class="login-form">
                    <input type="hidden" name="role" value="ceo">
                    <div class="form-group">
                        <label>Username/Email</label>
                        <input type="text" name="username" placeholder="ceo" value="ceo">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••" value="ceo123">
                    </div>
                    <button type="submit" name="login" class="login-btn">Login as CEO</button>
                </form>
            </div>
            
            <!-- Manager Login -->
            <div class="login-card manager">
                <div class="login-icon"><i class="fas fa-users-cog"></i></div>
                <h3>Manager</h3>
                <p>Team management, task assignment, leave approval, performance reviews</p>
                
                <form method="POST" class="login-form">
                    <input type="hidden" name="role" value="manager">
                    <div class="form-group">
                        <label>Username/Email</label>
                        <input type="text" name="username" placeholder="it_manager" value="it_manager">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••" value="manager123">
                    </div>
                    <button type="submit" name="login" class="login-btn">Login as Manager</button>
                </form>
            </div>
            
            <!-- Employee Login -->
            <div class="login-card employee">
                <div class="login-icon"><i class="fas fa-user-tie"></i></div>
                <h3>Employee</h3>
                <p>Check in/out, view tasks, leave requests, salary information</p>
                
                <form method="POST" class="login-form">
                    <input type="hidden" name="role" value="employee">
                    <div class="form-group">
                        <label>Username/Email</label>
                        <input type="text" name="username" placeholder="john.doe" value="john.doe">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••" value="employee123">
                    </div>
                    <button type="submit" name="login" class="login-btn">Login as Employee</button>
                </form>
            </div>
        </div>
        
        <?php if ($login_error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?= $login_error ?>
        </div>
        <?php endif; ?>
        
        <div class="demo-credentials">
            <h4><i class="fas fa-info-circle"></i> Demo Credentials</h4>
            <div class="credential-grid">
                <div class="credential-item">
                    <strong>CEO</strong>
                    <div>Username: ceo</div>
                    <div>Password: ceo123</div>
                </div>
                <div class="credential-item">
                    <strong>IT Manager</strong>
                    <div>Username: it_manager</div>
                    <div>Password: manager123</div>
                </div>
                <div class="credential-item">
                    <strong>Employee</strong>
                    <div>Username: john.doe</div>
                    <div>Password: employee123</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit();
endif;

// ==================== RENDER CEO DASHBOARD ====================
if ($dashboard_type == 'ceo'):
    $stats = getCompanyStats($pdo);
    $all_managers = $pdo->query("SELECT id, full_name, department FROM users WHERE role = 'manager' ORDER BY department")->fetchAll(PDO::FETCH_ASSOC);
    $all_departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $recent_activity = $pdo->query("
        SELECT a.*, u.full_name, u.department 
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.timestamp DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    $pending_leaves = $pdo->query("
        SELECT l.*, u.full_name, u.department 
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        WHERE l.status = 'pending'
        ORDER BY l.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Dashboard - Strategic Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1e3a8a, #312e81);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .logo i {
            color: #fbbf24;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            padding: 8px 20px;
            border-radius: 30px;
        }
        
        .user-info i {
            color: #fbbf24;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 900;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }
        
        .sidebar-item:hover {
            background: #f3f4f6;
            color: #1e3a8a;
            border-left-color: #fbbf24;
        }
        
        .sidebar-item.active {
            background: #e0e7ff;
            color: #1e3a8a;
            border-left-color: #fbbf24;
        }
        
        .sidebar-item i {
            width: 20px;
        }
        
        .sidebar-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 15px 20px;
        }
        
        /* Main Content */
        .main {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1e3a8a, #5b21b6);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-text h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            opacity: 0.9;
        }
        
        .stats-badge {
            display: flex;
            gap: 30px;
            background: rgba(255,255,255,0.2);
            padding: 15px 30px;
            border-radius: 15px;
        }
        
        .badge-item {
            text-align: center;
        }
        
        .badge-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .badge-label {
            font-size: 12px;
            opacity: 0.8;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1e3a8a;
        }
        
        .stat-info p {
            color: #6b7280;
            margin-top: 5px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1e3a8a, #5b21b6);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        /* Chart Grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-container {
            height: 300px;
        }
        
        /* Section */
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }
        
        /* Tables */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        /* Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .badge.approved {
            background: #d1fae5;
            color: #059669;
        }
        
        .badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a, #312e81);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px #1e3a8a;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 15px;
            font-size: 12px;
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
            padding: 30px;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e3a8a;
        }
        
        /* Success Message */
        .success-message {
            background: #d1fae5;
            color: #059669;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .chart-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-banner {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-crown"></i>
            <span>MPSS - CEO Dashboard</span>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?= htmlspecialchars($current_user['full_name']) ?></span>
                <span class="badge" style="background: #fbbf24; color: #1e3a8a;">CEO</span>
            </div>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-menu">
            <div class="sidebar-item active" onclick="scrollToTop()">
                <i class="fas fa-tachometer-alt"></i> Overview
            </div>
            <div class="sidebar-item" onclick="scrollToSection('departments')">
                <i class="fas fa-building"></i> Departments
            </div>
            <div class="sidebar-item" onclick="scrollToSection('employees')">
                <i class="fas fa-users"></i> Employees
            </div>
            <div class="sidebar-item" onclick="scrollToSection('managers')">
                <i class="fas fa-users-cog"></i> Managers
            </div>
            <div class="sidebar-item" onclick="scrollToSection('attendance')">
                <i class="fas fa-clock"></i> Attendance
            </div>
            <div class="sidebar-item" onclick="scrollToSection('leaves')">
                <i class="fas fa-calendar-alt"></i> Leave Requests
            </div>
            <div class="sidebar-item" onclick="scrollToSection('add-employee')">
                <i class="fas fa-user-plus"></i> Add Employee
            </div>
            <div class="sidebar-item" onclick="scrollToSection('add-manager')">
                <i class="fas fa-user-tie"></i> Add Manager
            </div>
            <div class="sidebar-divider"></div>
            <div class="sidebar-item" onclick="scrollToSection('reports')">
                <i class="fas fa-chart-bar"></i> Reports
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        
        <?php if ($success_message): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
        </div>
        <?php endif; ?>
        
        <?php if ($login_error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?= $login_error ?>
        </div>
        <?php endif; ?>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars($current_user['full_name']) ?>!</h1>
                <p>Strategic overview for <?= date('F Y') ?></p>
            </div>
            <div class="stats-badge">
                <div class="badge-item">
                    <div class="badge-value"><?= $stats['total_employees'] ?></div>
                    <div class="badge-label">Employees</div>
                </div>
                <div class="badge-item">
                    <div class="badge-value"><?= $stats['present_today'] ?></div>
                    <div class="badge-label">Present</div>
                </div>
                <div class="badge-item">
                    <div class="badge-value"><?= $stats['pending_leaves'] ?></div>
                    <div class="badge-label">Pending</div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $stats['total_employees'] ?></h3>
                    <p>Total Employees</p>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $stats['total_managers'] ?></h3>
                    <p>Managers</p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $stats['total_departments'] ?></h3>
                    <p>Departments</p>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>₹<?= number_format($stats['total_payroll'] / 100000, 1) ?>L</h3>
                    <p>Monthly Payroll</p>
                </div>
                <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="chart-grid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Department Distribution</h3>
                <div class="chart-container">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Department Performance</h3>
                <div class="chart-container">
                    <canvas id="kpiChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Departments Section -->
        <div class="section" id="departments">
            <div class="section-header">
                <h2><i class="fas fa-building"></i> Departments Overview</h2>
                <button class="btn btn-primary" onclick="showAddDepartmentModal()">
                    <i class="fas fa-plus"></i> Add Department
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Manager</th>
                            <th>Employees</th>
                            <th>Avg KPI</th>
                            <th>Performance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['departments'] as $dept): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dept['name']) ?></strong></td>
                            <td>
                                <?php
                                $manager = $pdo->prepare("SELECT full_name FROM users WHERE department = ? AND role = 'manager'");
                                $manager->execute([$dept['name']]);
                                $mgr = $manager->fetchColumn();
                                echo $mgr ? htmlspecialchars($mgr) : 'Not Assigned';
                                ?>
                            </td>
                            <td><?= $dept['emp_count'] ?></td>
                            <td><?= number_format($dept['avg_kpi'], 1) ?>%</td>
                            <td>
                                <div class="progress-bar" style="width: 100%; background: #e5e7eb; border-radius: 10px; height: 10px;">
                                    <div style="width: <?= $dept['avg_kpi'] ?>%; background: <?= $dept['avg_kpi'] >= 80 ? '#10b981' : ($dept['avg_kpi'] >= 60 ? '#f59e0b' : '#ef4444') ?>; height: 10px; border-radius: 10px;"></div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewDepartment('<?= $dept['name'] ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pending Leave Requests -->
        <div class="section" id="leaves">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Pending Leave Requests</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_leaves as $leave): ?>
                        <tr>
                            <td><?= htmlspecialchars($leave['full_name']) ?></td>
                            <td><?= htmlspecialchars($leave['department']) ?></td>
                            <td><span class="badge pending"><?= $leave['leave_type'] ?></span></td>
                            <td><?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?></td>
                            <td><?= htmlspecialchars($leave['reason']) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                    <button type="submit" name="approve_action" value="approved" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="approve_action" value="rejected" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="hidden" name="approve_leave" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pending_leaves)): ?>
                        <tr><td colspan="6" style="text-align: center;">No pending leave requests</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Add Employee Section -->
        <div class="section" id="add-employee">
            <div class="section-header">
                <h2><i class="fas fa-user-plus"></i> Add New Employee</h2>
            </div>
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" required>
                            <?php foreach ($all_departments as $dept): ?>
                            <option value="<?= $dept['name'] ?>"><?= $dept['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" required>
                    </div>
                    <div class="form-group">
                        <label>Base Salary (₹)</label>
                        <input type="number" name="base_salary" required>
                    </div>
                    <div class="form-group">
                        <label>Join Date</label>
                        <input type="date" name="join_date" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Manager</label>
                        <select name="manager_id">
                            <option value="">None</option>
                            <?php foreach ($all_managers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>"><?= htmlspecialchars($mgr['full_name']) ?> (<?= $mgr['department'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_employee" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Add Employee
                </button>
            </form>
        </div>
        
        <!-- Add Manager Section -->
        <div class="section" id="add-manager">
            <div class="section-header">
                <h2><i class="fas fa-user-tie"></i> Add New Manager</h2>
            </div>
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" required>
                            <?php foreach ($all_departments as $dept): ?>
                            <option value="<?= $dept['name'] ?>"><?= $dept['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" value="Manager" required>
                    </div>
                </div>
                <button type="submit" name="add_manager" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Add Manager
                </button>
            </form>
        </div>
        
        <!-- Recent Activity -->
        <div class="section" id="attendance">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Action</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activity as $act): ?>
                        <tr>
                            <td><?= date('H:i:s', strtotime($act['timestamp'])) ?></td>
                            <td><?= htmlspecialchars($act['full_name']) ?></td>
                            <td><?= htmlspecialchars($act['department']) ?></td>
                            <td>
                                <?php if ($act['action'] == 'in'): ?>
                                <span style="color: #10b981;"><i class="fas fa-sign-in-alt"></i> Check In</span>
                                <?php else: ?>
                                <span style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Check Out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $check_time = strtotime($act['timestamp']);
                                if ($act['action'] == 'in') {
                                    echo $check_time > strtotime('09:30:00') ? '<span class="badge pending">Late</span>' : '<span class="badge approved">On Time</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Scroll functions
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function scrollToSection(sectionId) {
            document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Department Distribution Chart
            const deptCtx = document.getElementById('deptChart')?.getContext('2d');
            if (deptCtx) {
                const deptData = <?= json_encode(array_column($stats['departments'], 'emp_count')) ?>;
                const deptLabels = <?= json_encode(array_column($stats['departments'], 'name')) ?>;
                
                new Chart(deptCtx, {
                    type: 'doughnut',
                    data: {
                        labels: deptLabels,
                        datasets: [{
                            data: deptData,
                            backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#ec4899'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        },
                        cutout: '70%'
                    }
                });
            }
            
            // KPI Chart
            const kpiCtx = document.getElementById('kpiChart')?.getContext('2d');
            if (kpiCtx) {
                const kpiData = <?= json_encode(array_column($stats['departments'], 'avg_kpi')) ?>;
                const kpiLabels = <?= json_encode(array_column($stats['departments'], 'name')) ?>;
                
                new Chart(kpiCtx, {
                    type: 'bar',
                    data: {
                        labels: kpiLabels,
                        datasets: [{
                            label: 'Average KPI Score',
                            data: kpiData,
                            backgroundColor: '#3b82f6',
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }
        });
        
        function viewDepartment(dept) {
            alert('Viewing department: ' + dept);
            // In production, show detailed department view
        }
        
        function showAddDepartmentModal() {
            alert('Add department functionality - In production, this would open a modal');
        }
    </script>
</body>
</html>
<?php
exit();
endif;

// ==================== RENDER MANAGER DASHBOARD ====================
if ($dashboard_type == 'manager'):
    $team = getManagerTeam($pdo, $current_user['id']);
    $pending_tasks = getManagerPendingTasks($pdo, $current_user['id']);
    $team_attendance = getManagerTeamAttendance($pdo, $current_user['id'], date('Y-m-d'));
    $team_leaves = $pdo->prepare("
        SELECT l.*, u.full_name 
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        WHERE u.manager_id = ? AND l.status = 'pending'
        ORDER BY l.created_at DESC
    ");
    $team_leaves->execute([$current_user['id']]);
    $team_leaves = $team_leaves->fetchAll(PDO::FETCH_ASSOC);
    
    // Get team members for task assignment
    $team_members = $pdo->prepare("SELECT id, full_name FROM users WHERE manager_id = ?");
    $team_members->execute([$current_user['id']]);
    $team_members = $team_members->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Team Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Similar styles to CEO dashboard but with manager-specific colors */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo i {
            color: #fbbf24;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 8px 20px;
            border-radius: 30px;
        }
        
        .sidebar {
            width: 260px;
            background: white;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #4b5563;
            cursor: pointer;
            border-left: 3px solid transparent;
        }
        
        .sidebar-item:hover,
        .sidebar-item.active {
            background: #dbeafe;
            color: #1e40af;
            border-left-color: #fbbf24;
        }
        
        .main {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #1e40af, #5b21b6);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4b5563;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.pending { background: #fef3c7; color: #d97706; }
        .badge.approved { background: #d1fae5; color: #059669; }
        .badge.rejected { background: #fee2e2; color: #dc2626; }
        .badge.high { background: #fee2e2; color: #dc2626; }
        .badge.medium { background: #fef3c7; color: #d97706; }
        .badge.low { background: #d1fae5; color: #059669; }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1e40af;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        
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
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .success-message {
            background: #d1fae5;
            color: #059669;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-users-cog"></i>
            <span>Manager Dashboard - <?= htmlspecialchars($current_user['department']) ?></span>
        </div>
        <div class="user-info">
            <i class="fas fa-user"></i> <?= htmlspecialchars($current_user['full_name']) ?>
            <a href="?logout=1" style="color: white; margin-left: 20px;"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    
    <div class="sidebar">
        <div class="sidebar-item active" onclick="scrollToTop()">
            <i class="fas fa-tachometer-alt"></i> Overview
        </div>
        <div class="sidebar-item" onclick="scrollToSection('team')">
            <i class="fas fa-users"></i> My Team
        </div>
        <div class="sidebar-item" onclick="scrollToSection('tasks')">
            <i class="fas fa-tasks"></i> Tasks
        </div>
        <div class="sidebar-item" onclick="scrollToSection('attendance')">
            <i class="fas fa-clock"></i> Attendance
        </div>
        <div class="sidebar-item" onclick="scrollToSection('leaves')">
            <i class="fas fa-calendar-alt"></i> Leave Requests
        </div>
        <div class="sidebar-item" onclick="scrollToSection('assign-task')">
            <i class="fas fa-plus-circle"></i> Assign Task
        </div>
    </div>
    
    <div class="main">
        
        <?php if ($success_message): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
        </div>
        <?php endif; ?>
        
        <div class="welcome-banner">
            <h1>Welcome, Manager <?= htmlspecialchars($current_user['full_name']) ?></h1>
            <p>Managing <?= count($team) ?> team members in <?= htmlspecialchars($current_user['department']) ?> department</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($team) ?></h3>
                <p>Team Members</p>
            </div>
            <div class="stat-card">
                <h3><?= count($pending_tasks) ?></h3>
                <p>Pending Tasks</p>
            </div>
            <div class="stat-card">
                <h3><?= count($team_leaves) ?></h3>
                <p>Leave Requests</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($team_attendance, fn($t) => $t['check_in'])) ?></h3>
                <p>Present Today</p>
            </div>
        </div>
        
        <!-- My Team Section -->
        <div class="section" id="team">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> My Team</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>KPI Score</th>
                            <th>Tasks</th>
                            <th>Attendance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                            <td><?= htmlspecialchars($member['position']) ?></td>
                            <td><?= $member['kpi_score'] ?>%</td>
                            <td><?= $member['tasks_completed'] ?>/<?= $member['total_tasks'] ?></td>
                            <td><?= $member['attendance'] ?>%</td>
                            <td>
                                <?php
                                $today_check = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date = ? AND action = 'in'");
                                $today_check->execute([$member['user_id'], date('Y-m-d')]);
                                if ($today_check->fetchColumn() > 0) {
                                    echo '<span class="badge approved">Present</span>';
                                } else {
                                    echo '<span class="badge pending">Absent</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewEmployee(<?= $member['user_id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pending Tasks -->
        <div class="section" id="tasks">
            <div class="section-header">
                <h2><i class="fas fa-tasks"></i> Assigned Tasks</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Deadline</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_tasks as $task): ?>
                        <tr>
                            <td><?= htmlspecialchars($task['title']) ?></td>
                            <td><?= htmlspecialchars($task['assigned_to_name']) ?></td>
                            <td><?= date('d M Y', strtotime($task['deadline'])) ?></td>
                            <td>
                                <span class="badge <?= $task['priority'] ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $task['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewTask(<?= $task['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pending_tasks)): ?>
                        <tr><td colspan="6" style="text-align: center;">No pending tasks</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Team Attendance -->
        <div class="section" id="attendance">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Today's Attendance</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_attendance as $att): ?>
                        <tr>
                            <td><?= htmlspecialchars($att['full_name']) ?></td>
                            <td><?= $att['check_in'] ? date('H:i:s', strtotime($att['check_in'])) : '-' ?></td>
                            <td><?= $att['check_out'] ? date('H:i:s', strtotime($att['check_out'])) : '-' ?></td>
                            <td>
                                <?php if ($att['check_in']): ?>
                                    <?php if (strtotime($att['check_in']) > strtotime('09:30:00')): ?>
                                        <span class="badge pending">Late</span>
                                    <?php else: ?>
                                        <span class="badge approved">On Time</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge rejected">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Leave Requests -->
        <div class="section" id="leaves">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> Pending Leave Requests</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_leaves as $leave): ?>
                        <tr>
                            <td><?= htmlspecialchars($leave['full_name']) ?></td>
                            <td><span class="badge pending"><?= $leave['leave_type'] ?></span></td>
                            <td><?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?></td>
                            <td><?= htmlspecialchars($leave['reason']) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                    <button type="submit" name="approve_action" value="approved" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="approve_action" value="rejected" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="hidden" name="approve_leave" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($team_leaves)): ?>
                        <tr><td colspan="5" style="text-align: center;">No pending leave requests</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Assign Task -->
        <div class="section" id="assign-task">
            <div class="section-header">
                <h2><i class="fas fa-plus-circle"></i> Assign New Task</h2>
            </div>
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to" required>
                            <?php foreach ($team_members as $member): ?>
                            <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Task Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Deadline</label>
                        <input type="date" name="deadline" required>
                    </div>
                </div>
                <button type="submit" name="assign_task" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-paper-plane"></i> Assign Task
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function scrollToSection(sectionId) {
            document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth' });
        }
        
        function viewEmployee(empId) {
            alert('Viewing employee details - ID: ' + empId);
        }
        
        function viewTask(taskId) {
            alert('Viewing task details - ID: ' + taskId);
        }
    </script>
</body>
</html>
<?php
exit();
endif;

// ==================== RENDER EMPLOYEE DASHBOARD ====================
if ($dashboard_type == 'employee'):
    $employee = getEmployeeDetails($pdo, $current_user['id']);
    $tasks = getEmployeeTasks($pdo, $current_user['id']);
    $attendance = getEmployeeAttendance($pdo, $current_user['id']);
    $leave_requests = getEmployeeLeaveRequests($pdo, $current_user['id']);
    $salary_history = getEmployeeSalaryHistory($pdo, $current_user['id']);
    $notifications = getEmployeeNotifications($pdo, $current_user['id']);
    
    // Check if already checked in today
    $today_check = $pdo->prepare("SELECT action FROM attendance WHERE user_id = ? AND date = ? ORDER BY timestamp DESC LIMIT 1");
    $today_check->execute([$current_user['id'], date('Y-m-d')]);
    $last_action = $today_check->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - My Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo i {
            color: #fbbf24;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-badge {
            position: relative;
            cursor: pointer;
        }
        
        .badge-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main {
            margin-top: 70px;
            padding: 30px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .check-btn {
            background: white;
            color: #059669;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .check-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.3);
        }
        
        .check-btn.out {
            background: #fef3c7;
            color: #d97706;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #059669;
        }
        
        .stat-label {
            color: #6b7280;
            margin-top: 5px;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: #f9fafb;
            border-radius: 10px;
        }
        
        .info-label {
            color: #6b7280;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4b5563;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.pending { background: #fef3c7; color: #d97706; }
        .badge.approved { background: #d1fae5; color: #059669; }
        .badge.rejected { background: #fee2e2; color: #dc2626; }
        .badge.completed { background: #d1fae5; color: #059669; }
        .badge.in_progress { background: #dbeafe; color: #1e40af; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #059669;
            color: white;
        }
        
        .btn-primary:hover {
            background: #047857;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #059669;
            color: #059669;
        }
        
        .btn-outline:hover {
            background: #059669;
            color: white;
        }
        
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
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: #f9fafb;
        }
        
        .notification-item.unread {
            background: #dbeafe;
        }
        
        .notification-time {
            font-size: 12px;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-user-tie"></i>
            <span>Employee Portal</span>
        </div>
        <div class="user-info">
            <div class="notification-badge" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications) > 0): ?>
                <span class="badge-count"><?= count($notifications) ?></span>
                <?php endif; ?>
            </div>
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($current_user['full_name']) ?></span>
            <a href="?logout=1" style="color: white;"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    
    <div class="main">
        
        <?php if ($success_message): ?>
        <div class="success-message" style="background: #d1fae5; color: #059669; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
        </div>
        <?php endif; ?>
        
        <!-- Welcome Card with Check In/Out -->
        <div class="welcome-card">
            <div>
                <h1>Welcome, <?= htmlspecialchars($employee['full_name']) ?>!</h1>
                <p><?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department_name']) ?></p>
            </div>
            <form method="POST">
                <?php if (!$last_action || $last_action['action'] == 'out'): ?>
                <button type="submit" name="check_action" value="in" class="check-btn">
                    <i class="fas fa-sign-in-alt"></i> Check In
                </button>
                <?php else: ?>
                <button type="submit" name="check_action" value="out" class="check-btn out">
                    <i class="fas fa-sign-out-alt"></i> Check Out
                </button>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $employee['kpi_score'] ?>%</div>
                <div class="stat-label">KPI Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $employee['attendance'] ?>%</div>
                <div class="stat-label">Attendance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $employee['tasks_completed'] ?>/<?= $employee['total_tasks'] ?></div>
                <div class="stat-label">Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($tasks) ?></div>
                <div class="stat-label">Active Tasks</div>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Employee Info -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-id-card"></i> My Information</h2>
                </div>
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($employee['full_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($employee['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Department</span>
                    <span class="info-value"><?= htmlspecialchars($employee['department_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Position</span>
                    <span class="info-value"><?= htmlspecialchars($employee['position']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Manager</span>
                    <span class="info-value"><?= htmlspecialchars($employee['manager_name'] ?: 'Not Assigned') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Join Date</span>
                    <span class="info-value"><?= date('d M Y', strtotime($employee['join_date'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($employee['phone'] ?: 'N/A') ?></span>
                </div>
            </div>
            
            <!-- Salary Info -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-rupee-sign"></i> Salary Information</h2>
                    <button class="btn btn-outline btn-sm" onclick="showSalaryHistory()">
                        <i class="fas fa-history"></i> History
                    </button>
                </div>
                <div class="info-item">
                    <span class="info-label">Base Salary</span>
                    <span class="info-value">₹<?= number_format($employee['base_salary']) ?></span>
                </div>
                <?php
                $performance_bonus = 0;
                if ($employee['kpi_score'] >= 90) $performance_bonus = $employee['base_salary'] * 0.20;
                elseif ($employee['kpi_score'] >= 75) $performance_bonus = $employee['base_salary'] * 0.10;
                elseif ($employee['kpi_score'] >= 60) $performance_bonus = $employee['base_salary'] * 0.05;
                
                $weekend_bonus = $employee['weekend_hours'] * 500;
                $total_salary = $employee['base_salary'] + $performance_bonus + $weekend_bonus;
                ?>
                <div class="info-item">
                    <span class="info-label">Performance Bonus</span>
                    <span class="info-value">₹<?= number_format($performance_bonus) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Weekend Bonus</span>
                    <span class="info-value">₹<?= number_format($weekend_bonus) ?></span>
                </div>
                <div class="info-item" style="background: #d1fae5;">
                    <span class="info-label">Total Salary</span>
                    <span class="info-value">₹<?= number_format($total_salary) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">KPI Rating</span>
                    <span class="info-value">
                        <?php
                        if ($employee['kpi_score'] >= 90) echo 'Excellent';
                        elseif ($employee['kpi_score'] >= 75) echo 'Good';
                        elseif ($employee['kpi_score'] >= 60) echo 'Average';
                        else echo 'Needs Improvement';
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- My Tasks -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-tasks"></i> My Tasks</h2>
                <span class="badge"><?= count($tasks) ?> tasks</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned By</th>
                            <th>Deadline</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= htmlspecialchars($task['title']) ?></td>
                            <td><?= htmlspecialchars($task['assigned_by_name']) ?></td>
                            <td><?= date('d M Y', strtotime($task['deadline'])) ?></td>
                            <td>
                                <span class="badge <?= $task['priority'] ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= str_replace('_', '', $task['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="btn-sm">
                                        <option value="pending" <?= $task['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="in_progress" <?= $task['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <input type="hidden" name="update_task" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tasks)): ?>
                        <tr><td colspan="6" style="text-align: center;">No tasks assigned</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Recent Attendance</h2>
                <button class="btn btn-outline btn-sm" onclick="viewFullAttendance()">
                    <i class="fas fa-calendar-alt"></i> View All
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $attendance_by_date = [];
                        foreach ($attendance as $rec) {
                            $date = $rec['date'];
                            if (!isset($attendance_by_date[$date])) {
                                $attendance_by_date[$date] = ['in' => null, 'out' => null];
                            }
                            $attendance_by_date[$date][$rec['action']] = $rec['timestamp'];
                        }
                        
                        $dates_to_show = array_slice(array_keys($attendance_by_date), 0, 5);
                        foreach ($dates_to_show as $date): 
                            $day = $attendance_by_date[$date];
                        ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($date)) ?></td>
                            <td><?= $day['in'] ? date('H:i:s', strtotime($day['in'])) : '-' ?></td>
                            <td><?= $day['out'] ? date('H:i:s', strtotime($day['out'])) : '-' ?></td>
                            <td>
                                <?php if ($day['in']): ?>
                                    <?php if (strtotime($day['in']) > strtotime($date . ' 09:30:00')): ?>
                                        <span class="badge pending">Late</span>
                                    <?php else: ?>
                                        <span class="badge approved">On Time</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge rejected">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Leave Requests -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> My Leave Requests</h2>
                <button class="btn btn-primary btn-sm" onclick="showLeaveModal()">
                    <i class="fas fa-plus"></i> Request Leave
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $leave): ?>
                        <tr>
                            <td><span class="badge pending"><?= $leave['leave_type'] ?></span></td>
                            <td><?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?></td>
                            <td><?= htmlspecialchars($leave['reason']) ?></td>
                            <td>
                                <span class="badge <?= $leave['status'] ?>">
                                    <?= ucfirst($leave['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($leave['approved_by_name'] ?: 'Pending') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($leave_requests)): ?>
                        <tr><td colspan="5" style="text-align: center;">No leave requests</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Leave Request Modal -->
    <div class="modal" id="leaveModal">
        <div class="modal-content">
            <div class="section-header">
                <h2>Request Leave</h2>
                <span class="close-modal" onclick="closeModal('leaveModal')" style="font-size: 24px; cursor: pointer;">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Leave Type</label>
                    <select name="leave_type" required>
                        <option value="annual">Annual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="3" required></textarea>
                </div>
                <button type="submit" name="submit_leave" class="btn btn-primary" style="width: 100%;">
                    Submit Request
                </button>
            </form>
        </div>
    </div>
    
    <!-- Notifications Modal -->
    <div class="modal" id="notificationsModal">
        <div class="modal-content">
            <div class="section-header">
                <h2>Notifications</h2>
                <span class="close-modal" onclick="closeModal('notificationsModal')" style="font-size: 24px; cursor: pointer;">&times;</span>
            </div>
            <div id="notificationsList">
                <?php foreach ($notifications as $note): ?>
                <div class="notification-item <?= $note['is_read'] ? '' : 'unread' ?>">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?= htmlspecialchars($note['title']) ?></strong>
                        <span class="notification-time"><?= date('H:i', strtotime($note['created_at'])) ?></span>
                    </div>
                    <p style="margin-top: 5px;"><?= htmlspecialchars($note['message']) ?></p>
                </div>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?>
                <p style="text-align: center; padding: 20px;">No notifications</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($notifications)): ?>
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="mark_read" class="btn btn-primary" style="width: 100%;">
                    Mark All as Read
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showLeaveModal() {
            document.getElementById('leaveModal').style.display = 'flex';
        }
        
        function showNotifications() {
            document.getElementById('notificationsModal').style.display = 'flex';
        }
        
        function showSalaryHistory() {
            alert('Salary history - In production, this would show your salary progression');
        }
        
        function viewFullAttendance() {
            alert('Full attendance history - In production, this would show all attendance records');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
exit();
endif;

// Fallback to login
header("Location: " . $_SERVER['PHP_SELF']);
exit();
?>




<!---login---!


  <?php
session_start();

/* TEMP USER (DB IN NEXT STEP) */
$validUser = "admin";
$validPass = "admin123";

$error = "";

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    if($username === $validUser && $password === $validPass){
        $_SESSION['user'] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid Username or Password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login | MPSS</title>

<style>

  body {
    /* Three-color gradient */
    background: linear-gradient(-45deg, #f5f6fa, #0a2f74, #28a745);
    background-size: 600% 600%;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    flex-direction: column;
    animation: gradientBG 15s ease infinite;
  }

  @keyframes gradientBG {
    0% {background-position:0% 50%;}
    50% {background-position:100% 50%;}
    100% {background-position:0% 50%;}
  }

.login-box{
    background:white;
    padding:30px;
    width:320px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    /* OPTIONAL: smooth transition */
    transition: transform 0.3s ease;
}
.login-box:hover{
    transform: translateY(-5px);
}
.login-box h2{
    text-align:center;
    margin-bottom:20px;
    color:#1e3a8a;
}
input{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #cbd5f5;
    border-radius:6px;
}
button{
    width:100%;
    padding:10px;
    background:#1e40af;
    border:none;
    color:white;
    border-radius:6px;
    font-size:16px;
    cursor:pointer;
}
button:hover{background:#1e3a8a}
.error{
    background:#fee2e2;
    color:#991b1b;
    padding:8px;
    border-radius:6px;
    text-align:center;
    margin-bottom:10px;
}
small{
    display:block;
    margin-top:10px;
    text-align:center;
    color:#64748b;
}
</style>
</head>

<body>

<div class="login-box">
    <h2>MPSS Login</h2>

    <?php if($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button name="login">Login</button>
    </form>

    <small>Default: admin / admin123</small>
</div>

</body>
</html>




    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Modern Color Palette */
            --primary-50: #eef2ff;
            --primary-100: #e0e7ff;
            --primary-200: #c7d2fe;
            --primary-300: #a5b4fc;
            --primary-400: #818cf8;
            --primary-500: #6366f1;
            --primary-600: #4f46e5;
            --primary-700: #4338ca;
            --primary-800: #3730a3;
            --primary-900: #312e81;
            
            --accent-500: #8b5cf6;
            --accent-600: #7c3aed;
            
            --success-500: #10b981;
            --warning-500: #f59e0b;
            --danger-500: #ef4444;
            --info-500: #3b82f6;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Light Theme */
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bg-primary: #f0f2f5;
            --bg-secondary: rgba(255, 255, 255, 0.9);
            --bg-tertiary: rgba(255, 255, 255, 0.5);
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-border: rgba(255, 255, 255, 0.2);
            --text-primary: var(--gray-900);
            --text-secondary: var(--gray-600);
            --text-tertiary: var(--gray-400);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --glass-border: 1px solid rgba(255, 255, 255, 0.18);
        }

        [data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            --bg-primary: #0f172a;
            --bg-secondary: rgba(30, 41, 59, 0.9);
            --bg-tertiary: rgba(15, 23, 42, 0.5);
            --card-bg: rgba(30, 41, 59, 0.9);
            --card-border: rgba(255, 255, 255, 0.05);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-tertiary: #64748b;
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --glass-border: 1px solid rgba(255, 255, 255, 0.05);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            transition: all 0.3s ease;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.05" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            pointer-events: none;
            opacity: 0.1;
        }

        /* Modern Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            backdrop-filter: blur(10px);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-400);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-500);
        }

        /* Navbar */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: var(--bg-secondary);
            border-bottom: var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .nav-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo i {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            gap: 0.5rem;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 600;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }

        .nav-link:hover {
            background: var(--bg-tertiary);
            color: var(--primary-500);
            transform: translateY(-2px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .nav-link i {
            font-size: 1rem;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            min-width: 240px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1001;
            padding: 0.5rem;
        }

        .nav-item:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            transform: translateX(5px);
        }

        .dropdown-item i {
            width: 20px;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--card-border);
            margin: 0.5rem 0;
        }

        /* Right Section */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .theme-toggle-nav, .notification-btn {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            color: var(--text-secondary);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .theme-toggle-nav:hover, .notification-btn:hover {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            transform: translateY(-2px);
        }

        .notification-btn {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--danger-500), #f87171);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
            border-radius: 30px;
            min-width: 20px;
            text-align: center;
            border: 2px solid var(--bg-secondary);
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .user-menu-btn:hover {
            background: var(--bg-secondary);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .user-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            width: 240px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1001;
            padding: 0.5rem;
        }

        .user-menu:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .user-dropdown-item:hover {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
        }

        .user-dropdown-item.logout:hover {
            background: linear-gradient(135deg, var(--danger-500), #f87171);
            color: white;
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            backdrop-filter: blur(5px);
        }

        /* Main Container */
        .main-container {
            max-width: 1440px;
            margin: 2rem auto;
            padding: 0 2rem;
            position: relative;
        }

        /* Welcome Section */
        .welcome-section {
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease;
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 24px;
            padding: 1.5rem 2rem;
            box-shadow: var(--glass-shadow);
        }

        .welcome-title h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
        }

        .welcome-title p {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.95rem;
        }

        .date-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 50px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }

        .action-message {
            background: linear-gradient(135deg, var(--success-500), #34d399);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            animation: slideIn 0.5s ease;
        }

        /* Check-in Card */
        .checkin-card {
            background: linear-gradient(135deg, var(--primary-600), var(--accent-600));
            border-radius: 30px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.3);
            animation: fadeInUp 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .checkin-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .time-display {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .time-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            backdrop-filter: blur(10px);
        }

        .time-info h2 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            font-size: 0.95rem;
        }

        .check-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .btn {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: white;
            color: var(--primary-600);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-outline:hover {
            background: white;
            color: var(--primary-600);
            transform: translateY(-3px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 24px;
            padding: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.3s ease;
            box-shadow: var(--glass-shadow);
            animation: fadeInUp 1s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: var(--primary-500);
        }

        .stat-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 24px;
            padding: 1.75rem;
            box-shadow: var(--glass-shadow);
            animation: fadeInUp 1.2s ease;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        .card-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header h2 i {
            color: var(--primary-500);
        }

        .badge {
            padding: 0.4rem 1rem;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .view-all {
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .view-all:hover {
            gap: 1rem;
        }

        /* Task List */
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .task-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 18px;
            transition: all 0.3s ease;
        }

        .task-item:hover {
            transform: translateX(5px);
            border-color: var(--primary-500);
        }

        .task-info {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
        }

        .priority-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            box-shadow: 0 0 10px currentColor;
        }

        .priority-high { 
            background: linear-gradient(135deg, var(--danger-500), #f87171);
            box-shadow: 0 0 10px var(--danger-500);
        }
        .priority-medium { 
            background: linear-gradient(135deg, var(--warning-500), #fbbf24);
            box-shadow: 0 0 10px var(--warning-500);
        }
        .priority-low { 
            background: linear-gradient(135deg, var(--success-500), #34d399);
            box-shadow: 0 0 10px var(--success-500);
        }

        .task-details h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .task-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .task-progress {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .progress-bar {
            width: 80px;
            height: 6px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-500), var(--accent-500));
            border-radius: 10px;
            transition: width 1s ease;
        }

        .task-status {
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-500);
        }

        .status-in_progress {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-500);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-500);
        }

        /* Profile Card */
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.25rem;
            position: relative;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 30px;
            border: 4px solid transparent;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500)) border-box;
            mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
            object-fit: cover;
        }

        .status-dot {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 18px;
            height: 18px;
            background: linear-gradient(135deg, var(--success-500), #34d399);
            border: 3px solid var(--card-bg);
            border-radius: 50%;
        }

        .profile-header h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .profile-header p {
            color: var(--primary-500);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px dashed var(--card-border);
        }

        .info-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-label i {
            color: var(--primary-500);
            width: 18px;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* Secondary Grid */
        .secondary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        /* Leave Grid */
        .leave-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .leave-item {
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 18px;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .leave-item:hover {
            transform: translateY(-3px);
            border-color: var(--primary-500);
        }

        .leave-count {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .leave-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* Performance Card */
        .kpi-container {
            margin: 1.5rem 0;
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .kpi-bar {
            height: 8px;
            background: var(--bg-tertiary);
            border-radius: 10px;
            overflow: hidden;
        }

        .kpi-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-500), var(--accent-500));
            border-radius: 10px;
            transition: width 1s ease;
            box-shadow: 0 0 10px var(--primary-500);
        }

        .rating-badge {
            padding: 0.4rem 1rem;
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .quick-action {
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 18px;
            padding: 1.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
            backdrop-filter: blur(5px);
        }

        .quick-action:hover {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
            transform: translateY(-3px) scale(1.05);
        }

        .quick-action i {
            font-size: 2rem;
            color: var(--primary-500);
            transition: all 0.3s ease;
        }

        .quick-action:hover i {
            color: white;
            transform: scale(1.1);
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
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            padding: 2rem;
            border-radius: 30px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--glass-shadow);
            animation: modalSlide 0.5s ease;
        }

        @keyframes modalSlide {
            from {
                transform: translateY(-50px) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: var(--glass-border);
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .close-modal {
            font-size: 2.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            line-height: 1;
        }

        .close-modal:hover {
            color: var(--danger-500);
            transform: rotate(90deg);
        }

        /* Notification List */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            border-radius: 18px;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            transform: translateX(5px);
        }

        .notification-item.unread {
            background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
            color: white;
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .icon-success { background: linear-gradient(135deg, var(--success-500), #34d399); }
        .icon-info { background: linear-gradient(135deg, var(--info-500), #60a5fa); }
        .icon-warning { background: linear-gradient(135deg, var(--warning-500), #fbbf24); }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            font-weight: 600;
            margin-bottom: 0.5rem;
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
            background: linear-gradient(135deg, var(--primary-600), var(--accent-600));
            border-radius: 20px;
            padding: 1.75rem;
            color: white;
            margin-bottom: 1.75rem;
        }

        .salary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
        }

        .salary-total {
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid rgba(255,255,255,0.2);
        }

        /* Footer */
        footer {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 24px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(99, 102, 241, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-grid, .secondary-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .user-info {
                display: none;
            }
            
            .nav-container {
                padding: 0.75rem 1rem;
            }
            
            .main-container {
                padding: 0 1rem;
            }
            
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .checkin-card {
                flex-direction: column;
                text-align: center;
            }
            
            .time-display {
                justify-content: center;
            }
            
            .check-buttons {
                width: 100%;
            }
            
            .btn {
                flex: 1;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .leave-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 998;
            display: none;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-right: var(--glass-border);
            z-index: 999;
            padding: 2rem 1rem;
            transition: left 0.3s ease;
            overflow-y: auto;
        }

        .mobile-menu.open {
            left: 0;
        }

        .mobile-nav-item {
            margin-bottom: 1rem;
        }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 12px;
            background: var(--bg-tertiary);
            border: var(--glass-border);
            font-weight: 600;
            cursor: pointer;
        }

        .mobile-dropdown {
            margin-left: 1rem;
            padding: 0.5rem 0;
            display: none;
        }

        .mobile-dropdown.show {
            display: block;
        }

        .mobile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .mobile-dropdown-item:hover {
            background: var(--primary-500);
            color: white;
        }
    </style>