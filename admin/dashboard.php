<?php
session_start();
include '../create_db.php';

if (!isset($_SESSION["login"])) {
    header("Location: /admin/login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'admin') {
    header("Location: /admin/consultant.php");
    exit;
}

$stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- stats ----------
$totalResponses = count($users);
$allRatings = [];
$officeSet = [];
$commentsCount = 0;
$monthly = []; // 'YYYY-MM' => [sum, count]
$bucketCounts = ['5' => 0, '4' => 0, '3' => 0, '1-2' => 0];

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

        $month = substr($u['submitted_at'] ?? '', 0, 7); // YYYY-MM
        if ($month) {
            if (!isset($monthly[$month])) $monthly[$month] = [0, 0];
            $monthly[$month][0] += $avg;
            $monthly[$month][1] += 1;
        }
    }
    if (!empty($u['office'])) $officeSet[$u['office']] = true;
    if (!empty(trim($u['comment'] ?? ''))) $commentsCount++;
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

$stmtAdmins = $db->query("SELECT COUNT(*) FROM admins");
$adminCount = (int) $stmtAdmins->fetchColumn();

$recent = array_slice($users, 0, 8);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - جماعة بركان</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
<div class="app">
    <?php $active = 'dashboard'; $role = 'admin'; include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <h1>لوحة الإدارة (Admin) - كاملة الصلاحيات</h1>
            <div class="right">
                <span> <?= date('d M Y') ?></span>
            </div>
        </div>

        <div class="content">
            <h2 style="font-size:18px;margin:4px 0 2px;">مرحباً بك، مدير النظام</h2>
            <p style="font-size:13px;color:var(--muted);margin:0 0 20px;">إدارة شاملة لتقييم الخدمات والمستخدمين والمستشارين</p>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="icon red">👥</div>
                    <div>
                        <div class="num"><?= $totalResponses ?></div>
                        <div class="label">إجمالي التقييمات</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon gold">⭐</div>
                    <div>
                        <div class="num"><?= $avgRatingOverall ?> / 5</div>
                        <div class="label">متوسط التقييم العام</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon green">🏢</div>
                    <div>
                        <div class="num"><?= $officeCount ?></div>
                        <div class="label">إجمالي المصالح</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon blue">💬</div>
                    <div>
                        <div class="num"><?= $commentsCount ?></div>
                        <div class="label">تعليقات المواطنين</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon pink">🔑</div>
                    <div>
                        <div class="num"><?= $adminCount ?></div>
                        <div class="label">عدد المسؤولين</div>
                    </div>
                </div>
            </div>

            <div class="panel-grid">
                <div class="panel">
                    <h3>متوسط التقييمات خلال الأشهر الأخيرة</h3>
                    <canvas id="lineChart" height="180"></canvas>
                </div>
                <div class="panel">
                    <h3>توزيع التقييمات</h3>
                    <canvas id="donutChart" height="180"></canvas>
                </div>
                <div class="panel" style="overflow-x:auto;">
                    <h3>آخر التقييمات</h3>
                    <table>
                        <thead>
                            <tr><th>المواطن</th><th>المصلحة</th><th>التقييم</th><th>التاريخ</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $u):
                            $ratings = json_decode($u['ratings'] ?? '[]', true) ?: [];
                            $ratings = array_filter($ratings, fn($v) => is_numeric($v));
                            $stars = count($ratings) ? (int) round(array_sum($ratings) / count($ratings)) : 0;
                            $date = $u['submitted_at'] ? date('d/m/Y', strtotime($u['submitted_at'])) : '—';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name'] ?: '—') ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($u['office'] ?: '—', 0, 22, '…')) ?></td>
                                <td class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= $stars ? '★' : '<span class="off">★</span>' ?>
                                    <?php endfor; ?>
                                </td>
                                <td><?= $date ?></td>
                                <td><button class="action-btn" onclick="openView(<?= (int)$u['id'] ?>)">عرض</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--muted);">لا توجد بيانات بعد</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="quick-actions">
                <a href="/admin/export.php">⬇️ تصدير التقارير (CSV)</a>
            </div>

            <div class="panel" id="responses" style="overflow-x:auto;">
                <h3>كل الإجابات (<?= $totalResponses ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>الاسم</th><th>العمر</th><th>البريد</th><th>الجنس</th><th>المصلحة</th>
                            <th>التاريخ</th><th>التقييم</th><th>محلول</th><th>اقتراحات</th><th>تعليق</th>
                            <th>وصول المستشار</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $ratings = json_decode($u['ratings'] ?? '[]', true) ?: [];
                        $improvements = json_decode($u['improvements'] ?? '[]', true) ?: [];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['age']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['sex']) ?></td>
                            <td><?= htmlspecialchars($u['office']) ?></td>
                            <td><?= htmlspecialchars($u['submitted_at']) ?></td>
                            <td><?= htmlspecialchars(implode(', ', $ratings)) ?></td>
                            <td><?= htmlspecialchars($u['resolved']) ?></td>
                            <td><?= htmlspecialchars(implode(' | ', $improvements)) ?></td>
                            <td class="comment-cell" id="comment-<?= (int)$u['id'] ?>" style="max-width:220px;white-space:pre-wrap;word-break:break-word;">
                                <?= htmlspecialchars($u['comment']) ?>
                            </td>
                            <td>
                                <button class="action-btn" id="visbtn-<?= (int)$u['id'] ?>"
                                        style="<?= $u['visible_to_consultant'] ? 'background:var(--success);color:#fff;border-color:var(--success);' : '' ?>"
                                        onclick="toggleConsultantAccess(<?= (int)$u['id'] ?>)">
                                    <?= $u['visible_to_consultant'] ? '✅ مسموح' : '🔒 ممنوع' ?>
                                </button>
                            </td>
                            <td><button class="action-btn" onclick="openEdit(<?= (int)$u['id'] ?>)">تعديل</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <h2>تعديل التعليق</h2>
        <textarea id="commentInput"></textarea>
        <div class="msg" id="statusMsg" style="display:none;"></div>
        <div class="modal-actions">
            <button class="btn-save" onclick="saveComment()">حفظ</button>
            <button class="btn-cancel" onclick="closeEdit()">إلغاء</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="viewOverlay">
    <div class="modal" id="viewContent"></div>
