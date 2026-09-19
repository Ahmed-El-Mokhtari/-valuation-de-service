<?php

$dbPath = __DIR__ . '/survey.db';
$db = new PDO("sqlite:$dbPath");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    age TEXT,
    sex TEXT,
    office TEXT,
    ratings TEXT,
    resolved TEXT,
    improvements TEXT,
    comment TEXT,
    submitted_at TEXT
);

CREATE TABLE IF NOT EXISTS admins(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
);
");

$db->exec("
INSERT OR IGNORE INTO admins (email, password) VALUES (
    'ahmed@gmail.com',
    '81dc9bdb52d04dc20036dbd8313ed055' -- 1234
);
");

// ---- lightweight migrations: add columns if this is an older database ----
function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->query("PRAGMA table_info($table)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ($col['name'] === $column) return true;
    }
    return false;
}

if (!columnExists($db, 'admins', 'role')) {
    $db->exec("ALTER TABLE admins ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
}

if (!columnExists($db, 'users', 'visible_to_consultant')) {
    $db->exec("ALTER TABLE users ADD COLUMN visible_to_consultant INTEGER NOT NULL DEFAULT 0");
}
