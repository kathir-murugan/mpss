<?php
session_start();

/* TEMP USERS (DB CAN BE ADDED LATER) */
$users = [
    "ceo" => [
        "password" => "ceo123",
        "role" => "CEO"
    ],
    "manager" => [
        "password" => "manager123",
        "role" => "Manager"
    ],
    "employee" => [
        "password" => "employee123",
        "role" => "Employee"
    ]
];

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (isset($users[$username]) && $users[$username]['password'] === $password) {

        $_SESSION['username'] = $username;
        $_SESSION['role'] = $users[$username]['role'];

        // ROLE BASED REDIRECT
        if ($_SESSION['role'] === "CEO") {
            header("Location:admin.php");
        } elseif ($_SESSION['role'] === "Manager") {
            header("Location:employee.php");
        } else {
            header("Location:user.php");
        }
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
body{
    background: linear-gradient(-45deg, #f5f6fa, #0a2f74, #28a745);
    background-size: 600% 600%;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    animation: gradientBG 15s ease infinite;
}
@keyframes gradientBG{
    0%{background-position:0% 50%}
    50%{background-position:100% 50%}
    100%{background-position:0% 50%}
}
.login-box{
    background:#fff;
    padding:30px;
    width:320px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}
h2{text-align:center;color:#1e3a8a}
input,select{
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
    color:#fff;
    border:none;
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
}
small{text-align:center;display:block;color:#64748b}
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

    <small>
        CEO: ceo / ceo123 <br>
        Manager: manager / manager123 <br>
        Employee: employee / employee123
    </small>
</div>

</body>
</html>