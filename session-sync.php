<?php
/**
 * Split-Domain Session Sync for ServEasy Service Provider Portal
 * Cryptographically verifies the JWT issued by the Central Backend API
 * and establishes the local PHP $_SESSION['dealer'] on the Provider domain.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/common/ApiBridge.php';

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];
$token = trim($_POST['access_token'] ?? ($jsonBody['access_token'] ?? ''));

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing access token']);
    exit;
}

$verifyRes = \Frontend\ApiBridge::callApi('dealer-auth-api.php', 'POST', [
    'action'       => 'verify_token',
    'access_token' => $token
]);

if (!empty($verifyRes['status']) && $verifyRes['status'] === 'success' && !empty($verifyRes['dealer']['id'])) {
    $_SESSION['dealer'] = [
        'id'          => (int)$verifyRes['dealer']['id'],
        'provider_id' => (int)$verifyRes['dealer']['id'],
        'name'        => $verifyRes['dealer']['name'] ?? 'Service Provider',
        'email'       => $verifyRes['dealer']['email'] ?? '',
        'phone'       => $verifyRes['dealer']['phone'] ?? '',
        'role'        => 'provider'
    ];
    $_SESSION['access_token'] = $token;

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    setcookie('access_token', $token, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    echo json_encode(['status' => 'success', 'dealer' => $_SESSION['dealer']]);
    exit;
}

http_response_code(401);
echo json_encode([
    'status'  => 'error',
    'message' => $verifyRes['message'] ?? 'Could not verify login session with Central API'
]);
exit;
