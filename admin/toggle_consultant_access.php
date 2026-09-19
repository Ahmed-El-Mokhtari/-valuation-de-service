<?php
session_start();
include '../create_db.php';

header('Content-Type: application/json');

// Only a logged-in admin (not a consultant) may release/revoke responses
if (!isset($_SESSION["login"]) || ($_SESSION["role"] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Non autorisé"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id      = $data['id'] ?? null;
$visible = !empty($data['visible']) ? 1 : 0;

if (!$id) {
    echo json_encode(["success" => false, "error" => "ID manquant"]);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE users SET visible_to_consultant = ? WHERE id = ?");
    $stmt->execute([$visible, $id]);

    echo json_encode(["success" => true, "visible" => $visible]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
