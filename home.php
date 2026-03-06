<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home | MPSS</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
}
.header {
    background: #0066cc;
    color: #fff;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header .logo {
    font-size: 20px;
    font-weight: bold;
}
.header .menu a {
    color: #fff;
    text-decoration: none;
    margin-left: 20px;
    font-size: 14px;
}
.header .menu a:hover { text-decoration: underline; }
.hero {
    background: #fff;
    padding: 60px 30px;
    text-align: center;
}
.hero h1 {
    font-size: 36px;
    color: #333;
    margin-bottom: 15px;
}
.hero p {
    font-size: 18px;
    color: #666;
    margin-bottom: 25px;
    line-height: 1.6;
}
.hero a {
    display: inline-block;
    padding: 12px 30px;
    background: #ff9900;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 16px;
}
.hero a:hover { background: #e68a00; }
.features {
    display: flex;
    justify-content: center;
    gap: 30px;
    padding: 50px 30px;
    flex-wrap: wrap;
}
.feature {
    background: #fff;
    padding: 30px;
    width: 280px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.feature h3 {
    color: #333;
    margin-bottom: 10px;
}
.feature p { color: #666; font-size: 14px; }
.footer {
    background: #333;
    color: #fff;
    text-align: center;
    padding: 20px;
    font-size: 14px;
}
</style>
</head>
<body>

<div class="header">
    <div class="logo">MPSS - Management Performance & Salary System</div>
    <div class="menu">
        <a href="home.php">Home</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>
</div>

<div class="hero">
    <h1>Management Performance & Salary System</h1>
    <p>Efficiently manage employee performance, track achievements,<br>
    and generate salary reports with ease.</p>
    <a href="login.php">Get Started</a>
</div>

<div class="features">
    <div class="feature">
        <h3>Employee Management</h3>
        <p>Manage employee records, profiles, and department information</p>
    </div>
    <div class="feature">
        <h3>Performance Tracking</h3>
        <p>Track KPI scores and performance metrics</p>
    </div>
    <div class="feature">
        <h3>Salary Processing</h3>
        <p>Calculate and generate salary reports automatically</p>
    </div>
    <div class="feature">
        <h3>Attendance</h3>
        <p>Monitor employee attendance and working hours</p>
    </div>
</div>

<div class="footer">
    &copy; 2026 MPSS. All rights reserved.
</div>

</body>
</html>

