<?php

$data = json_decode(file_get_contents("php://input"), true);

$name         = $data['name'] ?? '';
$email        = $data['email'] ?? '';
$age          = $data['age'] ?? '';
$sex          = $data['sex'] ?? '';
$office       = $data['office'] ?? '';
$ratings      = json_encode($data['ratings'] ?? []);
$resolved     = $data['resolved'] ?? '';
$improvements = json_encode($data['improvements'] ?? []);
$comment      = $data['comment'] ?? '';
$submittedAt  = $data['submittedAt'] ?? '';

try {

    // الاتصال بقاعدة SQLite
    $db = new PDO("sqlite:survey.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إدخال البيانات
    $stmt = $db->prepare("
        INSERT INTO users
        (name, email, age, sex, office, ratings, resolved, improvements, comment, submitted_at)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $name,
        $email,
        $age,
        $sex,
        $office,
        $ratings,
        $resolved,
        $improvements,
        $comment,
        $submittedAt
    ]);

    echo json_encode([
        "success" => true
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);

}
?>