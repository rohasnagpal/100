<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
benchmark_start_session();

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$next = benchmark_safe_next($_GET['next'] ?? $_POST['next'] ?? 'index.html');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!benchmark_csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = array();
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    } elseif (!benchmark_admin_configured()) {
        $error = 'Administrator authentication has not been configured.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        if (benchmark_password_matches($password)) {
            session_regenerate_id(true);
            $_SESSION['benchmark_admin'] = true;
            $_SESSION['benchmark_last_seen'] = time();
            $_SESSION['benchmark_csrf'] = bin2hex(random_bytes(32));
            header('Location: ' . $next);
            exit;
        }
        usleep(350000);
        $error = 'Incorrect administrator password.';
    }
}

$configured = benchmark_admin_configured();
$loggedIn = benchmark_is_admin();
$csrf = benchmark_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Administrator · Indian Law 100</title>
  <style>
    :root{--bg:#f7f5ef;--paper:#fffefa;--ink:#201e1b;--muted:#696258;--line:#e6dfd3;--accent:#c76443;--accent-deep:#994426;--green:#557d45;--green-soft:#e8f1e4;--red:#a94535;--red-soft:#f7e2de;--dark:#25221e}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--ink);font:14px/1.55 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.box{width:min(470px,calc(100% - 28px));background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:28px;box-shadow:0 12px 34px rgba(55,45,30,.08)}.mark{width:42px;height:42px;border-radius:10px;background:var(--dark);color:#fff;display:grid;place-items:center;font:600 18px Georgia,serif;margin-bottom:18px}h1{font:600 27px/1.2 Georgia,serif;margin:0 0 8px}p{color:var(--muted);margin:0 0 18px}label{display:block;margin:14px 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.input{width:100%;height:46px;border:1px solid #d4cab9;border-radius:9px;background:#fff;padding:0 13px;font:inherit}.input:focus{outline:0;border-color:var(--accent);box-shadow:0 0 0 3px #f7e5de}.row{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.btn{border:1px solid var(--accent);border-radius:9px;background:var(--accent);color:#fff;padding:10px 15px;font:inherit;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer}.btn.secondary{background:#fff;border-color:#d4cab9;color:var(--ink)}.message{border-radius:9px;padding:10px 12px;margin:14px 0}.error{color:var(--red);background:var(--red-soft)}.ok{color:var(--green);background:var(--green-soft)}.setup{border:1px solid var(--line);background:#fbfaf6;border-radius:10px;padding:13px;margin-top:16px;color:var(--muted);font-size:12px}.setup code{color:var(--ink);overflow-wrap:anywhere}.setup ol{margin:8px 0 0;padding-left:20px}.setup li+li{margin-top:6px}
  </style>
</head>
<body>
  <main class="box">
    <div class="mark">IL</div>
    <h1>Benchmark administrator</h1>
    <?php if ($loggedIn): ?>
      <div class="message ok">You are logged in. Finalised runs can be saved to this server’s leaderboard.</div>
      <div class="row">
        <a class="btn" href="<?php echo benchmark_h($next); ?>">Continue</a>
        <a class="btn secondary" href="leaderboard.php">Leaderboard</a>
        <form method="post" action="admin.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo benchmark_h($csrf); ?>">
          <input type="hidden" name="logout" value="1">
          <button class="btn secondary" type="submit">Log out</button>
        </form>
      </div>
    <?php elseif (!$configured): ?>
      <p>This installation needs an administrator password before it can save leaderboard results.</p>
      <div class="setup">
        <strong>Server setup</strong>
        <ol>
          <li>Copy <code>config.example.php</code> to <code>config.php</code>.</li>
          <li>Generate a password hash using the command in that file.</li>
          <li>Paste the hash into <code>admin_password_hash</code>, then reload this page.</li>
        </ol>
      </div>
      <div class="row"><a class="btn secondary" href="index.html">Back to benchmark</a></div>
    <?php else: ?>
      <p>Log in to publish completed runs to this installation’s leaderboard.</p>
      <?php if ($error): ?><div class="message error"><?php echo benchmark_h($error); ?></div><?php endif; ?>
      <form method="post" action="admin.php">
        <input type="hidden" name="csrf_token" value="<?php echo benchmark_h($csrf); ?>">
        <input type="hidden" name="next" value="<?php echo benchmark_h($next); ?>">
        <label for="password">Administrator password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required autofocus>
        <div class="row"><button class="btn" type="submit">Log in</button><a class="btn secondary" href="index.html">Cancel</a></div>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
