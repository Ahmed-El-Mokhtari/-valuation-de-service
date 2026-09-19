<?php
session_start();
include '../create_db.php';

header('Content-Type: application/json');

// Only a logged-in admin may modify comments
if (!isset($_SESSION["login"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Non autorisé"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id      = $data['id'] ?? null;
$comment = $data['comment'] ?? '';

if (!$id) {
    echo json_encode(["success" => false, "error" => "ID manquant"]);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE users SET comment = ? WHERE id = ?");
    $stmt->execute([$comment, $id]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
