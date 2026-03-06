<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Manager") {
    header("Location: login.php");
    exit;
}
?>

<?php
session_start();

/* USER - Now CEO */
$user = "CEO - Strategic Dashboard";
$companyName = "MPSS Enterprises";
$fiscalYear = "2026";

/* SAMPLE DATA (replace with DB later) */
$totalEmployees = 25;
$performanceReviews = 18;
$payrollGenerated = 12;

/* Employee Performance Data */
$employees = [
    [
        'id' => 1,
        'name' => 'John Doe',
        'department' => 'IT',
        'position' => 'Senior Developer',
        'kpi_score' => 85,
        'base_salary' => 45000,
        'tasks_completed' => 45,
        'total_tasks' => 50,
        'weekend_hours' => 12,
        'attendance' => 96,
        'join_date' => '2023-01-15',
        'email' => 'john.doe@company.com',
        'phone' => '+91 98765 43210',
        'emergency_contact' => '+91 99887 66554'
    ],
    [
        'id' => 2,
        'name' => 'Jane Smith',
        'department' => 'HR',
        'position' => 'HR Executive',
        'kpi_score' => 78,
        'base_salary' => 35000,
        'tasks_completed' => 38,
        'total_tasks' => 45,
        'weekend_hours' => 8,
        'attendance' => 98,
        'join_date' => '2023-03-20',
        'email' => 'jane.smith@company.com',
        'phone' => '+91 98765 43211',
        'emergency_contact' => '+91 99887 66555'
    ],
    [
        'id' => 3,
        'name' => 'Mike Johnson',
        'department' => 'Sales',
        'position' => 'Sales Manager',
        'kpi_score' => 92,
        'base_salary' => 55000,
        'tasks_completed' => 52,
        'total_tasks' => 55,
        'weekend_hours' => 15,
        'attendance' => 94,
        'join_date' => '2022-11-10',
        'email' => 'mike.johnson@company.com',
        'phone' => '+91 98765 43212',
        'emergency_contact' => '+91 99887 66556'
    ],
    [
        'id' => 4,
        'name' => 'Sarah Williams',
        'department' => 'Finance',
        'position' => 'Accountant',
        'kpi_score' => 71,
        'base_salary' => 40000,
        'tasks_completed' => 32,
        'total_tasks' => 42,
        'weekend_hours' => 5,
        'attendance' => 99,
        'join_date' => '2023-06-05',
        'email' => 'sarah.williams@company.com',
        'phone' => '+91 98765 43213',
        'emergency_contact' => '+91 99887 66557'
    ],
    [
        'id' => 5,
        'name' => 'David Brown',
        'department' => 'Marketing',
        'position' => 'Marketing Specialist',
        'kpi_score' => 88,
        'base_salary' => 42000,
        'tasks_completed' => 48,
        'total_tasks' => 52,
        'weekend_hours' => 10,
        'attendance' => 95,
        'join_date' => '2023-02-28',
        'email' => 'david.brown@company.com',
        'phone' => '+91 98765 43214',
        'emergency_contact' => '+91 99887 66558'
    ]
];

// Initialize upload history in session if not exists
if (!isset($_SESSION['upload_history'])) {
    $_SESSION['upload_history'] = [];
}

/* Handle bulk upload */
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bulk_employee_file'])) {
    $file = $_FILES['bulk_employee_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext == 'csv') {
        $result = processBulkUploadCSV($file['tmp_name'], $file['name']);
        if ($result['success']) {
            $upload_message = "Successfully uploaded " . $result['count'] . " employees! Salaries generated automatically.";
            if (!empty($result['message'])) {
                $upload_error = $result['message'];
            }
        } else {
            $upload_error = $result['message'];
        }
    } else {
        $upload_error = "Please upload only CSV files";
    }
}

// Process bulk upload CSV
function processBulkUploadCSV($file_path, $original_filename) {
    $handle = fopen($file_path, "r");
    $headers = fgetcsv($handle); // Get headers
    
    $success_count = 0;
    $errors = [];
    $uploaded_data = [];
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        // Map CSV columns to employee data
        if (count($data) >= 5) {
            // Get the next available ID
            $max_id = 0;
            foreach ($GLOBALS['employees'] as $emp) {
                $max_id = max($max_id, $emp['id']);
            }
            
            $new_employee = [
                'id' => $max_id + $success_count + 1,
                'name' => $data[0],
                'department' => $data[1],
                'position' => $data[2],
                'kpi_score' => isset($data[3]) ? floatval($data[3]) : 0,
                'base_salary' => floatval($data[4]),
                'tasks_completed' => isset($data[5]) ? intval($data[5]) : 0,
                'total_tasks' => isset($data[6]) ? intval($data[6]) : 0,
                'weekend_hours' => isset($data[7]) ? intval($data[7]) : 0,
                'attendance' => isset($data[8]) ? floatval($data[8]) : 0,
                'join_date' => isset($data[9]) ? $data[9] : date('Y-m-d'),
                'email' => isset($data[10]) ? $data[10] : '',
                'phone' => isset($data[11]) ? $data[11] : '',
                'emergency_contact' => isset($data[12]) ? $data[12] : ''
            ];
            
            // Validate required fields
            if (!empty($new_employee['name']) && !empty($new_employee['department']) && 
                !empty($new_employee['position']) && $new_employee['base_salary'] > 0) {
                
                // Add to employees array
                $GLOBALS['employees'][] = $new_employee;
                $uploaded_data[] = $new_employee;
                $success_count++;
            } else {
                $errors[] = "Row " . ($success_count + 2) . ": Missing required fields";
            }
        }
    }
    
    fclose($handle);
    
    // Update total employees count
    $GLOBALS['totalEmployees'] = count($GLOBALS['employees']);
    
    // Add to upload history
    $history_entry = [
        'id' => count($_SESSION['upload_history']) + 1,
        'filename' => $original_filename,
        'upload_date' => date('Y-m-d H:i:s'),
        'uploaded_by' => $GLOBALS['user'],
        'records_processed' => $success_count,
        'errors' => count($errors),
        'status' => count($errors) > 0 ? 'Partial Success' : 'Success',
        'employees_added' => $uploaded_data
    ];
    
    array_unshift($_SESSION['upload_history'], $history_entry); // Add to beginning of array
    
    return [
        'success' => true,
        'count' => $success_count,
        'message' => implode("<br>", $errors)
    ];
}

/* KPI & SALARY Functions */
function getRating($score){
    if($score >= 90) return "Excellent";
    if($score >= 75) return "Good";
    if($score >= 60) return "Average";
    return "Poor";
}

function getRatingColor($rating){
    return match($rating){
        "Excellent" => "#22c55e",
        "Good" => "#2563eb",
        "Average" => "#facc15",
        "Poor" => "#ef4444",
        default => "#64748b"
    };
}

function getIncrement($rating){
    return match($rating){
        "Excellent" => 0.15,
        "Good" => 0.10,
        "Average" => 0.05,
        default => 0
    };
}

function calculateWeekendBonus($weekendHours) {
    // Weekend bonus rate: ₹500 per hour
    return $weekendHours * 500;
}

function calculatePerformanceBonus($kpiScore, $baseSalary) {
    if($kpiScore >= 90) return $baseSalary * 0.20;
    if($kpiScore >= 75) return $baseSalary * 0.10;
    if($kpiScore >= 60) return $baseSalary * 0.05;
    return 0;
}

function calculateTotalSalary($baseSalary, $kpiScore, $weekendHours) {
    $rating = getRating($kpiScore);
    $increment = getIncrement($rating);
    $performanceBonus = calculatePerformanceBonus($kpiScore, $baseSalary);
    $weekendBonus = calculateWeekendBonus($weekendHours);
    
    $salaryWithIncrement = $baseSalary + ($baseSalary * $increment);
    $totalSalary = $salaryWithIncrement + $performanceBonus + $weekendBonus;
    
    return [
        'base' => $baseSalary,
        'increment' => $increment,
        'increment_amount' => $baseSalary * $increment,
        'performance_bonus' => $performanceBonus,
        'weekend_bonus' => $weekendBonus,
        'total' => $totalSalary,
        'rating' => $rating
    ];
}

// Calculate company statistics
$totalBaseSalary = array_sum(array_column($employees, 'base_salary'));
$totalWeekendHours = array_sum(array_column($employees, 'weekend_hours'));
$avgKpi = count($employees) > 0 ? array_sum(array_column($employees, 'kpi_score')) / count($employees) : 0;
$avgAttendance = count($employees) > 0 ? array_sum(array_column($employees, 'attendance')) / count($employees) : 0;
$totalTasksCompleted = array_sum(array_column($employees, 'tasks_completed'));
$totalTasks = array_sum(array_column($employees, 'total_tasks'));

// Calculate total projected salary with bonuses
$totalProjectedSalary = 0;
$departmentStats = [];
$ratingDistribution = ['Excellent' => 0, 'Good' => 0, 'Average' => 0, 'Poor' => 0];

foreach ($employees as $emp) {
    $salaryDetails = calculateTotalSalary($emp['base_salary'], $emp['kpi_score'], $emp['weekend_hours']);
    $totalProjectedSalary += $salaryDetails['total'];
    
    // Department stats
    $dept = $emp['department'];
    if (!isset($departmentStats[$dept])) {
        $departmentStats[$dept] = [
            'count' => 0,
            'totalSalary' => 0,
            'avgKpi' => 0,
            'kpiSum' => 0
        ];
    }
    $departmentStats[$dept]['count']++;
    $departmentStats[$dept]['totalSalary'] += $salaryDetails['total'];
    $departmentStats[$dept]['kpiSum'] += $emp['kpi_score'];
    
    // Rating distribution
    $rating = getRating($emp['kpi_score']);
    $ratingDistribution[$rating]++;
}

// Calculate department averages
foreach ($departmentStats as $dept => &$stats) {
    $stats['avgKpi'] = $stats['kpiSum'] / $stats['count'];
}

// CEO-specific calculations
$totalPayrollCost = $totalProjectedSalary;
$avgSalaryPerEmployee = $totalEmployees > 0 ? $totalProjectedSalary / $totalEmployees : 0;
$productivityScore = $totalTasks > 0 ? ($totalTasksCompleted / $totalTasks) * 100 : 0;
$attendanceRate = $avgAttendance;
$topPerformerCount = $ratingDistribution['Excellent'] + $ratingDistribution['Good'];
$topPerformerPercentage = $totalEmployees > 0 ? ($topPerformerCount / $totalEmployees) * 100 : 0;

// Get current month and year
$currentMonth = date('F Y');
$currentQuarter = 'Q' . ceil(date('n') / 3) . ' ' . date('Y');

