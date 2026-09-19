<?php
session_start();
include '../create_db.php';

if (!isset($_SESSION["login"])) {
    header("Location: /admin/login.php");
    exit;
}

$role = $_SESSION["role"] ?? 'admin';

if ($role === 'consultant') {
    $stmt = $db->query("SELECT * FROM users WHERE visible_to_consultant = 1 ORDER BY id DESC");
} else {
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reponses_sondage.csv"');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens Arabic text correctly
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['ID', 'Nom', 'Email', 'Age', 'Sexe', 'Bureau', 'Notes', 'Résolu', 'Améliorations', 'Commentaire', 'Soumis le']);

foreach ($rows as $r) {
    $ratings = json_decode($r['ratings'] ?? '[]', true) ?: [];
    $improvements = json_decode($r['improvements'] ?? '[]', true) ?: [];
    fputcsv($out, [
        $r['id'], $r['name'], $r['email'], $r['age'], $r['sex'], $r['office'],
        implode(',', $ratings), $r['resolved'], implode(' | ', $improvements),
        $r['comment'], $r['submitted_at']
    ]);
}

fclose($out);
