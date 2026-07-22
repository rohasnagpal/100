<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
benchmark_start_session();

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function number_or_zero($value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

function format_cost($value): string
{
    $cost = number_or_zero($value);
    if ($cost <= 0) {
        return '$0.0000';
    }
    if ($cost < 0.0001) {
        return '<$0.0001';
    }
    return '$' . number_format($cost, 4);
}

function format_date($value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 'Unknown date' : date('j M Y, H:i', $timestamp);
}

$domainLabels = array(
    'criminal_bns' => 'Criminal law (BNS)',
    'procedure_bnss' => 'Criminal procedure (BNSS)',
    'evidence_bsa' => 'Evidence (BSA)',
    'contract' => 'Contract law',
    'constitutional' => 'Constitutional law',
    'consumer' => 'Consumer protection',
    'it_cyber' => 'IT Act & cybercrime',
    'property_tenancy' => 'Property & tenancy',
    'family' => 'Family law',
    'labour' => 'Labour & employment',
    'company' => 'Company law',
    'tax_gst' => 'Tax / GST',
    'pmla_crypto_fema' => 'PMLA / crypto / FEMA',
);

$runs = array();
$errors = array();
$files = glob(__DIR__ . '/results/*.json') ?: array();

foreach ($files as $path) {
    if (!is_file($path) || filesize($path) > 2_500_000) {
        continue;
    }
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data) || ($data['benchmark']['id'] ?? '') !== 'indian-law-100' || ($data['status'] ?? '') !== 'finalised') {
        $errors[] = basename($path);
        continue;
    }

    $modelId = (string) ($data['model']['id'] ?? 'unknown-model');
    $summary = is_array($data['summary'] ?? null) ? $data['summary'] : array();
    $finalisedAt = (string) ($data['finalised_at'] ?? $data['completed_at'] ?? '');
    $runs[] = array(
        'filename' => basename($path),
        'model_id' => $modelId,
        'model_name' => (string) ($data['model']['name'] ?? $modelId),
        'judge_model' => (string) ($data['judge_model'] ?? ''),
        'percentage' => max(0, min(100, number_or_zero($summary['percentage'] ?? 0))),
        'points' => number_or_zero($summary['points'] ?? 0),
        'max' => number_or_zero($summary['max'] ?? 0),
        'correct' => (int) ($summary['correct'] ?? 0),
        'partial' => (int) ($summary['partial'] ?? 0),
        'incorrect' => (int) ($summary['incorrect'] ?? 0),
        'graded' => (int) ($summary['graded'] ?? 0),
        'trap_correct' => (int) ($summary['trap_correct'] ?? 0),
        'trap_total' => (int) ($summary['trap_total'] ?? 0),
        'cost' => number_or_zero($summary['cost'] ?? 0),
        'runtime_ms' => number_or_zero($summary['runtime_ms'] ?? 0),
        'by_domain' => is_array($summary['by_domain'] ?? null) ? $summary['by_domain'] : array(),
        'by_difficulty' => is_array($summary['by_difficulty'] ?? null) ? $summary['by_difficulty'] : array(),
        'finalised_at' => $finalisedAt,
        'timestamp' => strtotime($finalisedAt) ?: 0,
        'version' => (string) ($data['benchmark']['version'] ?? ''),
    );
}

usort($runs, static function (array $a, array $b): int {
    return $b['timestamp'] <=> $a['timestamp'];
});

$latest = array();
foreach ($runs as $run) {
    if (!isset($latest[$run['model_id']])) {
        $latest[$run['model_id']] = $run;
    }
}
$models = array_values($latest);

usort($models, static function (array $a, array $b): int {
    if ($a['percentage'] !== $b['percentage']) {
        return $b['percentage'] <=> $a['percentage'];
    }
    if ($a['cost'] !== $b['cost']) {
        return $a['cost'] <=> $b['cost'];
    }
    return strcmp($a['model_name'], $b['model_name']);
});

