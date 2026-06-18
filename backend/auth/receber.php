cat > /tmp/demo-hybrid/receber.php << 'EOF'
<?php
$in   = json_decode(file_get_contents("php://input"), true);
$key  = base64_decode($in["key"]);
$iv   = base64_decode($in["iv"]);
$data = base64_decode($in["data"]);

$priv = file_get_contents(__DIR__ . "/private.pem");
openssl_private_decrypt($key, $aes, $priv, OPENSSL_PKCS1_OAEP_PADDING);

$tag  = substr($data, -16);
$data = substr($data, 0, -16);

$texto = openssl_decrypt($data, "aes-256-gcm", $aes, OPENSSL_RAW_DATA, $iv, $tag);

$user = get_current_user() ?: 'www-data';
$host = gethostname() ?: 'server';
error_log("{$user}:{$host}> dados descriptografados: " . $texto);

echo json_encode(["success" => true, "mensagem" => "Dados recebidos e descriptografados: " . $texto]);
EOF