/* ===== CLEAR UPLOAD HISTORY ===== */
if (isset($_POST['clear_history'])) {
    $_SESSION['upload_history'] = [];   // Clear session history
    header("Location: " . $_SERVER['PHP_SELF']); // Refresh page
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MPSS CEO Dashboard - Strategic Overview <?= $fiscalYear ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #1e3a8a;
        --primary-dark: #0f172a;
        --primary-light: #3b82f6;
        --secondary: #64748b;
        --success: #22c55e;
        --warning: #facc15;
        --danger: #ef4444;
        --info: #3b82f6;
        --purple: #8b5cf6;
        --pink: #ec4899;
        --orange: #f97316;
        --dark: #020617;
        --light: #f8fafc;
        --card-bg: rgba(255, 255, 255, 0.95);
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: var(--dark);
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary), var(--purple));
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    /* Glassmorphism Effect */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: var(--shadow-lg);
    }

    /* Navbar */
    .navbar {
        height: 80px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
        box-shadow: var(--shadow-lg);
        border-bottom: 3px solid var(--warning);
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-100%);
        }
        to {
            transform: translateY(0);
        }
    }

    .logo-area {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .logo-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--warning), var(--orange));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transform: rotate(-5deg);
        transition: all 0.3s;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            transform: rotate(-5deg) scale(1);
        }
        50% {
            transform: rotate(-5deg) scale(1.05);
        }
    }

    .logo-icon:hover {
        transform: rotate(0deg) scale(1.1);
    }

    .logo-text {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 1px;
        background: linear-gradient(135deg, var(--warning), #fff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .menu-btn {
        font-size: 22px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .menu-btn:hover {
        transform: scale(1.1);
        color: var(--warning);
    }

    .user-profile {
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 25px;
        border-radius: 50px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .user-profile:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--warning), var(--orange));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 18px;
    }

    .user-info {
        line-height: 1.4;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
    }

    .user-role {
        font-size: 11px;
        opacity: 0.8;
    }

    /* Sidebar */
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, var(--primary-dark), #1e293b);
        position: fixed;
        top: 80px;
        left: 0;
        height: calc(100vh - 80px);
        transition: all 0.3s ease;
        z-index: 999;
        overflow-y: auto;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
        animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(-100%);
        }
        to {
            transform: translateX(0);
        }
    }

    .sidebar.hide {
        left: -280px;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .sidebar-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 25px;
        color: #cbd5e1;
        text-decoration: none;
        transition: all 0.3s;
        border-left: 4px solid transparent;
        margin: 5px 15px;
        border-radius: 0 12px 12px 0;
        cursor: pointer;
        font-weight: 500;
    }

    .sidebar-item i {
        width: 24px;
        font-size: 18px;
    }

    .sidebar-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-left-color: var(--warning);
        transform: translateX(5px);
    }

    .sidebar-item.active {
        background: linear-gradient(90deg, rgba(37, 99, 235, 0.3), transparent);
        color: white;
        border-left-color: var(--warning);
    }

    .sidebar-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        margin: 15px 20px;
    }

    /* Main Content */
    .main {
        margin-left: 280px;
        padding: 100px 30px 30px;
        transition: all 0.3s;
    }

    .main.expand {
        margin-left: 0;
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, var(--primary), var(--purple));
        border-radius: 30px;
        padding: 40px;
        color: white;
        margin-bottom: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--shadow-xl);
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: rgba(255, 255, 255, 0.1);
        transform: rotate(45deg);
        animation: shine 3s infinite;
    }

    @keyframes shine {
        0% {
            transform: rotate(45deg) translateX(-100%);
        }
        100% {
            transform: rotate(45deg) translateX(100%);
        }
    }

    .banner-content {
        position: relative;
        z-index: 1;
    }

    .banner-content h1 {
        font-size: 42px;
        margin-bottom: 15px;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .banner-content p {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 20px;
    }

    .banner-date {
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 20px;
        border-radius: 30px;
        display: inline-block;
        font-size: 14px;
        backdrop-filter: blur(5px);
    }

    .banner-stats {
        display: flex;
        gap: 50px;
        position: relative;
        z-index: 1;
    }

    .banner-stat {
        text-align: center;
    }

    .banner-stat .value {
        font-size: 48px;
        font-weight: 700;
        line-height: 1;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .banner-stat .label {
        font-size: 13px;
        opacity: 0.8;
        margin-top: 8px;
    }

    /* Executive Cards */
    .executive-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .exec-card {
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 24px;
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease;
    }

    .exec-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
    }

    .exec-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--purple));
    }

    .exec-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .exec-card-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary), var(--purple));
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 28px;
        box-shadow: var(--shadow-lg);
    }

    .exec-card-label {
        font-size: 14px;
        color: var(--secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .exec-card-value {
        font-size: 42px;
        font-weight: 700;
        color: var(--dark);
        margin: 10px 0;
        line-height: 1;
    }

    .exec-card-trend {
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .trend-up {
        color: var(--success);
    }

    .trend-down {
        color: var(--danger);
    }

    /* Insights Grid */
    .insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .insight-card {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: var(--shadow-md);
        transition: all 0.3s;
        cursor: pointer;
        border: 1px solid #e5e7eb;
        animation: fadeInUp 0.7s ease;
    }

    .insight-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-light);
    }

    .insight-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .insight-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary), var(--purple));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 22px;
    }

    .insight-title {
        font-weight: 600;
        color: var(--dark);
        font-size: 16px;
    }

    .insight-value {
        font-size: 36px;
        font-weight: 700;
        color: var(--primary);
        margin: 10px 0;
    }

    .insight-desc {
        font-size: 13px;
        color: var(--secondary);
        margin-bottom: 15px;
    }

    /* Charts Grid */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
        margin-bottom: 40px;
    }

    .chart-card {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        animation: fadeInUp 0.8s ease;
    }

    .chart-card h3 {
        margin-bottom: 20px;
        color: var(--dark);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-card h3 i {
        color: var(--primary);
    }

    .chart-container {
        height: 300px;
        position: relative;
    }

    /* Department Grid */
    .dept-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .dept-card {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        border-left: 4px solid;
        transition: all 0.3s;
        cursor: pointer;
        animation: fadeInUp 0.9s ease;
    }

    .dept-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }

    .dept-card h4 {
        font-size: 16px;
        margin-bottom: 10px;
        color: var(--dark);
    }

    .dept-card .score {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .dept-card .employees {
        font-size: 13px;
        color: var(--secondary);
        margin-bottom: 15px;
    }

    .dept-progress {
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        margin: 10px 0;
        overflow: hidden;
    }

    .dept-progress-bar {
        height: 100%;
        border-radius: 4px;
        transition: width 0.5s ease;
    }

    /* Sections */
    .section {
        background: white;
        border-radius: 24px;
        padding: 30px;
        box-shadow: var(--shadow-md);
        margin-bottom: 40px;
        border: 1px solid #e5e7eb;
        animation: fadeInUp 1s ease;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
    }

    .section-header h2 {
        color: var(--dark);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 22px;
    }

    .section-header h2 i {
        color: var(--primary);
    }

    /* Tables */
    .table-container {
        overflow-x: auto;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    th {
        background: #f8fafc;
        color: var(--dark);
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        border-bottom: 2px solid #e5e7eb;
    }

    td {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    tr {
        transition: all 0.2s;
    }

    tr:hover {
        background: #f8fafc;
        transform: scale(1.01);
        box-shadow: var(--shadow-sm);
    }

    /* Badges */
    .badge {
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        transition: all 0.3s;
    }

    .badge:hover {
        transform: scale(1.05);
    }

    .badge.excellent {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid #22c55e;
    }

    .badge.good {
        background: rgba(37, 99, 235, 0.15);
        color: #2563eb;
        border: 1px solid #2563eb;
    }

    .badge.average {
        background: rgba(250, 204, 21, 0.15);
        color: #facc15;
        border: 1px solid #facc15;
    }

    .badge.poor {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid #ef4444;
    }

    .badge.success {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid #22c55e;
    }

    .badge.warning {
        background: rgba(250, 204, 21, 0.15);
        color: #facc15;
        border: 1px solid #facc15;
    }

    .badge.primary {
        background: rgba(30, 58, 138, 0.15);
        color: #1e3a8a;
        border: 1px solid #1e3a8a;
    }

    .badge.purple {
        background: rgba(139, 92, 246, 0.15);
        color: #8b5cf6;
        border: 1px solid #8b5cf6;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 8px 12px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .action-btn.view {
        background: #3b82f6;
        color: white;
    }

    .action-btn.view:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
    }

    .action-btn.salary {
        background: #22c55e;
        color: white;
    }

    .action-btn.salary:hover {
        background: #16a34a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(34, 197, 94, 0.3);
    }

    .action-btn.email {
        background: #f97316;
        color: white;
    }

    .action-btn.email:hover {
        background: #ea580c;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
    }

    .action-btn.edit {
        background: #8b5cf6;
        color: white;
    }

    .action-btn.edit:hover {
        background: #7c3aed;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(139, 92, 246, 0.3);
    }

    .action-btn.delete {
        background: #ef4444;
        color: white;
    }

    .action-btn.delete:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
    }

    .action-btn.history {
        background: #64748b;
        color: white;
    }

    .action-btn.history:hover {
        background: #475569;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(100, 116, 139, 0.3);
    }

    .action-btn.manual {
        background: #f97316;
        color: white;
    }

    .action-btn.manual:hover {
        background: #ea580c;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
    }

    /* Progress Bar */
    .progress-bar-container {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.5s ease;
        position: relative;
        overflow: hidden;
    }

    .progress-bar-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        animation: shimmer 1.5s infinite;
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(100%);
        }
    }

    /* Financial Summary Cards */
    .financial-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .financial-card {
        background: #f8fafc;
        padding: 20px;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid #e5e7eb;
    }

    .financial-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .financial-card .label {
        font-size: 12px;
        color: var(--secondary);
        margin-bottom: 8px;
    }

    .financial-card .value {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
    }

    .financial-card .trend {
        font-size: 11px;
        margin-top: 8px;
    }

    /* Upload Zone */
    .upload-zone {
        border: 3px dashed var(--primary);
        border-radius: 20px;
        padding: 50px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 20px;
    }

    .upload-zone:hover {
        background: linear-gradient(135deg, #f0f4ff, #f8fafc);
        border-color: var(--purple);
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }

    .upload-zone i {
        font-size: 64px;
        color: var(--primary);
        margin-bottom: 20px;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .upload-zone h3 {
        margin-bottom: 10px;
        color: var(--dark);
    }

    .upload-zone p {
        color: var(--secondary);
    }

    .upload-zone.dragover {
        background: #e0e7ff;
        border-color: var(--purple);
        transform: scale(1.02);
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--purple));
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .btn-success {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-success:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px #22c55e;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-warning:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px #f97316;
    }

    .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
    }

    .btn-outline:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-3px);
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 12px;
    }

    .btn-lg {
        padding: 16px 32px;
        font-size: 16px;
    }

    .btn-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 24px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        animation: modalSlide 0.3s ease;
    }

    @keyframes modalSlide {
        from {
            opacity: 0;
            transform: translateY(-30px);
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
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }

    .modal-header h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--dark);
    }

    .modal-header h2 i {
        color: var(--primary);
    }

    .close-modal {
        font-size: 24px;
        cursor: pointer;
        color: var(--secondary);
        transition: all 0.2s;
    }

    .close-modal:hover {
        color: var(--danger);
        transform: scale(1.1);
    }

    .modal-form-group {
        margin-bottom: 20px;
    }

    .modal-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark);
    }

    .modal-form-group input,
    .modal-form-group select,
    .modal-form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .modal-form-group input:focus,
    .modal-form-group select:focus,
    .modal-form-group textarea:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 30px;
    }

    .confirmation-dialog {
        text-align: center;
        padding: 20px;
    }

    .confirmation-icon {
        font-size: 64px;
        color: var(--warning);
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }

    .confirmation-message {
        font-size: 18px;
        margin-bottom: 20px;
        color: var(--dark);
    }

    .confirmation-details {
        background: #f8fafc;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        text-align: left;
    }

    /* Spinner */
    .spinner {
        text-align: center;
        margin-top: 20px;
    }

    .spinner i {
        color: var(--primary);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Alert Messages */
    .message-success {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
        padding: 15px;
        border-radius: 12px;
        margin: 20px 0;
        border: 1px solid #22c55e;
        animation: slideIn 0.3s ease;
    }

    .message-error {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        padding: 15px;
        border-radius: 12px;
        margin: 20px 0;
        border: 1px solid #ef4444;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Filter Select */
    .filter-select {
        padding: 10px 20px;
        border-radius: 12px;
        border: 2px solid #e5e7eb;
        font-size: 14px;
        min-width: 250px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-select:focus {
        border-color: var(--primary);
        outline: none;
    }

    /* Salary Input Group */
    .salary-input-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .salary-input {
        flex: 1;
        padding: 8px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 13px;
        text-align: right;
        transition: all 0.2s;
    }

    .salary-input:focus {
        border-color: var(--primary);
        outline: none;
    }

    .salary-actions {
        display: flex;
        gap: 4px;
    }

    .salary-display {
        font-weight: 600;
        color: var(--primary);
    }

    .manual-badge {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
    }

    /* Summary Section */
    .salary-summary {
        background: linear-gradient(135deg, #f8fafc, #e0e7ff);
        padding: 20px;
        border-radius: 16px;
        margin: 20px 0;
    }

    .summary-item {
        text-align: center;
    }

    .summary-item .label {
        font-size: 12px;
        color: var(--secondary);
        margin-bottom: 5px;
    }

    .summary-item .value {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
    }

    /* Footer */
    .footer {
        text-align: center;
        margin-top: 50px;
        padding: 30px;
        color: var(--secondary);
        border-top: 1px solid #e5e7eb;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .sidebar {
            left: -280px;
        }
        
        .sidebar.show {
            left: 0;
        }
        
        .main {
            margin-left: 0;
        }
        
        .charts-grid {
            grid-template-columns: 1fr;
        }
        
        .insights-grid {
            grid-template-columns: 1fr;
        }
        
        .financial-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .navbar {
            padding: 0 20px;
        }
        
        .logo-text {
            display: none;
        }
        
        .user-info {
            display: none;
        }
        
        .welcome-banner {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .banner-stats {
            justify-content: center;
        }
        
        .financial-grid {
            grid-template-columns: 1fr;
        }
        
        .executive-grid {
            grid-template-columns: 1fr;
        }
        
        .dept-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Print Styles */
    @media print {
        .navbar, .sidebar, .btn, .action-buttons, .upload-zone {
            display: none;
        }
        
        .main {
            margin: 0;
            padding: 20px;
        }
        
        .section {
            box-shadow: none;
            border: 1px solid #ccc;
        }
    }
</style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <div class="logo-area">
        <div class="menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </div>
        <div class="logo-icon">
            <i class="fas fa-chart-pie"></i>
        </div>
        <div class="logo-text">MPSS | CEO Dashboard</div>
    </div>
    
    <div class="user-profile">
        <div class="user-avatar">
            <i class="fas fa-crown"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?= $user ?></div>
            <div class="user-role">Strategic Command Center</div>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-menu">
        <div class="sidebar-item active" onclick="scrollToTop()">
            <i class="fas fa-tachometer-alt"></i>
            <span>Strategic Overview</span>
        </div>
        <div class="sidebar-item" onclick="scrollToFinancial()">
            <i class="fas fa-chart-line"></i>
            <span>Financial Insights</span>
        </div>
        <div class="sidebar-item" onclick="scrollToTalent()">
            <i class="fas fa-users-cog"></i>
            <span>Talent Management</span>
        </div>
        <div class="sidebar-item" onclick="scrollToOperations()">
            <i class="fas fa-chart-bar"></i>
            <span>Operations</span>
        </div>
        <div class="sidebar-item" onclick="scrollToEmployees()">
            <i class="fas fa-id-badge"></i>
            <span>Employee Directory</span>
        </div>
        
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-item" onclick="scrollToBulkUpload()">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Data Import</span>
        </div>
        <div class="sidebar-item" onclick="scrollToUploadHistory()">
            <i class="fas fa-history"></i>
            <span>Upload History</span>
        </div>
        <div class="sidebar-item" onclick="scrollToUploadedFiles()">
            <i class="fas fa-file-invoice"></i>
            <span>File Review</span>
        </div>
        
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-item" onclick="window.location.href='login.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main" id="main">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="banner-content">
            <h1>Good day, CEO</h1>
            <p>Welcome to your strategic command center • <?= $currentQuarter ?></p>
            <div class="banner-date">
                <i class="far fa-calendar-alt"></i> <?= $currentMonth ?>
            </div>
        </div>
        <div class="banner-stats">
            <div class="banner-stat">
                <div class="value"><?= $totalEmployees ?></div>
                <div class="label">Total Employees</div>
            </div>
            <div class="banner-stat">
                <div class="value"><?= number_format($topPerformerPercentage, 0) ?>%</div>
                <div class="label">Top Performers</div>
            </div>
            <div class="banner-stat">
                <div class="value">₹<?= number_format(round($totalPayrollCost/100000, 1)) ?>L</div>
                <div class="label">Payroll Cost</div>
            </div>
        </div>
    </div>

    <!-- Executive KPI Cards -->
    <div class="executive-grid">
        <div class="exec-card" onclick="showKpiDetails('revenue')">
            <div class="exec-card-header">
                <div class="exec-card-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="exec-card-label">Revenue per Employee</div>
            </div>
            <div class="exec-card-value">₹<?= number_format(round($avgSalaryPerEmployee * 2.5)) ?></div>
            <div class="exec-card-trend trend-up">
                <i class="fas fa-arrow-up"></i> +12.3% vs last quarter
            </div>
        </div>

        <div class="exec-card" onclick="showKpiDetails('productivity')">
            <div class="exec-card-header">
                <div class="exec-card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="exec-card-label">Productivity Index</div>
            </div>
            <div class="exec-card-value"><?= number_format($productivityScore, 1) ?>%</div>
            <div class="exec-card-trend trend-up">
                <i class="fas fa-arrow-up"></i> +5.2% vs target
            </div>
        </div>

        <div class="exec-card" onclick="showKpiDetails('satisfaction')">
            <div class="exec-card-header">
                <div class="exec-card-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <div class="exec-card-label">Employee Satisfaction</div>
            </div>
            <div class="exec-card-value"><?= number_format($avgAttendance, 1) ?>%</div>
            <div class="exec-card-trend trend-up">
                <i class="fas fa-arrow-up"></i> +2.1% vs last month
            </div>
        </div>

        <div class="exec-card" onclick="showKpiDetails('retention')">
            <div class="exec-card-header">
                <div class="exec-card-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="exec-card-label">Talent Retention</div>
            </div>
            <div class="exec-card-value">94%</div>
            <div class="exec-card-trend trend-up">
                <i class="fas fa-arrow-up"></i> Above industry avg
            </div>
        </div>
    </div>

    <!-- Strategic Insights -->
    <div class="insights-grid">
        <div class="insight-card" onclick="showInsightDetails('topPerformers')">
            <div class="insight-header">
                <div class="insight-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="insight-title">Top Performers</div>
            </div>
            <div class="insight-value"><?= $topPerformerCount ?></div>
            <div class="insight-desc">Employees rated Excellent/Good (<?= number_format($topPerformerPercentage, 1) ?>% of workforce)</div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= $topPerformerPercentage ?>%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
            </div>
        </div>

        <div class="insight-card" onclick="showInsightDetails('development')">
            <div class="insight-header">
                <div class="insight-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="insight-title">Development Needed</div>
            </div>
            <div class="insight-value"><?= $ratingDistribution['Average'] + $ratingDistribution['Poor'] ?></div>
            <div class="insight-desc">Employees requiring performance improvement</div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= (($ratingDistribution['Average'] + $ratingDistribution['Poor']) / $totalEmployees) * 100 ?>%; background: linear-gradient(90deg, #f97316, #ea580c);"></div>
            </div>
        </div>

        <div class="insight-card" onclick="showInsightDetails('weekend')">
            <div class="insight-header">
                <div class="insight-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="insight-title">Weekend Work</div>
            </div>
            <div class="insight-value"><?= $totalWeekendHours ?> hrs</div>
            <div class="insight-desc">Total weekend hours this month (₹<?= number_format($totalWeekendHours * 500) ?> bonus payout)</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3><i class="fas fa-building"></i> Department Performance</h3>
            <div class="chart-container">
                <canvas id="deptChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Performance Rating Distribution</h3>
            <div class="chart-container">
                <canvas id="ratingChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Department Performance Cards -->
    <div class="section">
        <div class="section-header">
            <h2><i class="fas fa-chart-simple"></i> Department Performance Overview</h2>
            <button class="btn btn-outline btn-sm" onclick="exportDepartmentReport()">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>

        <div class="dept-grid">
            <?php foreach ($departmentStats as $dept => $stats): 
                $color = match($dept) {
                    'IT' => '#3b82f6',
                    'HR' => '#8b5cf6',
                    'Sales' => '#22c55e',
                    'Finance' => '#f97316',
                    'Marketing' => '#ec4899',
                    default => '#64748b'
                };
            ?>
            <div class="dept-card" style="border-left-color: <?= $color ?>" onclick="showDepartmentDetails('<?= $dept ?>')">
                <h4><?= $dept ?></h4>
                <div class="score"><?= number_format($stats['avgKpi'], 1) ?>%</div>
                <div class="employees"><?= $stats['count'] ?> employees • ₹<?= number_format(round($stats['totalSalary']/100000, 1)) ?>L payroll</div>
                <div class="dept-progress">
                    <div class="dept-progress-bar" style="width: <?= $stats['avgKpi'] ?>%; background: <?= $color ?>"></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 12px;">
                    <span>Target: 80%</span>
                    <span><?= $stats['avgKpi'] >= 80 ? '✓ On Track' : '⚠ Below Target' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Financial Overview Section -->
    <div class="section" id="financial-section">
        <div class="section-header">
            <h2><i class="fas fa-coins"></i> Financial Overview & Salary Projections</h2>
            <div class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="showProcessPayrollModal()">
                    <i class="fas fa-play"></i> Process Payroll
                </button>
                <button class="btn btn-outline btn-sm" onclick="showFinancialForecastModal()">
                    <i class="fas fa-chart-line"></i> Forecast
                </button>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="financial-grid">
            <div class="financial-card" onclick="showFinancialDetail('payroll')">
                <div class="label">Total Payroll</div>
                <div class="value">₹<?= number_format(round($totalPayrollCost)) ?></div>
                <div class="trend trend-up"><i class="fas fa-arrow-up"></i> +8.3% vs last month</div>
            </div>
            <div class="financial-card" onclick="showFinancialDetail('avgSalary')">
                <div class="label">Avg Salary</div>
                <div class="value">₹<?= number_format(round($avgSalaryPerEmployee)) ?></div>
                <div class="trend">Per employee</div>
            </div>
            <div class="financial-card" onclick="showFinancialDetail('bonus')">
                <div class="label">Bonus Payout</div>
                <div class="value">₹<?= number_format(round(array_sum(array_map(function($emp) {
                    return calculatePerformanceBonus($emp['kpi_score'], $emp['base_salary']) + calculateWeekendBonus($emp['weekend_hours']);
                }, $employees)))) ?></div>
                <div class="trend">Performance + Weekend</div>
            </div>
            <div class="financial-card" onclick="showFinancialDetail('costPerHire')">
                <div class="label">Cost per Hire</div>
                <div class="value">₹15,000</div>
                <div class="trend trend-up">-5% vs industry avg</div>
            </div>
        </div>

        <!-- Salary Table with CEO View -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Base Salary</th>
                        <th>Performance Bonus</th>
                        <th>Weekend Bonus</th>
                        <th>Total Compensation</th>
                        <th>KPI Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($employees as $emp): 
                        $salaryDetails = calculateTotalSalary($emp['base_salary'], $emp['kpi_score'], $emp['weekend_hours']);
                        $grandTotal += $salaryDetails['total'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= $emp['name'] ?></strong>
                            <br><small style="color: #64748b;"><?= $emp['position'] ?></small>
                        </td>
                        <td><?= $emp['department'] ?></td>
                        <td>₹<?= number_format($emp['base_salary']) ?></td>
                        <td>₹<?= number_format($salaryDetails['performance_bonus']) ?></td>
                        <td>₹<?= number_format($salaryDetails['weekend_bonus']) ?></td>
                        <td><strong>₹<?= number_format($salaryDetails['total']) ?></strong></td>
                        <td>
                            <span class="badge <?= strtolower($salaryDetails['rating']) ?>">
                                <?= $salaryDetails['rating'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewEmployeeDetails(<?= $emp['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn salary" onclick="calculateEmployeeSalary(<?= $emp['id'] ?>)">
                                    <i class="fas fa-calculator"></i>
                                </button>
                                <button class="action-btn email" onclick="showEmailModal(<?= $emp['id'] ?>)">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <button class="action-btn history" onclick="viewEmployeeHistory(<?= $emp['id'] ?>)">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8fafc; font-weight: 700;">
                    <tr>
                        <td colspan="5" style="text-align: right;">Total Payroll Cost:</td>
                        <td colspan="3">₹<?= number_format($grandTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Talent Management Section -->
    <div class="section" id="talent-section">
        <div class="section-header">
            <h2><i class="fas fa-users"></i> Talent Management & Performance</h2>
            <button class="btn btn-primary btn-sm" onclick="showTalentAnalyticsModal()">
                <i class="fas fa-chart-pie"></i> Talent Analytics
            </button>
        </div>

        <!-- Employee Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>KPI Score</th>
                        <th>Rating</th>
                        <th>Tasks</th>
                        <th>Attendance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): 
                        $rating = getRating($emp['kpi_score']);
                    ?>
                    <tr>
                        <td><strong><?= $emp['name'] ?></strong></td>
                        <td><?= $emp['department'] ?></td>
                        <td><?= $emp['position'] ?></td>
                        <td><?= $emp['kpi_score'] ?>%</td>
                        <td><span class="badge <?= strtolower($rating) ?>"><?= $rating ?></span></td>
                        <td><?= $emp['tasks_completed'] ?>/<?= $emp['total_tasks'] ?></td>
                        <td><?= $emp['attendance'] ?>%</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewEmployeeDetails(<?= $emp['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn salary" onclick="calculateEmployeeSalary(<?= $emp['id'] ?>)">
                                    <i class="fas fa-calculator"></i>
                                </button>
                                <button class="action-btn edit" onclick="showEditEmployeeModal(<?= $emp['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn history" onclick="viewEmployeeHistory(<?= $emp['id'] ?>)">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Upload Messages -->
    <?php if ($upload_message): ?>
        <div class="message-success">
            <i class="fas fa-check-circle"></i> <?= $upload_message ?>
        </div>
    <?php endif; ?>

    <?php if ($upload_error): ?>
        <div class="message-error">
            <i class="fas fa-exclamation-circle"></i> <?= $upload_error ?>
        </div>
    <?php endif; ?>

    <!-- Bulk Upload Section -->
    <div class="section" id="bulk-upload-section">
        <div class="section-header">
            <h2><i class="fas fa-cloud-upload-alt"></i> Data Import - Bulk Employee Upload</h2>
            <button class="btn btn-outline btn-sm" onclick="downloadTemplate()">
                <i class="fas fa-download"></i> Download Template
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
            <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Drag & Drop CSV File or Click to Browse</h3>
                <p>Supported format: .csv (max 5MB)</p>
                <input type="file" id="fileInput" name="bulk_employee_file" accept=".csv" style="display: none;" onchange="updateFileInfo(this)">
                <div id="fileInfo" style="margin-top: 15px; color: var(--primary);"></div>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 15px;" id="submitBtn">
                <i class="fas fa-upload"></i> Upload and Process Data
            </button>

            <div class="spinner" id="loadingSpinner" style="display: none;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Processing upload... This may take a moment.</p>
            </div>
        </form>
    </div>

    <!-- Upload History Section -->
    <div class="section" id="upload-history">
        <div class="section-header">
            <h2><i class="fas fa-history"></i> Data Import History</h2>
            <form method="POST" onsubmit="return confirmClearHistory()" style="display: inline;">
                <button type="submit" name="clear_history" class="btn btn-outline btn-sm">
                    <i class="fas fa-trash"></i> Clear History
                </button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Filename</th>
                        <th>Upload Date</th>
                        <th>Uploaded By</th>
                        <th>Records</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($_SESSION['upload_history'])): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #cbd5e1;"></i>
                            <p style="margin-top: 10px;">No upload history yet</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($_SESSION['upload_history'] as $upload): ?>
                        <tr>
                            <td>#<?= $upload['id'] ?></td>
                            <td><i class="fas fa-file-csv" style="color: #22c55e;"></i> <?= htmlspecialchars($upload['filename']) ?></td>
                            <td><?= date('d M Y H:i', strtotime($upload['upload_date'])) ?></td>
                            <td><?= htmlspecialchars($upload['uploaded_by']) ?></td>
                            <td><strong><?= $upload['records_processed'] ?></strong> / <?= $upload['errors'] ?> errors</td>
                            <td>
                                <span class="badge <?= $upload['status'] == 'Success' ? 'success' : 'warning' ?>">
                                    <?= $upload['status'] ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn view" onclick="viewUploadDetails(<?= htmlspecialchars(json_encode($upload)) ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Uploaded Files Review -->
    <div class="section" id="uploaded-files-section">
        <div class="section-header">
            <h2><i class="fas fa-file-invoice"></i> Review Uploaded Data</h2>
            <div class="btn-group">
                <select class="filter-select" id="fileSelect" onchange="loadSelectedFile()">
                    <option value="">Select a file to review</option>
                    <?php foreach($_SESSION['upload_history'] as $upload): ?>
                        <option value="<?= $upload['id'] ?>">
                            #<?= $upload['id'] ?> - <?= htmlspecialchars($upload['filename']) ?> (<?= date('d M', strtotime($upload['upload_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm" onclick="enableManualEntryMode()">
                    <i class="fas fa-pen"></i> Manual Entry Mode
                </button>
            </div>
        </div>

        <div id="employeeDetailsTable" style="display: none;">
            <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                <span class="badge primary">Manual Salary Entry Mode: Click on any salary amount to edit</span>
                <button class="btn btn-success btn-sm" onclick="saveAllManualSalaries()">
                    <i class="fas fa-save"></i> Save All
                </button>
                <button class="btn btn-warning btn-sm" onclick="revertAllManualSalaries()">
                    <i class="fas fa-undo"></i> Revert All
                </button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px"><input type="checkbox" id="tableSelectAll" onchange="toggleTableSelectAll()" checked></th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Base Salary</th>
                            <th>KPI Score</th>
                            <th>Rating</th>
                            <th>Manual Salary</th>
                            <th>Est. Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeeDetailsBody"></tbody>
                </table>
            </div>

            <!-- Salary Summary -->
            <div class="salary-summary">
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px;">
                    <div class="summary-item">
                        <div class="label">Selected Employees</div>
                        <div class="value" id="selectedCount">0</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Total Manual Salary</div>
                        <div class="value" id="totalManualSalary">₹0</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Total Calculated</div>
                        <div class="value" id="totalCalculated">₹0</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Difference</div>
                        <div class="value" id="salaryDifference">₹0</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Manual Entries</div>
                        <div class="value" id="manualCount">0</div>
                    </div>
                </div>
            </div>

            <div class="btn-group" style="justify-content: center; margin-top: 20px;">
                <button class="btn btn-success" onclick="processSelectedWithManualSalaries()">
                    <i class="fas fa-calculator"></i> Process Selected
                </button>
                <button class="btn btn-primary" onclick="showExportModal()">
                    <i class="fas fa-file-excel"></i> Export Selected
                </button>
                <button class="btn btn-info" onclick="applyManualToAll()">
                    <i class="fas fa-copy"></i> Apply to All
                </button>
            </div>
        </div>

        <div id="noFilesMessage" style="text-align: center; padding: 40px; <?= empty($_SESSION['upload_history']) ? '' : 'display: none;' ?>">
            <i class="fas fa-cloud-upload-alt" style="font-size: 64px; color: #cbd5e1;"></i>
            <h3 style="margin-top: 15px;">No uploaded files yet</h3>
            <p style="color: #64748b;">Upload a CSV file to review and manually enter salaries</p>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© <?= date('Y') ?> MPSS Enterprises – CEO Strategic Dashboard v2.0</p>
        <p style="font-size: 12px; margin-top: 5px;">Last updated: <?= date('d M Y H:i') ?></p>
    </div>
</div>

<!-- ========== MODALS ========== -->

<!-- Employee Details Modal -->
<div class="modal" id="employeeDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-tie"></i> Employee Details</h2>
            <span class="close-modal" onclick="closeModal('employeeDetailsModal')">&times;</span>
        </div>
        <div id="employeeModalContent"></div>
    </div>
</div>

<!-- Salary Details Modal -->
<div class="modal" id="salaryDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-calculator"></i> Compensation Details</h2>
            <span class="close-modal" onclick="closeModal('salaryDetailsModal')">&times;</span>
        </div>
        <div id="salaryModalContent"></div>
    </div>
</div>

<!-- Upload Details Modal -->
<div class="modal" id="uploadDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-csv"></i> Upload Details</h2>
            <span class="close-modal" onclick="closeModal('uploadDetailsModal')">&times;</span>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-envelope"></i> Send Email</h2>
            <span class="close-modal" onclick="closeModal('emailModal')">&times;</span>
        </div>
        <div id="emailModalContent">
            <div class="modal-form-group">
                <label>To:</label>
                <input type="email" id="emailTo" value="" readonly>
            </div>
            <div class="modal-form-group">
                <label>Subject:</label>
                <input type="text" id="emailSubject" value="Salary Information - ">
            </div>
            <div class="modal-form-group">
                <label>Message:</label>
                <textarea id="emailMessage" rows="5">Dear Employee,

Please find attached your salary information for the current month.

Best regards,
CEO Office</textarea>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal('emailModal')">Cancel</button>
                <button class="btn btn-primary" onclick="sendEmail()">Send Email</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal" id="editEmployeeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Employee</h2>
            <span class="close-modal" onclick="closeModal('editEmployeeModal')">&times;</span>
        </div>
        <div id="editEmployeeModalContent">
            <form id="editEmployeeForm">
                <div class="modal-form-group">
                    <label>Name:</label>
                    <input type="text" id="editName" required>
                </div>
                <div class="modal-form-group">
                    <label>Department:</label>
                    <select id="editDepartment">
                        <option value="IT">IT</option>
                        <option value="HR">HR</option>
                        <option value="Sales">Sales</option>
                        <option value="Finance">Finance</option>
                        <option value="Marketing">Marketing</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Position:</label>
                    <input type="text" id="editPosition" required>
                </div>
                <div class="modal-form-group">
                    <label>Base Salary (₹):</label>
                    <input type="number" id="editSalary" required>
                </div>
                <div class="modal-form-group">
                    <label>KPI Score (%):</label>
                    <input type="number" id="editKpi" min="0" max="100" required>
                </div>
                <div class="modal-form-group">
                    <label>Email:</label>
                    <input type="email" id="editEmail" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editEmployeeModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KPI Details Modal -->
<div class="modal" id="kpiDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-chart-line"></i> KPI Details</h2>
            <span class="close-modal" onclick="closeModal('kpiDetailsModal')">&times;</span>
        </div>
        <div id="kpiModalContent"></div>
    </div>
</div>

<!-- Insight Details Modal -->
<div class="modal" id="insightDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-lightbulb"></i> Strategic Insight</h2>
            <span class="close-modal" onclick="closeModal('insightDetailsModal')">&times;</span>
        </div>
        <div id="insightModalContent"></div>
    </div>
</div>

<!-- Department Details Modal -->
<div class="modal" id="departmentDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-building"></i> Department Details</h2>
            <span class="close-modal" onclick="closeModal('departmentDetailsModal')">&times;</span>
        </div>
        <div id="departmentModalContent"></div>
    </div>
</div>

<!-- Financial Details Modal -->
<div class="modal" id="financialDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-coins"></i> Financial Details</h2>
            <span class="close-modal" onclick="closeModal('financialDetailsModal')">&times;</span>
        </div>
        <div id="financialModalContent"></div>
    </div>
</div>

<!-- Payroll Processing Modal -->
<div class="modal" id="payrollModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-play"></i> Process Payroll</h2>
            <span class="close-modal" onclick="closeModal('payrollModal')">&times;</span>
        </div>
        <div id="payrollModalContent">
            <div class="confirmation-dialog">
                <div class="confirmation-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="confirmation-message">Process payroll for <?= $currentMonth ?>?</div>
                <div class="confirmation-details">
                    <p><strong>Total Amount:</strong> ₹<?= number_format($grandTotal) ?></p>
                    <p><strong>Employees:</strong> <?= $totalEmployees ?></p>
                    <p><strong>Processing Date:</strong> <?= date('d M Y') ?></p>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="closeModal('payrollModal')">Cancel</button>
                    <button class="btn btn-success" onclick="processPayrollConfirm()">Confirm Payroll</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Forecast Modal -->
<div class="modal" id="forecastModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-chart-line"></i> Financial Forecast</h2>
            <span class="close-modal" onclick="closeModal('forecastModal')">&times;</span>
        </div>
        <div id="forecastModalContent">
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 20px;">Next Quarter Projections</h3>
                <div class="financial-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="financial-card">
                        <div class="label">Projected Revenue</div>
                        <div class="value">₹45,00,000</div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Projected Costs</div>
                        <div class="value">₹28,00,000</div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Net Profit</div>
                        <div class="value">₹17,00,000</div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Profit Margin</div>
                        <div class="value">37.8%</div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 78%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                    <p style="text-align: center; margin-top: 10px; font-size: 12px;">78% of revenue target achieved</p>
                </div>
                <div class="modal-actions" style="margin-top: 20px;">
                    <button class="btn btn-primary" onclick="closeModal('forecastModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Talent Analytics Modal -->
<div class="modal" id="talentAnalyticsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-chart-pie"></i> Talent Analytics</h2>
            <span class="close-modal" onclick="closeModal('talentAnalyticsModal')">&times;</span>
        </div>
        <div id="talentAnalyticsContent">
            <div style="padding: 20px;">
                <div class="financial-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="financial-card">
                        <div class="label">Top Performers</div>
                        <div class="value"><?= $topPerformerCount ?></div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Retention Rate</div>
                        <div class="value">94%</div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Avg Tenure</div>
                        <div class="value">1.8 yrs</div>
                    </div>
                    <div class="financial-card">
                        <div class="label">Open Positions</div>
                        <div class="value">3</div>
                    </div>
                </div>
                <h4 style="margin: 20px 0 10px;">Department Distribution</h4>
                <?php foreach ($departmentStats as $dept => $stats): ?>
                <div style="margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><?= $dept ?></span>
                        <span><?= $stats['count'] ?> employees</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= ($stats['count'] / $totalEmployees) * 100 ?>%; background: <?= match($dept) {
                            'IT' => '#3b82f6',
                            'HR' => '#8b5cf6',
                            'Sales' => '#22c55e',
                            'Finance' => '#f97316',
                            'Marketing' => '#ec4899',
                            default => '#64748b'
                        } ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="modal-actions" style="margin-top: 20px;">
                    <button class="btn btn-primary" onclick="closeModal('talentAnalyticsModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal" id="exportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-excel"></i> Export Options</h2>
            <span class="close-modal" onclick="closeModal('exportModal')">&times;</span>
        </div>
        <div id="exportModalContent">
            <div style="padding: 20px;">
                <div class="modal-form-group">
                    <label>Export Format:</label>
                    <select id="exportFormat">
                        <option value="csv">CSV</option>
                        <option value="excel">Excel (XLSX)</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Include Fields:</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <label><input type="checkbox" checked> Name</label>
                        <label><input type="checkbox" checked> Department</label>
                        <label><input type="checkbox" checked> Position</label>
                        <label><input type="checkbox" checked> Salary</label>
                        <label><input type="checkbox" checked> KPI Score</label>
                        <label><input type="checkbox" checked> Rating</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="closeModal('exportModal')">Cancel</button>
                    <button class="btn btn-success" onclick="confirmExport()">Export</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Salary Modal -->
<div class="modal" id="manualSalaryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-pen"></i> Manual Salary Entry</h2>
            <span class="close-modal" onclick="closeModal('manualSalaryModal')">&times;</span>
        </div>
        <div id="manualSalaryModalContent">
            <div style="padding: 20px;">
                <div class="modal-form-group">
                    <label>Employee:</label>
                    <input type="text" id="manualEmpName" readonly>
                </div>
                <div class="modal-form-group">
                    <label>Calculated Salary (₹):</label>
                    <input type="text" id="manualCalculatedSalary" readonly>
                </div>
                <div class="modal-form-group">
                    <label>Manual Salary Amount (₹):</label>
                    <input type="number" id="manualSalaryAmount" class="salary-input" min="0" step="1000" value="0">
                </div>
                <div class="modal-form-group">
                    <label>Reason for Manual Entry:</label>
                    <select id="manualReason">
                        <option value="performance">Performance Adjustment</option>
                        <option value="promotion">Promotion</option>
                        <option value="special">Special Bonus</option>
                        <option value="correction">Salary Correction</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Notes:</label>
                    <textarea id="manualNotes" rows="3" placeholder="Add any additional notes..."></textarea>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="closeModal('manualSalaryModal')">Cancel</button>
                    <button class="btn btn-success" onclick="saveManualSalary()">Save Manual Salary</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Manual Modal -->
<div class="modal" id="bulkManualModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-pen"></i> Bulk Manual Entry</h2>
            <span class="close-modal" onclick="closeModal('bulkManualModal')">&times;</span>
        </div>
        <div id="bulkManualModalContent">
            <div style="padding: 20px;">
                <p>Apply manual salary to all selected employees</p>
                <div class="modal-form-group">
                    <label>Adjustment Type:</label>
                    <select id="bulkAdjustmentType">
                        <option value="fixed">Fixed Amount</option>
                        <option value="percentage">Percentage of Calculated</option>
                        <option value="increase">Increase by Amount</option>
                        <option value="decrease">Decrease by Amount</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Value:</label>
                    <input type="number" id="bulkAdjustmentValue" class="salary-input" min="0" step="1000">
                </div>
                <div class="modal-form-group">
                    <label>Reason:</label>
                    <select id="bulkReason">
                        <option value="performance">Performance Adjustment</option>
                        <option value="promotion">Promotion</option>
                        <option value="special">Special Bonus</option>
                        <option value="correction">Salary Correction</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="closeModal('bulkManualModal')">Cancel</button>
                    <button class="btn btn-success" onclick="applyBulkManual()">Apply to Selected</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal" id="confirmationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Confirmation</h2>
            <span class="close-modal" onclick="closeModal('confirmationModal')">&times;</span>
        </div>
        <div id="confirmationModalContent"></div>
    </div>
</div>

<script>
// Store data
const uploadHistory = <?= json_encode($_SESSION['upload_history']) ?>;
const employees = <?= json_encode($employees) ?>;
let currentEmployeeId = null;
let currentEmailEmployee = null;
let manualSalaryData = {};
let currentManualEmployee = null;

// Toggle sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('hide');
    document.getElementById('main').classList.toggle('expand');
}

// Scroll functions
function scrollToTop() { 
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
}

function scrollToFinancial() { 
    document.getElementById('financial-section').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToTalent() { 
    document.getElementById('talent-section').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToOperations() { 
    document.getElementById('financial-section').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToEmployees() { 
    document.getElementById('talent-section').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToBulkUpload() { 
    document.getElementById('bulk-upload-section').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToUploadHistory() { 
    document.getElementById('upload-history').scrollIntoView({ behavior: 'smooth' }); 
}

function scrollToUploadedFiles() { 
    document.getElementById('uploaded-files-section').scrollIntoView({ behavior: 'smooth' }); 
}

// Modal functions
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Department Performance Chart
    const deptCtx = document.getElementById('deptChart')?.getContext('2d');
    if (deptCtx) {
        const deptData = <?= json_encode(array_map(function($dept, $stats) {
            return ['dept' => $dept, 'avgKpi' => $stats['avgKpi']];
        }, array_keys($departmentStats), $departmentStats)) ?>;
        
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: deptData.map(d => d.dept),
                datasets: [{
                    label: 'Average KPI Score (%)',
                    data: deptData.map(d => d.avgKpi),
                    backgroundColor: [
                        '#3b82f6', '#8b5cf6', '#22c55e', '#f97316', '#ec4899'
                    ],
                    borderRadius: 8,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const dept = context.label;
                                const stats = <?= json_encode($departmentStats) ?>;
                                return `Employees: ${stats[dept].count} | Payroll: ₹${Math.round(stats[dept].totalSalary/1000)}K`;
                            }
                        }
                    }
                },
                onClick: function(event, items) {
                    if (items.length > 0) {
                        const index = items[0].dataIndex;
                        const dept = deptData[index].dept;
                        showDepartmentDetails(dept);
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: '#e5e7eb' }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    // Rating Distribution Chart
    const ratingCtx = document.getElementById('ratingChart')?.getContext('2d');
    if (ratingCtx) {
        new Chart(ratingCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Average', 'Poor'],
                datasets: [{
                    data: [
                        <?= $ratingDistribution['Excellent'] ?>,
                        <?= $ratingDistribution['Good'] ?>,
                        <?= $ratingDistribution['Average'] ?>,
                        <?= $ratingDistribution['Poor'] ?>
                    ],
                    backgroundColor: ['#22c55e', '#2563eb', '#facc15', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const total = <?= $totalEmployees ?>;
                                const value = context.raw;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${percentage}% of workforce`;
                            }
                        }
                    }
                },
                onClick: function(event, items) {
                    if (items.length > 0) {
                        const index = items[0].dataIndex;
                        const ratings = ['Excellent', 'Good', 'Average', 'Poor'];
                        showRatingDetails(ratings[index]);
                    }
                },
                cutout: '70%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 2000
                }
            }
        });
    }
});

// Employee functions
function viewEmployeeDetails(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    const rating = getRating(emp.kpi_score);
    const salaryDetails = calculateTotalSalaryDetails(emp);
    
    const modal = document.getElementById('employeeDetailsModal');
    const content = document.getElementById('employeeModalContent');
    
    content.innerHTML = `
        <div style="background: linear-gradient(135deg, #1e3a8a, #8b5cf6); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
            <h3 style="font-size: 24px; margin-bottom: 5px;">${emp.name}</h3>
            <p>${emp.position} • ${emp.department}</p>
            <p style="margin-top: 10px; opacity: 0.9;"><i class="fas fa-envelope"></i> ${emp.email || 'N/A'} • <i class="fas fa-phone"></i> ${emp.phone || 'N/A'}</p>
        </div>
        
        <div class="financial-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
            <div class="financial-card">
                <div class="label">KPI Score</div>
                <div class="value">${emp.kpi_score}%</div>
                <div class="badge ${rating.toLowerCase()}">${rating}</div>
            </div>
            <div class="financial-card">
                <div class="label">Attendance</div>
                <div class="value">${emp.attendance}%</div>
            </div>
            <div class="financial-card">
                <div class="label">Tasks</div>
                <div class="value">${emp.tasks_completed}/${emp.total_tasks}</div>
            </div>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <h4 style="margin-bottom: 15px;">Compensation Breakdown</h4>
            <table style="width: 100%;">
                <tr><td>Base Salary:</td><td style="text-align: right; font-weight: 600;">₹${Number(emp.base_salary).toLocaleString()}</td></tr>
                <tr><td>Increment (${salaryDetails.increment*100}%):</td><td style="text-align: right;">+ ₹${Math.round(salaryDetails.increment_amount).toLocaleString()}</td></tr>
                <tr><td>Performance Bonus:</td><td style="text-align: right;">+ ₹${Math.round(salaryDetails.performanceBonus).toLocaleString()}</td></tr>
                <tr><td>Weekend Bonus (${emp.weekend_hours} hrs):</td><td style="text-align: right;">+ ₹${Math.round(salaryDetails.weekendBonus).toLocaleString()}</td></tr>
                <tr style="border-top: 2px solid #e5e7eb;"><td style="font-weight: 700;">Total Compensation:</td><td style="text-align: right; font-weight: 700; color: #1e3a8a;">₹${Math.round(salaryDetails.total).toLocaleString()}</td></tr>
            </table>
        </div>
        
        <div class="modal-actions" style="margin-top: 20px;">
            <button class="btn btn-outline" onclick="closeModal('employeeDetailsModal')">Close</button>
            <button class="btn btn-primary" onclick="showEditEmployeeModal(${emp.id})">Edit Employee</button>
            <button class="btn btn-success" onclick="showEmailModal(${emp.id})">Send Email</button>
        </div>
    `;
    
    showModal('employeeDetailsModal');
}

function calculateEmployeeSalary(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    const salaryDetails = calculateTotalSalaryDetails(emp);
    
    const modal = document.getElementById('salaryDetailsModal');
    const content = document.getElementById('salaryModalContent');
    
    content.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <h3>${emp.name}</h3>
            <p style="color: #64748b;">${emp.position} • ${emp.department}</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #1e3a8a, #8b5cf6); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
            <div style="font-size: 14px; opacity: 0.9;">Total Monthly Compensation</div>
            <div style="font-size: 42px; font-weight: 700;">₹${Math.round(salaryDetails.total).toLocaleString()}</div>
        </div>
        
        <div style="display: grid; gap: 10px;">
            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <span>Base Salary:</span>
                <span style="font-weight: 600;">₹${Number(emp.base_salary).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <span>Performance Rating:</span>
                <span class="badge ${getRating(emp.kpi_score).toLowerCase()}">${getRating(emp.kpi_score)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <span>Increment (${salaryDetails.increment*100}%):</span>
                <span style="color: #22c55e;">+ ₹${Math.round(salaryDetails.increment_amount).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <span>Performance Bonus:</span>
                <span style="color: #22c55e;">+ ₹${Math.round(salaryDetails.performanceBonus).toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <span>Weekend Bonus:</span>
                <span style="color: #22c55e;">+ ₹${Math.round(salaryDetails.weekendBonus).toLocaleString()}</span>
            </div>
        </div>
        
        <div class="modal-actions" style="margin-top: 20px;">
            <button class="btn btn-outline" onclick="closeModal('salaryDetailsModal')">Close</button>
            <button class="btn btn-success" onclick="generatePayslip(${emp.id})">Generate Payslip</button>
            <button class="btn btn-warning" onclick="showManualSalaryModal(${emp.id})">Manual Entry</button>
        </div>
    `;
    
    showModal('salaryDetailsModal');
}

// Show email modal
function showEmailModal(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    currentEmailEmployee = emp;
    document.getElementById('emailTo').value = emp.email || 'No email provided';
    document.getElementById('emailSubject').value = `Salary Information - ${emp.name}`;
    document.getElementById('emailMessage').value = `Dear ${emp.name},\n\nPlease find attached your salary information for the current month.\n\nBest regards,\nCEO Office`;
    
    showModal('emailModal');
}

// Send email
function sendEmail() {
    const to = document.getElementById('emailTo').value;
    const subject = document.getElementById('emailSubject').value;
    const message = document.getElementById('emailMessage').value;
    
    alert(`✅ Email sent successfully to ${to}\n\nSubject: ${subject}\n\nIn production, this would send a real email.`);
    closeModal('emailModal');
}

// Show edit employee modal
function showEditEmployeeModal(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    currentEmployeeId = empId;
    document.getElementById('editName').value = emp.name;
    document.getElementById('editDepartment').value = emp.department;
    document.getElementById('editPosition').value = emp.position;
    document.getElementById('editSalary').value = emp.base_salary;
    document.getElementById('editKpi').value = emp.kpi_score;
    document.getElementById('editEmail').value = emp.email || '';
    
    showModal('editEmployeeModal');
}

// Handle edit form submit
document.getElementById('editEmployeeForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const updatedEmp = {
        id: currentEmployeeId,
        name: document.getElementById('editName').value,
        department: document.getElementById('editDepartment').value,
        position: document.getElementById('editPosition').value,
        base_salary: parseFloat(document.getElementById('editSalary').value),
        kpi_score: parseFloat(document.getElementById('editKpi').value),
        email: document.getElementById('editEmail').value
    };
    
    alert(`✅ Employee ${updatedEmp.name} updated successfully!\n\nChanges saved.`);
    closeModal('editEmployeeModal');
});

// View employee history
function viewEmployeeHistory(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    showConfirmationModal(
        'Employee History',
        `
        <div style="padding: 20px;">
            <h4>${emp.name} - Performance History</h4>
            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px;">
                    <span>March 2026:</span>
                    <span>KPI: ${emp.kpi_score}% | Rating: ${getRating(emp.kpi_score)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px;">
                    <span>February 2026:</span>
                    <span>KPI: ${emp.kpi_score - 2}% | Rating: ${getRating(emp.kpi_score - 2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px;">
                    <span>January 2026:</span>
                    <span>KPI: ${emp.kpi_score - 5}% | Rating: ${getRating(emp.kpi_score - 5)}</span>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <p><strong>Salary History:</strong></p>
                <p>Current: ₹${Number(emp.base_salary).toLocaleString()}</p>
                <p>Previous: ₹${Number(emp.base_salary - 5000).toLocaleString()}</p>
            </div>
        </div>
        `
    );
}

// Generate payslip
function generatePayslip(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    alert(`📄 Generating payslip for ${emp.name}...\n\nPayslip would be downloaded as PDF.`);
    closeModal('salaryDetailsModal');
}

// KPI details
function showKpiDetails(type) {
    let title, content;
    
    switch(type) {
        case 'revenue':
            title = 'Revenue per Employee';
            content = `
                <div style="padding: 20px;">
                    <h3>Revenue per Employee: ₹<?= number_format(round($avgSalaryPerEmployee * 2.5)) ?></h3>
                    <p style="color: #64748b; margin: 15px 0;">Industry benchmark: ₹1,25,000</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 90%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                    <p style="margin-top: 10px;">12.3% above industry average</p>
                </div>
            `;
            break;
        case 'productivity':
            title = 'Productivity Index';
            content = `
                <div style="padding: 20px;">
                    <h3>Productivity Index: <?= number_format($productivityScore, 1) ?>%</h3>
                    <p style="color: #64748b; margin: 15px 0;">Target: 85%</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= $productivityScore ?>%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                    <p style="margin-top: 10px;">Tasks Completed: <?= $totalTasksCompleted ?> / <?= $totalTasks ?></p>
                </div>
            `;
            break;
        case 'satisfaction':
            title = 'Employee Satisfaction';
            content = `
                <div style="padding: 20px;">
                    <h3>Employee Satisfaction: <?= number_format($avgAttendance, 1) ?>%</h3>
                    <p style="color: #64748b; margin: 15px 0;">Based on attendance and retention metrics</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= $avgAttendance ?>%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                </div>
            `;
            break;
        case 'retention':
            title = 'Talent Retention';
            content = `
                <div style="padding: 20px;">
                    <h3>Talent Retention: 94%</h3>
                    <p style="color: #64748b; margin: 15px 0;">Industry average: 89%</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 94%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                    <p style="margin-top: 10px;">Above industry average by 5%</p>
                </div>
            `;
            break;
    }
    
    document.getElementById('kpiModalContent').innerHTML = content;
    showModal('kpiDetailsModal');
}

// Insight details
function showInsightDetails(type) {
    let title, content;
    
    switch(type) {
        case 'topPerformers':
            title = 'Top Performers Analysis';
            content = `
                <div style="padding: 20px;">
                    <h3>Top Performers: <?= $topPerformerCount ?> employees</h3>
                    <p style="color: #64748b; margin: 15px 0;"><?= number_format($topPerformerPercentage, 1) ?>% of workforce</p>
                    <div style="margin-top: 20px;">
                        <h4>List of Top Performers:</h4>
                        <?php foreach ($employees as $emp): 
                            if (getRating($emp['kpi_score']) == 'Excellent' || getRating($emp['kpi_score']) == 'Good'): ?>
                            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                                <span><strong><?= $emp['name'] ?></strong> - <?= $emp['department'] ?></span>
                                <span class="badge <?= strtolower(getRating($emp['kpi_score'])) ?>"><?= getRating($emp['kpi_score']) ?></span>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            `;
            break;
        case 'development':
            title = 'Development Needed';
            content = `
                <div style="padding: 20px;">
                    <h3>Employees Needing Development: <?= $ratingDistribution['Average'] + $ratingDistribution['Poor'] ?></h3>
                    <p style="color: #64748b; margin: 15px 0;">Performance improvement required</p>
                    <div style="margin-top: 20px;">
                        <h4>List:</h4>
                        <?php foreach ($employees as $emp): 
                            if (getRating($emp['kpi_score']) == 'Average' || getRating($emp['kpi_score']) == 'Poor'): ?>
                            <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                                <span><strong><?= $emp['name'] ?></strong> - <?= $emp['department'] ?></span>
                                <span class="badge <?= strtolower(getRating($emp['kpi_score'])) ?>"><?= getRating($emp['kpi_score']) ?></span>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            `;
            break;
        case 'weekend':
            title = 'Weekend Work Analysis';
            content = `
                <div style="padding: 20px;">
                    <h3>Total Weekend Hours: <?= $totalWeekendHours ?> hrs</h3>
                    <p style="color: #64748b; margin: 15px 0;">Bonus Payout: ₹<?= number_format($totalWeekendHours * 500) ?></p>
                    <div style="margin-top: 20px;">
                        <h4>Breakdown by Employee:</h4>
                        <?php foreach ($employees as $emp): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span><strong><?= $emp['name'] ?></strong> - <?= $emp['department'] ?></span>
                            <span><?= $emp['weekend_hours'] ?> hrs (₹<?= number_format($emp['weekend_hours'] * 500) ?>)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            `;
            break;
    }
    
    document.getElementById('insightModalContent').innerHTML = content;
    showModal('insightDetailsModal');
}

// Department details
function showDepartmentDetails(dept) {
    const deptEmployees = employees.filter(e => e.department === dept);
    const deptStats = <?= json_encode($departmentStats) ?>[dept];
    
    let employeesList = '';
    deptEmployees.forEach(emp => {
        employeesList += `
            <tr>
                <td>${emp.name}</td>
                <td>${emp.position}</td>
                <td>${emp.kpi_score}%</td>
                <td><span class="badge ${getRating(emp.kpi_score).toLowerCase()}">${getRating(emp.kpi_score)}</span></td>
                <td>₹${Number(emp.base_salary).toLocaleString()}</td>
                <td>
                    <button class="action-btn view" onclick="viewEmployeeDetails(${emp.id})"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
        `;
    });
    
    const content = `
        <div style="padding: 20px;">
            <h3>${dept} Department</h3>
            <div class="financial-grid" style="grid-template-columns: repeat(3, 1fr); margin: 20px 0;">
                <div class="financial-card">
                    <div class="label">Employees</div>
                    <div class="value">${deptStats.count}</div>
                </div>
                <div class="financial-card">
                    <div class="label">Avg KPI</div>
                    <div class="value">${deptStats.avgKpi.toFixed(1)}%</div>
                </div>
                <div class="financial-card">
                    <div class="label">Total Payroll</div>
                    <div class="value">₹${Math.round(deptStats.totalSalary/1000)}K</div>
                </div>
            </div>
            
            <h4 style="margin: 20px 0 10px;">Employees</h4>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>KPI</th>
                            <th>Rating</th>
                            <th>Salary</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${employeesList}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('departmentModalContent').innerHTML = content;
    showModal('departmentDetailsModal');
}

// Rating details
function showRatingDetails(rating) {
    const ratingEmployees = employees.filter(e => getRating(e.kpi_score) === rating);
    
    let employeesList = '';
    ratingEmployees.forEach(emp => {
        employeesList += `
            <tr>
                <td>${emp.name}</td>
                <td>${emp.department}</td>
                <td>${emp.position}</td>
                <td>${emp.kpi_score}%</td>
                <td>₹${Number(emp.base_salary).toLocaleString()}</td>
                <td>
                    <button class="action-btn view" onclick="viewEmployeeDetails(${emp.id})"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
        `;
    });
    
    const content = `
        <div style="padding: 20px;">
            <h3>${rating} Performers</h3>
            <p style="color: #64748b; margin: 10px 0;">${ratingEmployees.length} employees (${((ratingEmployees.length / <?= $totalEmployees ?>) * 100).toFixed(1)}% of workforce)</p>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>KPI</th>
                            <th>Salary</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${employeesList || '<tr><td colspan="6" style="text-align: center;">No employees in this category</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('insightModalContent').innerHTML = content;
    showModal('insightDetailsModal');
}

// Financial details
function showFinancialDetail(type) {
    let title, content;
    
    switch(type) {
        case 'payroll':
            title = 'Payroll Details';
            content = `
                <div style="padding: 20px;">
                    <h3>Total Payroll: ₹<?= number_format(round($totalPayrollCost)) ?></h3>
                    <div style="margin-top: 20px;">
                        <h4>Monthly Breakdown:</h4>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span>Base Salaries:</span>
                            <span>₹<?= number_format(round($totalBaseSalary)) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span>Performance Bonuses:</span>
                            <span>₹<?= number_format(round(array_sum(array_map(function($emp) {
                                return calculatePerformanceBonus($emp['kpi_score'], $emp['base_salary']);
                            }, $employees)))) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span>Weekend Bonuses:</span>
                            <span>₹<?= number_format(round($totalWeekendHours * 500)) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #e0e7ff; border-radius: 8px; margin-top: 10px; font-weight: 700;">
                            <span>Total:</span>
                            <span>₹<?= number_format(round($totalPayrollCost)) ?></span>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'avgSalary':
            title = 'Average Salary Analysis';
            content = `
                <div style="padding: 20px;">
                    <h3>Average Salary: ₹<?= number_format(round($avgSalaryPerEmployee)) ?></h3>
                    <div style="margin-top: 20px;">
                        <h4>By Department:</h4>
                        <?php foreach ($departmentStats as $dept => $stats): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span><?= $dept ?>:</span>
                            <span>₹<?= number_format(round($stats['totalSalary'] / $stats['count'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            `;
            break;
        case 'bonus':
            title = 'Bonus Payout Analysis';
            content = `
                <div style="padding: 20px;">
                    <h3>Total Bonuses: ₹<?= number_format(round(array_sum(array_map(function($emp) {
                        return calculatePerformanceBonus($emp['kpi_score'], $emp['base_salary']) + calculateWeekendBonus($emp['weekend_hours']);
                    }, $employees)))) ?></h3>
                    <div style="margin-top: 20px;">
                        <h4>Breakdown:</h4>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span>Performance Bonuses:</span>
                            <span>₹<?= number_format(round(array_sum(array_map(function($emp) {
                                return calculatePerformanceBonus($emp['kpi_score'], $emp['base_salary']);
                            }, $employees)))) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 10px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                            <span>Weekend Bonuses:</span>
                            <span>₹<?= number_format(round($totalWeekendHours * 500)) ?></span>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'costPerHire':
            title = 'Cost per Hire';
            content = `
                <div style="padding: 20px;">
                    <h3>Cost per Hire: ₹15,000</h3>
                    <p style="color: #64748b; margin: 15px 0;">Industry average: ₹15,800</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 95%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div>
                    </div>
                    <p style="margin-top: 10px;">5% below industry average - Good efficiency!</p>
                </div>
            `;
            break;
    }
    
    document.getElementById('financialModalContent').innerHTML = content;
    showModal('financialDetailsModal');
}

// Show process payroll modal
function showProcessPayrollModal() {
    showModal('payrollModal');
}

// Process payroll confirmation
function processPayrollConfirm() {
    alert('✅ Payroll processed successfully for <?= $currentMonth ?>!\n\nTotal amount: ₹<?= number_format($grandTotal) ?>\nEmployees: <?= $totalEmployees ?>\nTransaction ID: PAY' + Math.floor(Math.random() * 1000000));
    closeModal('payrollModal');
}

// Show financial forecast modal
function showFinancialForecastModal() {
    showModal('forecastModal');
}

// Show talent analytics modal
function showTalentAnalyticsModal() {
    showModal('talentAnalyticsModal');
}

// Show export modal
function showExportModal() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one employee to export');
        return;
    }
    showModal('exportModal');
}

// Confirm export
function confirmExport() {
    const format = document.getElementById('exportFormat').value;
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    alert(`📊 Exporting ${selected.length} employees to ${format.toUpperCase()} format`);
    closeModal('exportModal');
}

// Show confirmation modal
function showConfirmationModal(title, content) {
    document.getElementById('confirmationModalContent').innerHTML = `
        <div style="padding: 20px;">
            <h3>${title}</h3>
            ${content}
            <div class="modal-actions" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="closeModal('confirmationModal')">Close</button>
            </div>
        </div>
    `;
    showModal('confirmationModal');
}

// ========== MANUAL SALARY FUNCTIONS ==========

// Enable manual entry mode
function enableManualEntryMode() {
    alert('Manual Entry Mode Activated!\n\nClick on any salary amount in the table to edit manually.');
}

// Show manual salary modal
function showManualSalaryModal(empId) {
    const emp = employees.find(e => e.id == empId);
    if (!emp) return;
    
    currentManualEmployee = empId;
    const salaryDetails = calculateTotalSalaryDetails(emp);
    
    document.getElementById('manualEmpName').value = emp.name;
    document.getElementById('manualCalculatedSalary').value = `₹${Math.round(salaryDetails.total).toLocaleString()}`;
    document.getElementById('manualSalaryAmount').value = manualSalaryData[empId]?.amount || Math.round(salaryDetails.total);
    document.getElementById('manualReason').value = manualSalaryData[empId]?.reason || 'performance';
    document.getElementById('manualNotes').value = manualSalaryData[empId]?.notes || '';
    
    showModal('manualSalaryModal');
}

// Save manual salary
function saveManualSalary() {
    const empId = currentManualEmployee;
    const amount = document.getElementById('manualSalaryAmount').value;
    const reason = document.getElementById('manualReason').value;
    const notes = document.getElementById('manualNotes').value;
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid salary amount');
        return;
    }
    
    manualSalaryData[empId] = {
        amount: parseFloat(amount),
        reason: reason,
        notes: notes,
        timestamp: new Date().toISOString()
    };
    
    // Update the table row
    updateTableRowWithManualSalary(empId, amount);
    
    // Update summary
    updateSalarySummary();
    
    alert(`✅ Manual salary saved for employee ID ${empId}\nAmount: ₹${parseFloat(amount).toLocaleString()}`);
    closeModal('manualSalaryModal');
}

// Update table row with manual salary
function updateTableRowWithManualSalary(empId, amount) {
    const rows = document.querySelectorAll('#employeeDetailsBody tr');
    rows.forEach(row => {
        const viewBtn = row.querySelector('.action-btn.view');
        if (viewBtn) {
            const onclickAttr = viewBtn.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(empId)) {
                const manualCell = row.cells[7];
                const estCell = row.cells[8];
                
                manualCell.innerHTML = `
                    <div class="salary-input-group">
                        <span class="salary-display">₹${parseFloat(amount).toLocaleString()}</span>
                        <span class="manual-badge">Manual</span>
                        <div class="salary-actions">
                            <button class="action-btn edit" onclick="showManualSalaryModal(${empId})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" onclick="clearManualSalary(${empId})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                estCell.innerHTML = `<strong>₹${parseFloat(amount).toLocaleString()}</strong>`;
            }
        }
    });
}

// Clear manual salary
function clearManualSalary(empId) {
    if (confirm('Clear manual salary entry and revert to calculated?')) {
        delete manualSalaryData[empId];
        
        const emp = employees.find(e => e.id == empId);
        if (emp) {
            const salaryDetails = calculateTotalSalaryDetails(emp);
            updateTableRowWithCalculatedSalary(empId, salaryDetails.total);
        }
        
        updateSalarySummary();
    }
}

// Update table row with calculated salary
function updateTableRowWithCalculatedSalary(empId, calculatedAmount) {
    const rows = document.querySelectorAll('#employeeDetailsBody tr');
    rows.forEach(row => {
        const viewBtn = row.querySelector('.action-btn.view');
        if (viewBtn) {
            const onclickAttr = viewBtn.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(empId)) {
                const manualCell = row.cells[7];
                const estCell = row.cells[8];
                
                manualCell.innerHTML = `
                    <div class="salary-input-group">
                        <span class="salary-display">₹${Math.round(calculatedAmount).toLocaleString()}</span>
                        <div class="salary-actions">
                            <button class="action-btn manual" onclick="showManualSalaryModal(${empId})">
                                <i class="fas fa-pen"></i> Manual
                            </button>
                        </div>
                    </div>
                `;
                
                estCell.innerHTML = `<strong>₹${Math.round(calculatedAmount).toLocaleString()}</strong>`;
            }
        }
    });
}

// Update salary summary
function updateSalarySummary() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    const selectedCount = selected.length;
    let totalManual = 0;
    let totalCalculated = 0;
    let manualCount = 0;
    
    selected.forEach(cb => {
        const row = cb.closest('tr');
        const manualCell = row.cells[7];
        const estCell = row.cells[8];
        
        if (manualCell.querySelector('.manual-badge')) {
            const manualAmount = parseFloat(manualCell.querySelector('.salary-display').innerText.replace('₹', '').replace(/,/g, ''));
            totalManual += manualAmount;
            manualCount++;
        } else {
            const estAmount = parseFloat(estCell.innerText.replace('₹', '').replace(/,/g, ''));
            totalCalculated += estAmount;
        }
    });
    
    const grandTotal = totalManual + totalCalculated;
    const difference = totalManual - totalCalculated;
    
    document.getElementById('selectedCount').innerText = selectedCount;
    document.getElementById('totalManualSalary').innerHTML = `₹${Math.round(totalManual).toLocaleString()}`;
    document.getElementById('totalCalculated').innerHTML = `₹${Math.round(totalCalculated).toLocaleString()}`;
    document.getElementById('salaryDifference').innerHTML = `₹${Math.round(difference).toLocaleString()}`;
    document.getElementById('manualCount').innerText = manualCount;
    
    const diffElement = document.getElementById('salaryDifference');
    if (difference > 0) {
        diffElement.style.color = '#22c55e';
    } else if (difference < 0) {
        diffElement.style.color = '#ef4444';
    } else {
        diffElement.style.color = '#64748b';
    }
}

// Save all manual salaries
function saveAllManualSalaries() {
    const count = Object.keys(manualSalaryData).length;
    if (count === 0) {
        alert('No manual salary entries to save');
        return;
    }
    
    alert(`✅ Saved ${count} manual salary entries\n\nIn production, these would be saved to the database.`);
}

// Revert all manual salaries
function revertAllManualSalaries() {
    if (confirm('Revert all manual salary entries back to calculated values?')) {
        const empIds = Object.keys(manualSalaryData);
        empIds.forEach(empId => {
            const emp = employees.find(e => e.id == empId);
            if (emp) {
                const salaryDetails = calculateTotalSalaryDetails(emp);
                updateTableRowWithCalculatedSalary(empId, salaryDetails.total);
            }
        });
        
        manualSalaryData = {};
        updateSalarySummary();
        alert(`✅ Reverted ${empIds.length} manual entries`);
    }
}

// Process selected with manual salaries
function processSelectedWithManualSalaries() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one employee');
        return;
    }
    
    const manualCount = countManualEntries();
    const total = calculateSelectedTotal();
    
    alert(`✅ Processing ${selected.length} selected employees\nTotal amount: ₹${total}\nManual entries: ${manualCount}\n\nIn production, salaries would be processed now.`);
}

// Apply manual to all
function applyManualToAll() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one employee');
        return;
    }
    
    showModal('bulkManualModal');
}

// Apply bulk manual
function applyBulkManual() {
    const adjustmentType = document.getElementById('bulkAdjustmentType').value;
    const value = parseFloat(document.getElementById('bulkAdjustmentValue').value);
    const reason = document.getElementById('bulkReason').value;
    
    if (!value || value <= 0) {
        alert('Please enter a valid value');
        return;
    }
    
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    
    selected.forEach(cb => {
        const row = cb.closest('tr');
        const viewBtn = row.querySelector('.action-btn.view');
        const onclickAttr = viewBtn.getAttribute('onclick');
        const empId = onclickAttr.match(/\d+/)[0];
        const emp = employees.find(e => e.id == empId);
        
        if (emp) {
            const salaryDetails = calculateTotalSalaryDetails(emp);
            let newAmount = salaryDetails.total;
            
            switch(adjustmentType) {
                case 'fixed':
                    newAmount = value;
                    break;
                case 'percentage':
                    newAmount = salaryDetails.total * (value / 100);
                    break;
                case 'increase':
                    newAmount = salaryDetails.total + value;
                    break;
                case 'decrease':
                    newAmount = Math.max(0, salaryDetails.total - value);
                    break;
            }
            
            manualSalaryData[empId] = {
                amount: newAmount,
                reason: reason,
                notes: `Bulk adjustment: ${adjustmentType} of ${value}`,
                timestamp: new Date().toISOString()
            };
            
            updateTableRowWithManualSalary(empId, newAmount);
        }
    });
    
    updateSalarySummary();
    alert(`✅ Applied bulk adjustment to ${selected.length} employees`);
    closeModal('bulkManualModal');
}

// Upload functions
function updateFileInfo(input) {
    const fileInfo = document.getElementById('fileInfo');
    if (input.files.length > 0) {
        const file = input.files[0];
        fileInfo.innerHTML = `<i class="fas fa-check-circle" style="color: #22c55e;"></i> Selected: ${file.name} (${(file.size/1024).toFixed(2)} KB)`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const form = document.getElementById('bulkUploadForm');
    const submitBtn = document.getElementById('submitBtn');
    const spinner = document.getElementById('loadingSpinner');
    
    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].name.endsWith('.csv')) {
                fileInput.files = files;
                updateFileInfo(fileInput);
            } else {
                alert('Please upload a CSV file only');
            }
        });
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select a file to upload');
            } else {
                submitBtn.style.display = 'none';
                spinner.style.display = 'block';
            }
        });
    }
});

function downloadTemplate() {
    const headers = ['Name', 'Department', 'Position', 'KPI Score', 'Base Salary', 'Tasks Completed', 'Total Tasks', 'Weekend Hours', 'Attendance %', 'Join Date', 'Email', 'Phone', 'Emergency Contact'];
    const sample = ['John Doe', 'IT', 'Senior Developer', '85', '45000', '45', '50', '12', '96', '2023-01-15', 'john@company.com', '9876543210', '9988776655'];
    
    let csvContent = headers.join(',') + '\n' + sample.join(',');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'employee_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function loadSelectedFile() {
    const fileId = document.getElementById('fileSelect').value;
    const tableContainer = document.getElementById('employeeDetailsTable');
    const noFilesMessage = document.getElementById('noFilesMessage');
    
    if (!fileId) {
        tableContainer.style.display = 'none';
        noFilesMessage.style.display = 'block';
        return;
    }
    
    const upload = uploadHistory.find(u => u.id == fileId);
    if (upload && upload.employees_added) {
        displayEmployeesWithManual(upload.employees_added);
        tableContainer.style.display = 'block';
        noFilesMessage.style.display = 'none';
    }
}

function displayEmployeesWithManual(employees) {
    const tbody = document.getElementById('employeeDetailsBody');
    let html = '';
    
    employees.forEach((emp, index) => {
        const rating = getRating(emp.kpi_score);
        const salaryDetails = calculateTotalSalaryDetails(emp);
        const hasManual = manualSalaryData[emp.id];
        const displayAmount = hasManual ? hasManual.amount : salaryDetails.total;
        
        html += `
            <tr>
                <td><input type="checkbox" class="employee-checkbox" data-index="${index}" checked onchange="updateSalarySummary()"></td>
                <td><strong>${emp.name}</strong></td>
                <td>${emp.department}</td>
                <td>${emp.position}</td>
                <td>₹${Number(emp.base_salary).toLocaleString()}</td>
                <td>${emp.kpi_score}%</td>
                <td><span class="badge ${rating.toLowerCase()}">${rating}</span></td>
                <td>
                    <div class="salary-input-group">
                        <span class="salary-display">₹${Math.round(displayAmount).toLocaleString()}</span>
                        ${hasManual ? '<span class="manual-badge">Manual</span>' : ''}
                        <div class="salary-actions">
                            <button class="action-btn manual" onclick="showManualSalaryModal(${emp.id})">
                                <i class="fas fa-pen"></i>
                            </button>
                            ${hasManual ? `<button class="action-btn delete" onclick="clearManualSalary(${emp.id})">
                                <i class="fas fa-times"></i>
                            </button>` : ''}
                        </div>
                    </div>
                </td>
                <td><strong>₹${Math.round(displayAmount).toLocaleString()}</strong></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn view" onclick="viewEmployeeDetails(${emp.id})"><i class="fas fa-eye"></i></button>
                        <button class="action-btn salary" onclick="calculateEmployeeSalary(${emp.id})"><i class="fas fa-calculator"></i></button>
                        <button class="action-btn email" onclick="showEmailModal(${emp.id})"><i class="fas fa-envelope"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    document.getElementById('tableSelectAll').checked = true;
    updateSalarySummary();
}

function viewUploadDetails(upload) {
    const modal = document.getElementById('uploadDetailsModal');
    const modalContent = document.getElementById('modalContent');
    
    let employeesHtml = '';
    if (upload.employees_added) {
        employeesHtml = `
            <h3 style="margin: 20px 0 10px;">Uploaded Employees (${upload.employees_added.length})</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Base Salary</th>
                            <th>KPI Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${upload.employees_added.map(emp => `
                            <tr>
                                <td>${emp.name}</td>
                                <td>${emp.department}</td>
                                <td>${emp.position}</td>
                                <td>₹${Number(emp.base_salary).toLocaleString()}</td>
                                <td>${emp.kpi_score}%</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    modalContent.innerHTML = `
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <p><strong>Filename:</strong> ${upload.filename}</p>
            <p><strong>Upload Date:</strong> ${upload.upload_date}</p>
            <p><strong>Uploaded By:</strong> ${upload.uploaded_by}</p>
            <p><strong>Records Processed:</strong> ${upload.records_processed}</p>
            <p><strong>Errors:</strong> ${upload.errors}</p>
            <p><strong>Status:</strong> <span class="badge ${upload.status == 'Success' ? 'success' : 'warning'}">${upload.status}</span></p>
        </div>
        ${employeesHtml}
        <div class="modal-actions" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="closeModal('uploadDetailsModal')">Close</button>
        </div>
    `;
    
    showModal('uploadDetailsModal');
}

function toggleTableSelectAll() {
    const selectAll = document.getElementById('tableSelectAll').checked;
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = selectAll);
    updateSalarySummary();
}

// CEO specific functions
function exportDepartmentReport() {
    alert('📑 Department performance report exported successfully');
}

function confirmClearHistory() {
    return confirm('⚠️ Are you sure you want to clear all upload history? This action cannot be undone.');
}

// Utility functions
function getRating(score) {
    if(score >= 90) return "Excellent";
    if(score >= 75) return "Good";
    if(score >= 60) return "Average";
    return "Poor";
}

function calculateTotalSalaryDetails(emp) {
    const baseSalary = emp.base_salary;
    const kpiScore = emp.kpi_score;
    const weekendHours = emp.weekend_hours || 0;
    
    let increment = 0;
    if(kpiScore >= 90) increment = 0.15;
    else if(kpiScore >= 75) increment = 0.10;
    else if(kpiScore >= 60) increment = 0.05;
    
    let performanceBonus = 0;
    if(kpiScore >= 90) performanceBonus = baseSalary * 0.20;
    else if(kpiScore >= 75) performanceBonus = baseSalary * 0.10;
    else if(kpiScore >= 60) performanceBonus = baseSalary * 0.05;
    
    const weekendBonus = weekendHours * 500;
    const salaryWithIncrement = baseSalary + (baseSalary * increment);
    const total = salaryWithIncrement + performanceBonus + weekendBonus;
    
    return {
        increment: increment,
        increment_amount: baseSalary * increment,
        performanceBonus: performanceBonus,
        weekendBonus: weekendBonus,
        total: total
    };
}

function countManualEntries() {
    let count = 0;
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    selected.forEach(cb => {
        const row = cb.closest('tr');
        if (row.cells[7].querySelector('.manual-badge')) {
            count++;
        }
    });
    return count;
}

function calculateSelectedTotal() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');
    let total = 0;
    
    selected.forEach(cb => {
        const row = cb.closest('tr');
        const manualCell = row.cells[7];
        if (manualCell.querySelector('.manual-badge')) {
            const amount = parseFloat(manualCell.querySelector('.salary-display').innerText.replace('₹', '').replace(/,/g, ''));
            total += amount;
        } else {
            const estCell = row.cells[8];
            const amount = parseFloat(estCell.innerText.replace('₹', '').replace(/,/g, ''));
            total += amount;
        }
    });
    
    return Math.round(total).toLocaleString();
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Auto-refresh every 10 minutes
setTimeout(() => location.reload(), 600000);
</script>

</body>
</html>