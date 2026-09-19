<?php
session_start();
include '../create_db.php';

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: /admin/consultant_login.php");
    exit;
}

if (isset($_SESSION["login"]) && ($_SESSION["role"] ?? '') === 'consultant') {
    header("Location: /admin/consultant.php");
    exit;
}

$error = '';

if (isset($_POST["submit"])) {
    $email    = trim($_POST["email"] ?? '');
    $password = md5($_POST["password"] ?? '');

    $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email AND password = :pass AND role = 'consultant'");
    $stmt->execute([
        "email" => $email,
        "pass"  => $password
    ]);

    $consultant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($consultant) {
        $_SESSION["login"] = $consultant["email"];
        $_SESSION["role"]  = 'consultant';
        header("Location: /admin/consultant.php");
        exit;
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المستشار - جماعة بركان</title>
    <style>
        :root {
            --blue-dk: #0d3b66;
            --blue: #1e73b0;
            --cream-bg: #eaf3fb;
            --border: #dce9f5;
            --text: #1f2a33;
            --muted: #7d8a94;
            --danger: #d13c3c;
            --radius-lg: 16px;
            --radius-sm: 8px;
            --shadow: 0 8px 28px rgba(13,59,102,.1);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            direction: rtl; color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #eaf3fb, #cfe4f5);
        }
        .login-card {
            background: #fff; padding: 34px 30px; border-radius: var(--radius-lg);
            box-shadow: var(--shadow); width: 100%; max-width: 330px; text-align: center;
        }
        .login-card img { width: 64px; margin-bottom: 10px; }
        .login-card h1 { font-size: 16px; margin: 0 0 2px; color: var(--blue-dk); }
        .login-card p.sub { font-size: 12px; color: var(--muted); margin: 0 0 20px; }
        .login-card input {
            width: 100%; padding: 11px 12px; border: 1px solid var(--border);
            border-radius: var(--radius-sm); font-size: 13.5px; margin-bottom: 12px;
            text-align: right; font-family: inherit;
        }
        .login-card button {
            width: 100%; background: var(--blue); color: #fff; border: none;
            padding: 11px; border-radius: var(--radius-sm); font-size: 14px;
            font-weight: 600; cursor: pointer;
        }
        .login-card button:hover { background: var(--blue-dk); }
        .err { color: var(--danger); font-size: 12.5px; margin-bottom: 12px; }
        .switch-link { display: block; margin-top: 16px; font-size: 12px; color: var(--muted); }
        .switch-link a { color: var(--blue); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <form class="login-card" action="" method="POST">
        <img src="../LOGO_1_-_Copy_2.png" alt="Logo" onerror="this.style.display='none'">
        <h1>جماعة بركان</h1>
        <p class="sub">فضاء المستشار (Consultant) - تسجيل الدخول</p>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="email" placeholder="البريد الإلكتروني" name="email" required />
        <input type="password" placeholder="كلمة المرور" name="password" required />
        <button type="submit" name="submit">دخول</button>
        <span class="switch-link">أنت مسؤول؟ <a href="/admin/login.php">تسجيل الدخول هنا</a></span>
    </form>
</body>
</html>
