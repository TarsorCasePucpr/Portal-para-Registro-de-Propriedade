<?php
require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $acao = $_POST["acao"] ?? "";

    if ($acao === "redefinir_senha") {

        $token = $_POST["token"] ?? "";
        $novaSenha = $_POST["nova_senha"] ?? "";

        if (!$token || !$novaSenha) {
            die("Dados inválidos");
        }

        // hash do token
        $tokenHash = hash("sha256", $token);

        // buscar token no banco
        $sql = "SELECT * FROM tokens 
                WHERE token_hash = :hash 
                AND type = 'recovery'
                AND used_at IS NULL
                AND expires_at > NOW()
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(["hash" => $tokenHash]);

        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            die("Token inválido ou expirado");
        }

        $userId = $tokenData["user_id"];

        // hash da nova senha
        $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT, ["cost" => 13]);

        // atualizar senha
        $sql = "UPDATE users SET password = :senha WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            "senha" => $senhaHash,
            "id" => $userId
        ]);

        // invalidar token
        $sql = "UPDATE tokens SET used_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["id" => $tokenData["id"]]);

        // redirecionar
        header("Location: ../../frontend/pages/login.html?reset=success");
        exit;
    }
}