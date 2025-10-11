<?php
session_start();
require_once "db.php";

$error = '';
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $db_password);
        $stmt->fetch();

        if (password_verify($password, $db_password)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบบัญชีผู้ใช้";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>💧 เข้าสู่ระบบ - โรงน้ำดื่มตากับยาย</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
/* ====================== Root Variables ====================== */
:root {
    --glass-bg: rgba(255, 255, 255, 0.15);
    --glass-border: rgba(255, 255, 255, 0.3);
    --input-bg: rgba(255, 255, 255, 0.2);
    --btn-color1: #0072ff;
    --btn-color2: #00c6ff;
    --glow-color: #7ef5ff;
}

/* ====================== Global Reset ====================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Sarabun', sans-serif;
}

body {
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-image: 
        linear-gradient(rgba(26, 42, 90, 0.7), rgba(43, 69, 126, 0.8)),
        url('https://images.pexels.com/photos/40784/drops-of-water-water-nature-liquid-40784.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* ====================== Login Container ====================== */
.login-container {
    position: relative;
    width: 400px;
    padding: 50px 40px;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 40px;
    border: 2px solid var(--glass-border);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    text-align: center;
    color: #fff;
    animation: fadeIn 1.2s cubic-bezier(0.68, -0.55, 0.27, 1.55) forwards;
}

/* ====================== Fade In Animation ====================== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ====================== Logo ====================== */
.login-container .logo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid var(--glass-border);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
    margin-bottom: 25px;
    transition: transform 0.7s cubic-bezier(0.68, -0.55, 0.27, 1.55);
}
.login-container .logo:hover {
    transform: rotate(360deg) scale(1.1);
    box-shadow: 0 0 25px rgba(255, 255, 255, 0.4);
}

/* ====================== Heading ====================== */
h2 {
    margin-bottom: 30px;
    font-size: 32px;
    font-weight: 700;
    letter-spacing: 1px;
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
    color: #e0f7fa;
}

/* ====================== Form Styles ====================== */
form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}
label {
    text-align: left;
    font-weight: 600;
    font-size: 16px;
    text-shadow: 0 0 3px rgba(0, 0, 0, 0.2);
}

/* ====================== Input Fields ====================== */
.input-group { position: relative; }
input[type="text"], input[type="password"] {
    width: 100%;
    padding: 15px 20px;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    outline: none;
    font-size: 16px;
    background: var(--input-bg);
    color: #fff;
    transition: all 0.3s ease;
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
}
input::placeholder { color: rgba(255, 255, 255, 0.7); }
input:focus {
    background: rgba(255, 255, 255, 0.4);
    border-color: rgba(255, 255, 255, 0.8);
}

/* ====================== Password Toggle ====================== */
.password-toggle {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: rgba(255, 255, 255, 0.8);
    transition: color 0.3s;
}
.password-toggle:hover { color: #fff; }
input[type="password"] { padding-right: 50px; }

/* ====================== Submit Button ====================== */
button[type="submit"] {
    padding: 15px;
    border-radius: 15px;
    border: none;
    font-size: 20px;
    font-weight: bold;
    background: linear-gradient(45deg, var(--btn-color1), var(--btn-color2));
    color: #fff;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    letter-spacing: 1px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}
button[type="submit"]:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    background: linear-gradient(45deg, var(--btn-color2), var(--btn-color1));
}

/* ====================== Error Message ====================== */
.error-message {
    background: rgba(255, 59, 48, 0.7);
    padding: 15px;
    border-radius: 15px;
    font-weight: bold;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
    animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
    box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
}

/* ====================== Shake Animation ====================== */
@keyframes shake {
    10%, 90% { transform: translateX(-5px); }
    20%, 80% { transform: translateX(5px); }
    30%, 50%, 70% { transform: translateX(-5px); }
    40%, 60% { transform: translateX(5px); }
}

/* ====================== Responsive ====================== */
@media (max-width: 480px) {
    .login-container {
        width: 90%;
        padding: 30px 20px;
    }
    h2 { font-size: 24px; }
    button[type="submit"] { font-size: 16px; }
}

</style>
</head>
<body>

<div class="login-container">
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h2>เข้าสู่ระบบ</h2>
    <form method="POST" action="login.php">
        <label for="username">ชื่อผู้ใช้</label>
        <div class="input-group">
            <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
        </div>

        <label for="password">รหัสผ่าน</label>
        <div class="input-group">
            <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
            <i id="togglePassword" class='bx bx-show password-toggle'></i>
        </div>

        <button type="submit">
            <i class='bx bx-log-in'></i> เข้าสู่ระบบ
        </button>

        <?php if (!empty($error)) : ?>
            <p class="error-message"><?= $error ?></p>
        <?php endif; ?>
    </form>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

togglePassword.addEventListener('click', () => {
    const isPassword = password.getAttribute('type') === 'password';
    password.setAttribute('type', isPassword ? 'text' : 'password');
    togglePassword.classList.toggle('bx-show', !isPassword);
    togglePassword.classList.toggle('bx-hide', isPassword);
});
</script>

</body>
</html>