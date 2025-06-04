<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';

header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$pdo = Database::getInstance();
$stmt = $pdo->prepare("SELECT poll_id, title FROM polls WHERE title LIKE ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute(['%' . $q . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);