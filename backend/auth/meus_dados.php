<?php
declare(strict_types=1);

session_start();
require_once "../config/db.php";

// verifica se está logado
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["erro" => "Não autenticado"]);
    exit;
}

$userId = $_SESSION["user_id"];

$sql = "SELECT nome, email FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(["id" => $userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["erro" => "Usuário não encontrado"]);
    exit;
}

header("Content-Type: application/json");
echo json_encode($user);