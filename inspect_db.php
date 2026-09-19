<?php
$db = new PDO("sqlite:" . __DIR__ . "/survey.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->query("SELECT id, name, email, age, sex, office, ratings, resolved, improvements, comment, submitted_at FROM users ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "---- ROW {$row['id']} ----\n";
    foreach ($row as $key => $value) {
        echo "$key: " . ($value === null ? 'NULL' : $value) . "\n";
    }
    echo "\n";
}
