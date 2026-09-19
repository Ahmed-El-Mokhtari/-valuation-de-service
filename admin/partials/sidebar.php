<?php
// Admin-only sidebar. Expects $active ('dashboard'|'admins') and $_SESSION['login'].
$adminEmail = $_SESSION['login'] ?? '';
$initial = strtoupper(substr($adminEmail, 0, 1)) ?: 'A';
?>

<aside class="sidebar">
    <div class="brand">
        <img src="../LOGO_1_-_Copy_2.png" alt="Logo" onerror="this.style.display='none'">
        <div>
            <strong>جماعة بركان</strong>
            <span>Commune de Berkane</span>
        </div>
    </div>

    <div class="profile">
        <div class="avatar"><?= htmlspecialchars($initial) ?></div>
        <div>
            <strong>مدير النظام</strong>
            <span><?= htmlspecialchars($adminEmail) ?></span>
            <span class="badge">متصل</span>
        </div>
    </div>

    <nav>
        <a href="/admin/dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">
             لوحة التحكم
        </a>
        <a href="/admin/admins.php" class="<?= $active === 'admins' ? 'active' : '' ?>">
            الحسابات (Admins/Consultants)
        </a>
        <a class="logout" href="/admin/login.php?logout=1" onclick="return confirm('Se déconnecter ?')">
            <span>⏻</span> تسجيل الخروج
        </a>
    </nav>

</aside>
