<?php
session_start();
include '../create_db.php';

if (!isset($_SESSION["login"]) || ($_SESSION["role"] ?? '') !== 'admin') {
    header("Location: /admin/login.php");
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $newEmail = trim($_POST['email'] ?? '');
    $newPass  = $_POST['password'] ?? '';
    $newRole  = ($_POST['role'] ?? 'admin') === 'consultant' ? 'consultant' : 'admin';

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $msg = "البريد الإلكتروني غير صالح.";
        $msgType = 'err';
    } elseif (strlen($newPass) < 4) {
        $msg = "كلمة المرور يجب أن تحتوي على 4 أحرف على الأقل.";
        $msgType = 'err';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO admins (email, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$newEmail, md5($newPass), $newRole]);
            $msg = "تمت إضافة الحساب بنجاح.";
            $msgType = 'ok';
        } catch (PDOException $e) {
            // UNIQUE constraint on email will trigger this if it already exists
            $msg = "هذا البريد الإلكتروني مسجل بالفعل.";
            $msgType = 'err';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_id'])) {
    $delId = (int) $_POST['delete_admin_id'];
    $currentAdminId = $db->prepare("SELECT id FROM admins WHERE email = ?");
    $currentAdminId->execute([$_SESSION['login']]);
    $myId = $currentAdminId->fetchColumn();

    if ($delId === (int) $myId) {
        $msg = "لا يمكنك حذف حسابك الخاص وأنت متصل به.";
        $msgType = 'err';
    } else {
        $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$delId]);
        $msg = "تم حذف الحساب.";
        $msgType = 'ok';
    }
}

$admins = $db->query("SELECT id, email, role FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المسؤولون - جماعة بركان</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<div class="app">
    <?php $active = 'admins'; $role = 'admin'; include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <h1>إدارة الحسابات (Admins / Consultants)</h1>
            <div class="right"><span> <?= date('d M Y') ?></span></div>
        </div>

        <div class="content" style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">
            <div class="card-form">
                <h3 style="margin-top:0;">إضافة حساب جديد</h3>
                <?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                <form method="POST" action="">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" required placeholder="admin@example.com">
                    <label>كلمة المرور</label>
                    <input type="password" name="password" required minlength="4" placeholder="••••••">
                    <label>الدور (Role)</label>
                    <select name="role">
                        <option value="admin">مسؤول (Admin) - صلاحيات كاملة</option>
                        <option value="consultant">مستشار (Consultant) - اطلاع فقط</option>
                    </select>
                    <button type="submit" name="add_admin" value="1"> إضافة</button>
                </form>
            </div>

            <div class="panel" style="flex:1; min-width:320px;">
                <h3>قائمة الحسابات (<?= count($admins) ?>)</h3>
                <table>
                    <thead><tr><th>#</th><th>البريد الإلكتروني</th><th>الدور</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                        <tr>
                            <td><?= (int)$a['id'] ?></td>
                            <td><?= htmlspecialchars($a['email']) ?><?= $a['email'] === $_SESSION['login'] ? ' <span style="color:var(--success);font-size:11px;">(أنت)</span>' : '' ?></td>
                            <td><?= $a['role'] === 'consultant' ? 'مستشار' : 'مسؤول' ?></td>
                            <td>
                                <form method="POST" action="" onsubmit="return confirm('حذف هذا الحساب؟');" style="display:inline;">
                                    <input type="hidden" name="delete_admin_id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="action-btn" style="color:var(--danger);">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