$aggregateDomains = array();
foreach ($models as $model) {
    foreach ($model['by_domain'] as $domain => $score) {
        if (!isset($aggregateDomains[$domain])) {
            $aggregateDomains[$domain] = array('points' => 0.0, 'max' => 0.0, 'models' => 0);
        }
        $aggregateDomains[$domain]['points'] += number_or_zero($score['points'] ?? 0);
        $aggregateDomains[$domain]['max'] += number_or_zero($score['max'] ?? 0);
        $aggregateDomains[$domain]['models']++;
    }
}
foreach ($aggregateDomains as $domain => $score) {
    $aggregateDomains[$domain]['percentage'] = $score['max'] > 0 ? ($score['points'] / $score['max']) * 100 : 0;
}
uasort($aggregateDomains, static function (array $a, array $b): int {
    return $b['percentage'] <=> $a['percentage'];
});

$topScore = $models ? $models[0]['percentage'] : 0;
$average = $models ? array_sum(array_column($models, 'percentage')) / count($models) : 0;
$averageTrap = 0;
$trapModels = 0;
foreach ($models as $model) {
    if ($model['trap_total'] > 0) {
        $averageTrap += ($model['trap_correct'] / $model['trap_total']) * 100;
        $trapModels++;
    }
}
$averageTrap = $trapModels ? $averageTrap / $trapModels : 0;
$adminConfigured = benchmark_admin_configured();
$adminLoggedIn = benchmark_is_admin();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Leaderboard for the Indian Law 100 legal AI micro-case benchmark.">
  <title>Leaderboard · Indian Law 100</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#f7f5ef;--paper:#fffefa;--ink:#201e1b;--muted:#696258;--faint:#91897d;--line:#e6dfd3;--line-strong:#d4cab9;--accent:#c76443;--accent-deep:#994426;--accent-soft:#f7e5de;--dark:#25221e;--green:#557d45;--green-soft:#e8f1e4;--amber:#a67418;--amber-soft:#f8edd3;--red:#a94535;--red-soft:#f7e2de;--serif:'Lora',Georgia,serif;--sans:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;--shadow:0 1px 2px rgba(55,45,30,.04),0 12px 34px rgba(55,45,30,.06)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.55 var(--sans);-webkit-font-smoothing:antialiased}a{color:inherit}.shell{width:min(1120px,calc(100% - 40px));margin:auto}.topbar{height:64px;border-bottom:1px solid var(--line);background:rgba(247,245,239,.94)}.topbar .shell{height:100%;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:11px;text-decoration:none;font-weight:700}.mark{width:35px;height:35px;border-radius:9px;background:var(--dark);color:#fff;display:grid;place-items:center;font:600 17px var(--serif)}.nav{display:flex;gap:6px}.nav a{padding:8px 10px;border-radius:8px;text-decoration:none;color:var(--muted);font-size:13px}.nav a:hover{background:var(--paper);color:var(--ink)}
    main{padding-bottom:90px}.hero{text-align:center;padding:58px 0 24px}.wordmark{display:flex;justify-content:center;gap:8px;margin-bottom:19px}.wordmark span{width:48px;height:56px;border:1.5px solid var(--line-strong);border-radius:10px;background:var(--paper);display:grid;place-items:center;font:600 25px var(--serif)}.wordmark span:nth-child(2){background:var(--accent);border-color:var(--accent);color:#fff;transform:translateY(-4px)}.eyebrow{font-size:10.5px;color:var(--accent-deep);font-weight:700;letter-spacing:.14em;text-transform:uppercase}.hero h1{font:500 clamp(28px,4vw,42px)/1.15 var(--serif);margin:10px 0 8px}.hero h1 em{color:var(--accent)}.hero p{max-width:650px;margin:auto;color:var(--muted)}
    .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-top:16px}.stat{background:var(--paper);border:1px solid var(--line);border-radius:13px;padding:17px;box-shadow:var(--shadow)}.stat span{display:block;color:var(--faint);font-size:9.5px;text-transform:uppercase;letter-spacing:.07em}.stat b{display:block;font:600 29px var(--serif);margin-top:3px}.stat:first-child{background:var(--dark);border-color:var(--dark);color:#fff}.stat:first-child span{color:#c9c1b6}.stat:first-child b{color:#f3b39b}
    .card{background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:26px 28px;margin-top:20px;box-shadow:var(--shadow)}.card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.card h2{font:600 20px var(--serif);margin:0}.hint{color:var(--muted);font-size:12.5px;margin:5px 0 0}.badge{border:1px solid var(--line);border-radius:999px;background:var(--bg);padding:5px 10px;color:var(--muted);font-size:10.5px;white-space:nowrap}
    .leader-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:13px;margin-top:20px}.model-card{position:relative;border:1px solid var(--line);border-radius:13px;padding:18px;background:#fbfaf6;min-width:0}.model-card.winner{background:#fff;border-color:var(--accent);box-shadow:var(--shadow)}.rank{position:absolute;right:16px;top:16px;color:var(--faint);font:600 14px var(--serif)}.winner .rank{color:var(--accent-deep)}.model-name{font-weight:700;padding-right:45px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.model-id{font-size:10.5px;color:var(--faint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.winner-tag{display:inline-block;margin-top:6px;color:var(--accent-deep);background:var(--accent-soft);border-radius:999px;padding:3px 8px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
    .score-visual{display:grid;grid-template-columns:92px 1fr;align-items:center;gap:17px;margin-top:15px}.donut{--score:0;width:88px;height:88px;border-radius:50%;background:conic-gradient(var(--accent) calc(var(--score)*1%),var(--line) 0);display:grid;place-items:center;position:relative}.donut::before{content:'';position:absolute;inset:9px;border-radius:50%;background:#fbfaf6}.winner .donut::before{background:#fff}.donut b{position:relative;font:600 20px var(--serif)}.donut b small{font:500 9px var(--sans);color:var(--faint)}.grade-counts{display:grid;gap:6px;font-size:10.5px;color:var(--muted)}.grade-counts span{display:flex;align-items:center;gap:7px}.swatch{width:7px;height:7px;border-radius:2px}.swatch.correct{background:var(--green)}.swatch.partial{background:var(--amber)}.swatch.incorrect{background:var(--red)}
    .domain-bars{display:grid;gap:7px;border-top:1px solid var(--line);margin-top:15px;padding-top:13px}.domain-row{display:grid;grid-template-columns:minmax(85px,1fr) 1.3fr 31px;gap:8px;align-items:center;font-size:9.5px;color:var(--muted)}.domain-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.track{height:6px;background:var(--line);border-radius:999px;overflow:hidden}.fill{height:100%;background:linear-gradient(90deg,var(--accent),#df9478);border-radius:999px}.domain-value{text-align:right}.model-meta{display:flex;gap:11px;flex-wrap:wrap;margin-top:12px;color:var(--faint);font-size:9.5px}.view-json{display:inline-block;margin-top:11px;color:var(--accent-deep);font-size:10.5px;font-weight:650;text-decoration:none}.view-json:hover{text-decoration:underline}
    .domain-overview{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-top:18px}.domain-overview .domain-item{border:1px solid var(--line);border-radius:10px;padding:12px}.domain-item-head{display:flex;justify-content:space-between;gap:15px;margin-bottom:8px;font-size:11px}.domain-item-head b{font-size:12px}.domain-item .track{height:8px}.empty{text-align:center;padding:46px 20px;color:var(--muted)}.empty b{display:block;font:600 20px var(--serif);color:var(--ink);margin-bottom:6px}.btn{display:inline-block;margin-top:16px;background:var(--accent);color:#fff;border-radius:9px;padding:10px 15px;text-decoration:none;font-weight:650;font-size:12px}.notice{margin-top:18px;border:1px solid #e3c787;background:var(--amber-soft);color:#745514;border-radius:10px;padding:10px 13px;font-size:11.5px}.errors{color:var(--red);font-size:10px;margin-top:10px}
    footer{text-align:center;color:var(--faint);font-size:11px;padding:45px 0}.table-wrap{overflow:auto;margin-top:18px}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid var(--line);padding:10px 11px;white-space:nowrap;font-size:11.5px}th{color:var(--faint);font-size:9px;text-transform:uppercase;letter-spacing:.06em;background:#f3f0e9}
    @media(max-width:760px){.stats{grid-template-columns:1fr 1fr}.domain-overview{grid-template-columns:1fr}.leader-grid{grid-template-columns:1fr}}@media(max-width:520px){.shell{width:min(100% - 24px,1120px)}.nav a:first-child{display:none}.card{padding:20px 16px}.stats{gap:8px}.stat{padding:13px}.stat b{font-size:24px}.score-visual{grid-template-columns:80px 1fr}.donut{width:78px;height:78px}.domain-row{grid-template-columns:90px 1fr 28px}}
  </style>
</head>
<body>
  <header class="topbar"><div class="shell"><a class="brand" href="index.html"><span class="mark">IL</span><span>Indian Law 100</span></a><nav class="nav"><a href="index.html">Run benchmark</a><a href="admin.php?next=leaderboard.php"><?php echo $adminLoggedIn ? 'Admin ✓' : 'Admin'; ?></a></nav></div></header>
  <main><div class="shell">
    <section class="hero"><div class="wordmark" aria-hidden="true"><span>1</span><span>0</span><span>0</span></div><div class="eyebrow">Latest finalised run per model</div><h1>Legal AI <em>Leaderboard</em></h1><p>Ranked by total rubric score. Domain, difficulty, trap performance, cost, and the original auditable JSON remain visible.</p></section>

    <section class="stats" aria-label="Leaderboard summary">
      <div class="stat"><span>Top score</span><b><?php echo h(number_format($topScore, 1)); ?>%</b></div>
      <div class="stat"><span>Models ranked</span><b><?php echo count($models); ?></b></div>
      <div class="stat"><span>Average score</span><b><?php echo h(number_format($average, 1)); ?>%</b></div>
      <div class="stat"><span>Average trap accuracy</span><b><?php echo h(number_format($averageTrap, 0)); ?>%</b></div>
    </section>

    <?php if (!$adminConfigured): ?>
      <div class="notice"><strong>Setup required:</strong> configure an administrator password before this installation can publish benchmark results. See <code>config.example.php</code> and the README.</div>
    <?php elseif ($adminLoggedIn): ?>
      <div class="notice"><strong>Administrator signed in:</strong> completed runs can be saved to this installation’s leaderboard.</div>
    <?php else: ?>
      <div class="notice"><strong>Independent leaderboard:</strong> results shown here were published by this server’s administrator. Anyone may run the benchmark and download an auditable JSON copy.</div>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><div><h2>Model ranking</h2><p class="hint">Only each model’s most recently finalised run is ranked. Ties favour lower estimated cost.</p></div><span class="badge"><?php echo count($runs); ?> finalised run<?php echo count($runs) === 1 ? '' : 's'; ?></span></div>
      <?php if (!$models): ?>
        <div class="empty"><b>No finalised scores yet</b>Run the 100-case benchmark, review all grades, and finalise it while logged in as this server’s administrator.<br><a class="btn" href="index.html">Run the benchmark</a></div>
      <?php else: ?>
        <div class="leader-grid">
          <?php foreach ($models as $index => $model): ?>
            <article class="model-card <?php echo $index === 0 ? 'winner' : ''; ?>">
              <span class="rank">#<?php echo $index + 1; ?></span>
              <div class="model-name" title="<?php echo h($model['model_name']); ?>"><?php echo h($model['model_name']); ?></div>
              <div class="model-id" title="<?php echo h($model['model_id']); ?>"><?php echo h($model['model_id']); ?></div>
              <?php if ($index === 0): ?><span class="winner-tag">Current leader</span><?php endif; ?>
              <div class="score-visual">
                <div class="donut" style="--score:<?php echo h($model['percentage']); ?>"><b><?php echo h(number_format($model['percentage'], 1)); ?><small>%</small></b></div>
                <div class="grade-counts">
                  <span><i class="swatch correct"></i><b><?php echo $model['correct']; ?></b> correct</span>
                  <span><i class="swatch partial"></i><b><?php echo $model['partial']; ?></b> partial</span>
                  <span><i class="swatch incorrect"></i><b><?php echo $model['incorrect']; ?></b> incorrect</span>
                  <span><b><?php echo $model['trap_correct']; ?>/<?php echo $model['trap_total']; ?></b> traps correct</span>
                </div>
              </div>
              <div class="domain-bars">
                <?php foreach ($model['by_domain'] as $domain => $score): $pct = number_or_zero($score['max'] ?? 0) > 0 ? number_or_zero($score['points'] ?? 0) / number_or_zero($score['max']) * 100 : 0; ?>
                  <div class="domain-row"><span class="domain-name" title="<?php echo h($domainLabels[$domain] ?? $domain); ?>"><?php echo h($domainLabels[$domain] ?? $domain); ?></span><span class="track"><span class="fill" style="width:<?php echo h($pct); ?>%"></span></span><span class="domain-value"><?php echo h(number_format($pct, 0)); ?>%</span></div>
                <?php endforeach; ?>
              </div>
              <div class="model-meta"><span><?php echo h(number_format($model['points'], 0)); ?>/<?php echo h(number_format($model['max'], 0)); ?> points</span><span><?php echo h(format_cost($model['cost'])); ?></span><span><?php echo h(format_date($model['finalised_at'])); ?></span></div>
              <a class="view-json" href="results/<?php echo rawurlencode($model['filename']); ?>" target="_blank" rel="noopener">Open auditable JSON →</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($errors): ?><p class="errors"><?php echo count($errors); ?> invalid result file<?php echo count($errors) === 1 ? ' was' : 's were'; ?> ignored.</p><?php endif; ?>
    </section>

    <?php if ($aggregateDomains): ?>
      <section class="card"><div class="card-head"><div><h2>Performance by legal domain</h2><p class="hint">Average points across the latest run for every ranked model.</p></div></div><div class="domain-overview">
        <?php foreach ($aggregateDomains as $domain => $score): ?><div class="domain-item"><div class="domain-item-head"><span><?php echo h($domainLabels[$domain] ?? $domain); ?></span><b><?php echo h(number_format($score['percentage'], 1)); ?>%</b></div><div class="track"><div class="fill" style="width:<?php echo h($score['percentage']); ?>%"></div></div></div><?php endforeach; ?>
      </div></section>
    <?php endif; ?>

    <?php if (count($runs) > count($models)): ?>
      <section class="card"><div class="card-head"><div><h2>Run archive</h2><p class="hint">All finalised runs, including earlier attempts for models already ranked above.</p></div></div><div class="table-wrap"><table><thead><tr><th>Model</th><th>Score</th><th>Cases</th><th>Judge</th><th>Finalised</th><th>Data</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><?php echo h($run['model_name']); ?></td><td><?php echo h(number_format($run['percentage'], 1)); ?>%</td><td><?php echo $run['graded']; ?></td><td><?php echo h($run['judge_model']); ?></td><td><?php echo h(format_date($run['finalised_at'])); ?></td><td><a href="results/<?php echo rawurlencode($run['filename']); ?>" target="_blank" rel="noopener">JSON</a></td></tr><?php endforeach; ?></tbody></table></div></section>
    <?php endif; ?>
  </div></main>
  <footer>Indian Law 100 · Gold answers require expert review and a stated law-as-of date.</footer>
</body>
</html>
