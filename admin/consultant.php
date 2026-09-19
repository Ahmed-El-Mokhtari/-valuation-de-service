<?php
session_start();
include '../create_db.php';

if (!isset($_SESSION["login"])) {
    header("Location: /admin/consultant_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'consultant') {
    header("Location: /admin/dashboard.php");
    exit;
}

$adminEmail = $_SESSION['login'] ?? '';
$initial = strtoupper(substr($adminEmail, 0, 1)) ?: 'C';

// Consultants only ever see responses the admin has explicitly released
$stmt = $db->prepare("SELECT * FROM users WHERE visible_to_consultant = 1 ORDER BY id DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- stats (computed only from released responses) ----------
$totalResponses = count($users);
$allRatings = [];
$officeSet = [];
$officeAgg = []; // office => [sum, count]
$monthly = [];
$bucketCounts = ['5' => 0, '4' => 0, '3' => 0, '1-2' => 0];
$thisMonthCount = 0;
$curMonth = date('Y-m');

foreach ($users as $u) {
    $ratings = json_decode($u['ratings'] ?? '[]', true) ?: [];
    $ratings = array_filter($ratings, fn($v) => is_numeric($v));
    if (!empty($ratings)) {
        $avg = array_sum($ratings) / count($ratings);
        $allRatings = array_merge($allRatings, $ratings);

        $rounded = (int) round($avg);
        if ($rounded >= 5) $bucketCounts['5']++;
        elseif ($rounded == 4) $bucketCounts['4']++;
        elseif ($rounded == 3) $bucketCounts['3']++;
        else $bucketCounts['1-2']++;

        $month = substr($u['submitted_at'] ?? '', 0, 7);
        if ($month) {
            if (!isset($monthly[$month])) $monthly[$month] = [0, 0];
            $monthly[$month][0] += $avg;
            $monthly[$month][1] += 1;
            if ($month === $curMonth) $thisMonthCount++;
        }

        if (!empty($u['office'])) {
            if (!isset($officeAgg[$u['office']])) $officeAgg[$u['office']] = [0, 0];
            $officeAgg[$u['office']][0] += $avg;
            $officeAgg[$u['office']][1] += 1;
        }
    }
    if (!empty($u['office'])) $officeSet[$u['office']] = true;
}

$avgRatingOverall = count($allRatings) ? round(array_sum($allRatings) / count($allRatings), 2) : 0;
$officeCount = count($officeSet);

ksort($monthly);
$monthly = array_slice($monthly, -6, 6, true);
$monthLabels = [];
$monthValues = [];
$moisFr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jui','07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
foreach ($monthly as $m => $pair) {
    $parts = explode('-', $m);
    $monthLabels[] = $moisFr[$parts[1] ?? ''] ?? $m;
    $monthValues[] = round($pair[0] / $pair[1], 2);
}

$officeAvg = [];
foreach ($officeAgg as $office => $pair) {
    $officeAvg[] = ['office' => $office, 'avg' => round($pair[0] / $pair[1], 2)];
}
usort($officeAvg, fn($a, $b) => $b['avg'] <=> $a['avg']);
$officeAvg = array_slice($officeAvg, 0, 6);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المستشار - جماعة بركان</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        :root {
            --blue-dk: #0d3b66;
            --blue: #1e73b0;
            --blue-lt: #4fa3d8;
            --blue-active: #2c86c6;
            --cream-bg: #f5f9fd;
            --card-bg: #ffffff;
            --border: #e2edf6;
            --text: #1f2a33;
            --muted: #7d8a94;
            --success: #2e9b4f;
            --warn: #e0a527;
            --danger: #d13c3c;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow: 0 8px 28px rgba(13,59,102,.08);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: var(--cream-bg); color: var(--text); direction: rtl; }
        a { text-decoration: none; color: inherit; }

        .app { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; flex-shrink: 0; color: #fff; padding: 20px 16px;
            background: linear-gradient(180deg, var(--blue-dk), var(--blue));
            display: flex; flex-direction: column;
        }
        .sidebar .brand { display: flex; align-items: center; gap: 10px; padding: 4px 6px 20px; border-bottom: 1px solid rgba(255,255,255,.15); margin-bottom: 16px; }
        .sidebar .brand img { width: 40px; height: 40px; border-radius: 8px; background: #fff; padding: 3px; }
        .sidebar .brand div strong { display: block; font-size: 14px; }
        .sidebar .brand div span { display: block; font-size: 11px; opacity: .8; }
        .sidebar .profile { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.1); border-radius: var(--radius-md); padding: 10px 12px; margin-bottom: 18px; }
        .sidebar .profile .avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,.22); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; }
        .sidebar .profile strong { display: block; font-size: 13px; }
        .sidebar .profile span { display: block; font-size: 11px; opacity: .8; }
        .sidebar .profile .badge { display: inline-block; margin-top: 3px; font-size: 10px; background: var(--success); padding: 1px 8px; border-radius: 20px; }
        .sidebar nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-sm); font-size: 13.5px; color: rgba(255,255,255,.9); }
        .sidebar nav a:hover { background: rgba(255,255,255,.1); }
        .sidebar nav a.active { background: var(--blue-active); font-weight: 600; }
        .sidebar .logout {margin-top: auto; padding-top: 14px; direction: rtl; align-items:flex-start; padding-inline: 12px;border-top: 1px solid rgba(255,255,255,.12); font-size: 13px; color: rgba(255,255,255,.85); display: flex; float: right; gap: 8px; padding-inline: 12px;  }
        .sidebar .logout:hover { color: #fff; }
        .main { flex: 1; min-width: 0; }
        .topbar { background: var(--card-bg); border-bottom: 1px solid var(--border); padding: 16px 26px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .topbar h1 { font-size: 17px; margin: 0; }
        .topbar .right { display: flex; align-items: center; gap: 14px; font-size: 13px; color: var(--muted); }
        .content { padding: 24px 26px 40px; }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 22px; }
        .stat-card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 16px 18px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 12px; }
        .stat-card .icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; background: #e2edfb; color: var(--blue); }
        .stat-card .num { font-size: 20px; font-weight: 700; line-height: 1.1; }
        .stat-card .label { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .panel-grid { display: grid; grid-template-columns: 1.3fr 1fr 1.3fr; gap: 16px; margin-bottom: 22px; }
        @media (max-width: 1100px) { .panel-grid { grid-template-columns: 1fr; } }
        .panel { background: var(--card-bg); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow); }
        .panel h3 { margin: 0 0 14px; font-size: 14px; }

        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: right; padding: 9px 10px; font-size: 12.5px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        .stars { color: var(--warn); letter-spacing: 1px; font-size: 13px; }
        .stars .off { color: #e4ecf3; }

        .export-btn { background: var(--blue); color: #fff; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; }
        .export-btn:hover { background: var(--blue-dk); }

        .bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .bar-row .office { flex: 1; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-row .track { width: 110px; height: 8px; background: #eef4fa; border-radius: 6px; overflow: hidden; }
        .bar-row .fill { height: 100%; background: var(--blue); }
        .bar-row .val { font-size: 12px; font-weight: 600; width: 24px; text-align: left; }
    </style>
</head>
<body>
<div class="app">
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
                <strong>مستشار</strong>
                <span><?= htmlspecialchars($adminEmail) ?></span>
                <span class="badge">متصل</span>
            </div>
        </div>

        <nav>
            <a href="/admin/consultant.php" class="active"><span>🏠</span> لوحة التحكم</a>
        </nav>

        <a class="logout" href="/admin/consultant_login.php?logout=1" onclick="return confirm('Se déconnecter ?')">
            <span>⏻</span> تسجيل الخروج
        </a>
    </aside>

    <div class="main">
        <div class="topbar">
            <h1>لوحة المستشار (Consultant) - صلاحية الاطلاع فقط</h1>
            <div class="right">
                <a href="/admin/export.php" class="export-btn"> تصدير التقرير</a>
                <span> <?= date('d M Y') ?></span>
            </div>
        </div>

        <div class="content">
            <h2 style="font-size:18px;margin:4px 0 2px;">مرحباً بك، مستشار</h2>
            <p style="font-size:13px;color:var(--muted);margin:0 0 20px;">عرض التقارير والإحصائيات الخاصة بتقييم الخدمات (البيانات المصرح بها من طرف الإدارة فقط)</p>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="icon">👥</div>
                    <div>
                        <div class="num"><?= $totalResponses ?></div>
                        <div class="label">إجمالي التقييمات المتاحة</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">⭐</div>
                    <div>
                        <div class="num"><?= $avgRatingOverall ?> / 5</div>
                        <div class="label">متوسط التقييم العام</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">🏢</div>
                    <div>
                        <div class="num"><?= $officeCount ?></div>
                        <div class="label">إجمالي المصالح</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">📈</div>
                    <div>
                        <div class="num"><?= $thisMonthCount ?></div>
                        <div class="label">التقييمات هذا الشهر</div>
                    </div>
                </div>
            </div>

            <div class="panel-grid">
                <div class="panel">
                    <h3>متوسط التقييمات حسب الشهر</h3>
                    <canvas id="lineChart" height="180"></canvas>
                </div>
                <div class="panel">
                    <h3>توزيع التقييمات</h3>
                    <canvas id="donutChart" height="180"></canvas>
                </div>
                <div class="panel">
                    <h3>متوسط التقييم حسب المصلحة</h3>
                    <?php foreach ($officeAvg as $o): ?>
                        <div class="bar-row">
                            <div class="office"><?= htmlspecialchars($o['office']) ?></div>
                            <div class="track"><div class="fill" style="width:<?= min(100, $o['avg'] / 5 * 100) ?>%;"></div></div>
                            <div class="val"><?= $o['avg'] ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($officeAvg)): ?>
                        <p style="font-size:12.5px;color:var(--muted);">لا توجد بيانات متاحة بعد.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel" id="responses" style="overflow-x:auto;">
                <h3>التقييمات المتاحة (<?= $totalResponses ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>الاسم</th><th>المصلحة</th><th>التقييم</th><th>التاريخ</th><th>اقتراحات</th><th>تعليق</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $ratings = json_decode($u['ratings'] ?? '[]', true) ?: [];
                        $improvements = json_decode($u['improvements'] ?? '[]', true) ?: [];
                        $ratingsNum = array_filter($ratings, fn($v) => is_numeric($v));
                        $stars = count($ratingsNum) ? (int) round(array_sum($ratingsNum) / count($ratingsNum)) : 0;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['office']) ?></td>
                            <td class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= $stars ? '★' : '<span class="off">★</span>' ?>
                                <?php endfor; ?>
                            </td>
                            <td><?= $u['submitted_at'] ? date('d/m/Y', strtotime($u['submitted_at'])) : '—' ?></td>
                            <td><?= htmlspecialchars(implode(' | ', $improvements)) ?></td>
                            <td style="max-width:220px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($u['comment']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--muted);">لا توجد تقييمات مصرح بها من طرف الإدارة بعد</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($monthValues) ?>,
            borderColor: '#1e73b0',
            backgroundColor: 'rgba(30,115,176,.1)',
            tension: .35,
            fill: true,
            pointBackgroundColor: '#1e73b0'
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 5 } } }
});

new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['ممتاز (5)', 'جيد (4)', 'متوسط (3)', 'ضعيف (1-2)'],
        datasets: [{
            data: [<?= $bucketCounts['5'] ?>, <?= $bucketCounts['4'] ?>, <?= $bucketCounts['3'] ?>, <?= $bucketCounts['1-2'] ?>],
            backgroundColor: ['#0d3b66', '#1e73b0', '#4fa3d8', '#b7d9ee']
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
});
</script>
</body>
</html>