</div>

<script>
const usersData = <?= json_encode($users, JSON_UNESCAPED_UNICODE) ?>;

// ---- charts ----
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($monthValues) ?>,
            borderColor: '#b3202c',
            backgroundColor: 'rgba(179,32,44,.1)',
            tension: .35,
            fill: true,
            pointBackgroundColor: '#b3202c'
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
            backgroundColor: ['#7a1522', '#c9424f', '#e8a15a', '#f1d9b0']
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
});

// ---- edit comment modal ----
let currentId = null;
function openEdit(id) {
    currentId = id;
    document.getElementById('commentInput').value = document.getElementById('comment-' + id).textContent.trim();
    document.getElementById('statusMsg').style.display = 'none';
    document.getElementById('modalOverlay').classList.add('open');
}
function closeEdit() {
    document.getElementById('modalOverlay').classList.remove('open');
    currentId = null;
}
async function saveComment() {
    const statusMsg = document.getElementById('statusMsg');
    const comment = document.getElementById('commentInput').value;
    statusMsg.style.display = 'block';
    statusMsg.className = 'msg';
    statusMsg.textContent = 'جارٍ الحفظ...';
    try {
        const res = await fetch('/admin/update_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentId, comment })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('comment-' + currentId).textContent = comment;
            closeEdit();
        } else {
            statusMsg.className = 'msg err';
            statusMsg.textContent = 'خطأ: ' + (data.error || 'غير معروف');
        }
    } catch (e) {
        statusMsg.className = 'msg err';
        statusMsg.textContent = 'خطأ في الاتصال بالشبكة.';
    }
}

// ---- view modal (recent table) ----
function openView(id) {
    const u = usersData.find(x => parseInt(x.id) === id);
    if (!u) return;
    let ratings = [];
    try { ratings = JSON.parse(u.ratings || '[]'); } catch(e) {}
    let improvements = [];
    try { improvements = JSON.parse(u.improvements || '[]'); } catch(e) {}

    document.getElementById('viewContent').innerHTML = `
        <h2>تفاصيل التقييم</h2>
        <div class="detail-row"><b>الاسم:</b> ${escapeHtml(u.name)}</div>
        <div class="detail-row"><b>البريد:</b> ${escapeHtml(u.email)}</div>
        <div class="detail-row"><b>المصلحة:</b> ${escapeHtml(u.office)}</div>
        <div class="detail-row"><b>العمر / الجنس:</b> ${escapeHtml(u.age)} / ${escapeHtml(u.sex)}</div>
        <div class="detail-row"><b>التاريخ:</b> ${escapeHtml(u.submitted_at)}</div>
        <div class="detail-row"><b>التقييمات:</b> ${ratings.join(', ')}</div>
        <div class="detail-row"><b>اقتراحات:</b> ${improvements.map(escapeHtml).join(' | ') || '—'}</div>
        <div class="detail-row"><b>تعليق:</b> ${escapeHtml(u.comment) || '—'}</div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeView()">إغلاق</button>
        </div>
    `;
    document.getElementById('viewOverlay').classList.add('open');
}
function closeView() { document.getElementById('viewOverlay').classList.remove('open'); }
function escapeHtml(s) {
    if (!s) return '';
    return s.toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// ---- release / revoke consultant access ----
async function toggleConsultantAccess(id) {
    const btn = document.getElementById('visbtn-' + id);
    const currentlyVisible = btn.textContent.includes('مسموح');
    const nextVisible = !currentlyVisible;
    btn.disabled = true;
    try {
        const res = await fetch('/admin/toggle_consultant_access.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, visible: nextVisible })
        });
        const data = await res.json();
        if (data.success) {
            if (data.visible) {
                btn.textContent = '✅ مسموح';
                btn.style.background = 'var(--success)';
                btn.style.color = '#fff';
                btn.style.borderColor = 'var(--success)';
            } else {
                btn.textContent = '🔒 ممنوع';
                btn.style.background = '';
                btn.style.color = '';
                btn.style.borderColor = '';
            }
        } else {
            alert('خطأ: ' + (data.error || 'غير معروف'));
        }
    } catch (e) {
        alert('خطأ في الاتصال بالشبكة.');
    } finally {
        btn.disabled = false;
    }
}
</script>
</body>
</html>
