<?php
declare(strict_types=1);
session_start();
require_once "../config/db.php";

function gerarToken(): string {
    return bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $acao = $_POST["acao"] ?? "";

    // SOLICITAR LINK
    if ($acao === "solicitar_link") {

        $email = $_POST["email"] ?? "";

        if (!$email) {
            header("Location: ../../frontend/pages/recuperacao-senha.html?msg=ok");
            exit;
        }

        $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["email" => $email]);

        $user = $stmt->fetch();

        if ($user) {
            $token = gerarToken();
            $tokenHash = hash("sha256", $token);

            // salva token
            $sql = "INSERT INTO tokens (user_id, token_hash, type, expires_at)
                    VALUES (:user_id, :hash, 'recovery', DATE_ADD(NOW(), INTERVAL 1 HOUR))";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                "user_id" => $user["id"],
                "hash" => $tokenHash
            ]);

            $link = "http://localhost/frontend/pages/redefinicao-senha.html?token=" . $token;

            echo "Link de recuperação: <a href='$link'>$link</a>";
            exit;
        }


        header("Location: ../../frontend/pages/recuperacao-senha.html?msg=ok");
        exit;
    }

    // REDEFINIR SENHA

    if ($acao === "redefinir_senha") {

        $token = $_POST["token"] ?? "";
        $novaSenha = $_POST["nova_senha"] ?? "";

        if (!$token || !$novaSenha) {
            die("Dados inválidos");
        }


        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{12,}/', $novaSenha)) {
            die("Senha fraca");
        }

        $tokenHash = hash("sha256", $token);

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

        $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT, ["cost" => 13]);

        $sql = "UPDATE users SET password = :senha WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            "senha" => $senhaHash,
            "id" => $userId
        ]);

        // invalida token
        $sql = "UPDATE tokens SET used_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["id" => $tokenData["id"]]);

        header("Location: ../../frontend/pages/login.html?reset=success");
        exit;
    }
}