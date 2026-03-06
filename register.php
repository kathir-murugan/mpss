<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | MPSS</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    background-color: #f0f0f0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}
.form-container {
    background: #fff;
    padding: 30px;
    width: 400px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
h2 { text-align: center; color: #333; margin-bottom: 20px; }
input {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
input:focus {
    outline: none;
    border-color: #0066cc;
}
button {
    width: 100%;
    padding: 12px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
}
button:hover { background: #218838; }
.login-link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
    color: #0066cc;
    cursor: pointer;
}
.login-link:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="form-container">
    <h2>Register</h2>
    <form action="register_process.php" method="POST">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register</button>
    </form>
    <div class="login-link" onclick="window.location.href='login.php'">Already have an account? Login</div>
</div>

</body>
</html>

