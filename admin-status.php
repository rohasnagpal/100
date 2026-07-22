<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
benchmark_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo json_encode(array(
    'configured' => benchmark_admin_configured(),
    'logged_in' => benchmark_is_admin(),
    'csrf_token' => benchmark_is_admin() ? benchmark_csrf_token() : null,
), JSON_UNESCAPED_SLASHES);
