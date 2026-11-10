<?php
declare(strict_types=1);

require __DIR__ . '/constants.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/store.php';
require __DIR__ . '/steps.php';

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function send_session_cookie(string $token, int $expires): void
{
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $appPath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/') . '/';
    setcookie(SESSION_COOKIE, $token, ['expires' => $expires, 'path' => $appPath,
        'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
}

function start_session(array $auth, int $nowSeconds): array
{
    $token = bin2hex(random_bytes(32));
    send_session_cookie($token, $nowSeconds + SESSION_TTL_SECONDS);
    return auth_with_session($auth, $token, $nowSeconds);
}

function load_state(int $nowMs): array
{
    $state = read_json_file(STATE_FILE);
    if ($state === null) {
        $state = state_from_seed(seed_rows(), $nowMs);
        write_json_file(STATE_FILE, $state);
    }
    return $state;
}

/** The one orchestrator: every request runs top to bottom through here, under the data lock. */
function handle_request(): void
{
    $nowSeconds = time();
    $nowMs = (int) round(microtime(true) * 1000);
    $isRead = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    $body = $isRead ? [] : json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        respond(400, ['error' => 'JSON inválido']);
        return;
    }
    $action = $isRead ? 'state' : (string) ($body['action'] ?? '');
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    $token = (string) ($_COOKIE[SESSION_COOKIE] ?? '');
    $auth = (read_json_file(AUTH_FILE) ?? []) + empty_auth();

    if ($action === 'setup') {
        if ($auth['hash'] !== '') {
            respond(409, ['auth' => 'login', 'error' => 'A senha já foi criada.']);
            return;
        }
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            respond(400, ['auth' => 'setup', 'error' => 'Use pelo menos ' . MIN_PASSWORD_LENGTH . ' caracteres.']);
            return;
        }
        $auth['hash'] = password_hash($password, PASSWORD_DEFAULT);
        write_json_file(AUTH_FILE, start_session($auth, $nowSeconds));
        respond(200, ['auth' => 'ok', 'state' => load_state($nowMs)]);
        return;
    }
    if ($auth['hash'] === '') {
        respond(401, ['auth' => 'setup']);
        return;
    }
    if ($action === 'login') {
        $wait = lockout_remaining($auth, $nowSeconds);
        if ($wait > 0) {
            respond(429, ['auth' => 'login', 'error' => "Muitas tentativas. Tente de novo em {$wait}s."]);
            return;
        }
        if (!password_verify($password, $auth['hash'])) {
            write_json_file(AUTH_FILE, auth_with_failure($auth, $nowSeconds));
            respond(401, ['auth' => 'login', 'error' => 'Senha errada.']);
            return;
        }
        write_json_file(AUTH_FILE, start_session($auth, $nowSeconds));
        respond(200, ['auth' => 'ok', 'state' => load_state($nowMs)]);
        return;
    }
    if (!session_is_valid($auth, $token, $nowSeconds)) {
        respond(401, ['auth' => 'login']);
        return;
    }
    if ($action === 'logout') {
        write_json_file(AUTH_FILE, auth_without_session($auth, $token));
        send_session_cookie('', 1);
        respond(200, ['auth' => 'login']);
        return;
    }
    $state = load_state($nowMs);
    if ($action === 'ops' && is_array($body['ops'] ?? null)) {
        $state = apply_ops($state, $body['ops'], $nowMs);
        write_json_file(STATE_FILE, $state);
    } elseif ($action !== 'state') {
        respond(400, ['error' => 'Ação desconhecida.']);
        return;
    }
    respond(200, ['auth' => 'ok', 'state' => $state]);
}

try {
    with_lock('handle_request');
} catch (Throwable $error) {
    error_log('[mudanca] ' . $error->getMessage());
    respond(500, ['error' => 'Erro no servidor.']);
}
