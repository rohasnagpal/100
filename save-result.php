<?php
declare(strict_types=1);

session_start();

function respond(array $data, int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(array('ok' => false, 'error' => 'POST required.'), 405);
}

// Reuse the existing ROHAS admin session so public visitors cannot write fake
// leaderboard entries. The browser still downloads a local JSON without login.
if (empty($_SESSION['rohas_admin'])) {
    respond(array('ok' => false, 'error' => 'ROHAS admin login required for server save.'), 403);
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 2_500_000) {
    respond(array('ok' => false, 'error' => 'Result exceeds the 2.5 MB limit.'), 413);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond(array('ok' => false, 'error' => 'Empty request body.'), 400);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    respond(array('ok' => false, 'error' => 'Invalid JSON.'), 400);
}

if (($payload['benchmark']['id'] ?? '') !== 'indian-law-100' || ($payload['benchmark']['version'] ?? '') !== '1.0') {
    respond(array('ok' => false, 'error' => 'Wrong benchmark identifier or version.'), 400);
}
if (($payload['status'] ?? '') !== 'finalised' || empty($payload['finalised_at'])) {
    respond(array('ok' => false, 'error' => 'Only finalised runs may be saved.'), 400);
}
if (empty($payload['run_id']) || empty($payload['model']['id']) || !isset($payload['summary']['percentage'])) {
    respond(array('ok' => false, 'error' => 'Missing run, model, or score data.'), 400);
}
if (!isset($payload['results']) || !is_array($payload['results']) || count($payload['results']) !== 100) {
    respond(array('ok' => false, 'error' => 'A finalised result must contain exactly 100 cases.'), 400);
}

$caseIds = array();
foreach ($payload['results'] as $result) {
    $caseId = is_array($result) ? (string) ($result['case_id'] ?? '') : '';
    $grade = is_array($result) ? ($result['final_grade'] ?? null) : null;
    if ($caseId === '' || isset($caseIds[$caseId]) || !is_array($grade)) {
        respond(array('ok' => false, 'error' => 'Every case must have a unique ID and final grade.'), 400);
    }
    $caseIds[$caseId] = true;
    foreach (array('outcome' => 50, 'legal_basis' => 30, 'reasoning' => 20) as $axis => $maximum) {
        if (!isset($grade[$axis]) || !is_numeric($grade[$axis]) || (float) $grade[$axis] < 0 || (float) $grade[$axis] > $maximum) {
            respond(array('ok' => false, 'error' => 'Every final grade must contain all three numeric scoring axes.'), 400);
        }
    }
}

$percentage = (float) $payload['summary']['percentage'];
if (!is_finite($percentage) || $percentage < 0 || $percentage > 100) {
    respond(array('ok' => false, 'error' => 'Invalid percentage.'), 400);
}

// Refuse accidental secret persistence even if the client schema changes later.
$lowerRaw = strtolower($raw);
foreach (array('api_key', 'apikey', 'authorization') as $secretKey) {
    if (strpos($lowerRaw, '"' . $secretKey . '"') !== false) {
        respond(array('ok' => false, 'error' => 'Result contains a forbidden secret field.'), 400);
    }
}

$modelSlug = strtolower((string) $payload['model']['id']);
$modelSlug = preg_replace('/[^a-z0-9]+/', '-', $modelSlug) ?: 'model';
$modelSlug = trim(substr($modelSlug, 0, 60), '-');
$stamp = gmdate('Ymd-His');
$random = bin2hex(random_bytes(4));
$filename = sprintf('indian-law-100-%s-%s-%s.json', $modelSlug, $stamp, $random);

$resultsDir = __DIR__ . DIRECTORY_SEPARATOR . 'results';
if (!is_dir($resultsDir) && !mkdir($resultsDir, 0755, true) && !is_dir($resultsDir)) {
    respond(array('ok' => false, 'error' => 'Could not create the results directory.'), 500);
}

$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    respond(array('ok' => false, 'error' => 'Could not encode the result.'), 500);
}

$target = $resultsDir . DIRECTORY_SEPARATOR . $filename;
if (file_put_contents($target, $encoded . PHP_EOL, LOCK_EX) === false) {
    respond(array('ok' => false, 'error' => 'Could not write the result file.'), 500);
}
@chmod($target, 0644);

respond(array('ok' => true, 'filename' => $filename), 201);
