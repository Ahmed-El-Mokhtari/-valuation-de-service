<?php
$db = new PDO('sqlite:' . __DIR__ . '/survey.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "COUNT=" . $count . "\n";
$names = $db->query("SELECT id,name,email,submitted_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "ROWS=\n";
foreach ($names as $row) {
    echo implode(' | ', $row) . "\n";
}
