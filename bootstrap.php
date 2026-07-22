<?php
declare(strict_types=1);

const BENCHMARK_SESSION_NAME = 'indian_law_100';

function benchmark_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = array(
        'admin_password_hash' => '',
        'session_timeout' => 43_200,
    );

    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
    if (is_file($configPath)) {
        $local = require $configPath;
        if (is_array($local)) {
            $config = array_replace($config, $local);
        }
    }

    $environmentHash = getenv('INDIAN_LAW_100_ADMIN_PASSWORD_HASH');
    if (is_string($environmentHash) && trim($environmentHash) !== '') {
        $config['admin_password_hash'] = trim($environmentHash);
    }

    // Plain-text environment configuration is convenient for local containers.
    // Prefer INDIAN_LAW_100_ADMIN_PASSWORD_HASH for production deployments.
    $environmentPassword = getenv('INDIAN_LAW_100_ADMIN_PASSWORD');
    if (is_string($environmentPassword) && $environmentPassword !== '') {
        $config['admin_password'] = $environmentPassword;
    }

    $config['session_timeout'] = max(900, (int) ($config['session_timeout'] ?? 43_200));
    return $config;
}

function benchmark_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function benchmark_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(BENCHMARK_SESSION_NAME);
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => benchmark_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();

    $now = time();
    $timeout = benchmark_config()['session_timeout'];
    $lastSeen = (int) ($_SESSION['benchmark_last_seen'] ?? 0);
    if ($lastSeen > 0 && $now - $lastSeen > $timeout) {
        unset($_SESSION['benchmark_admin']);
        session_regenerate_id(true);
    }
    $_SESSION['benchmark_last_seen'] = $now;
}

function benchmark_admin_configured(): bool
{
    $config = benchmark_config();
    $hash = trim((string) ($config['admin_password_hash'] ?? ''));
    $hashInfo = $hash !== '' ? password_get_info($hash) : array();
    return ($hash !== '' && ($hashInfo['algo'] ?? null) !== null) || isset($config['admin_password']);
}

function benchmark_password_matches(string $password): bool
{
    $config = benchmark_config();
    $hash = trim((string) ($config['admin_password_hash'] ?? ''));
    if ($hash !== '') {
        return password_verify($password, $hash);
    }
    if (isset($config['admin_password'])) {
        return hash_equals((string) $config['admin_password'], $password);
    }
    return false;
}

function benchmark_is_admin(): bool
{
    return !empty($_SESSION['benchmark_admin']);
}

function benchmark_csrf_token(): string
{
    if (empty($_SESSION['benchmark_csrf'])) {
        $_SESSION['benchmark_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['benchmark_csrf'];
}

function benchmark_csrf_valid(?string $token): bool
{
    $expected = (string) ($_SESSION['benchmark_csrf'] ?? '');
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function benchmark_safe_next($value): string
{
    $next = trim((string) $value);
    if ($next !== '' && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._?=&%\/-]*\z/', $next) && strpos($next, '//') === false && $next[0] !== '/') {
        return $next;
    }
    return 'index.html';
}

function benchmark_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
