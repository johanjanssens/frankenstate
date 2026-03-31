<?php
$_title = 'Dashboard';
$_prev  = null;
$_next  = ['url' => '../explorer/', 'label' => 'Explorer'];
include __DIR__ . '/../_header.php';

$state = new FrankenPHP\SharedArray();

// Format uptime
$uptime = (int)($state['server.uptime_seconds'] ?? 0);
$uptimeStr = sprintf('%dh %dm %ds', intdiv($uptime, 3600), intdiv($uptime % 3600, 60), $uptime % 60);
?>

    <div class="intro">
        <h1>Dashboard</h1>
        <p>Live metrics pushed from Go on every request. Refresh the page &mdash; watch the numbers change.</p>
        <div class="intro-badges">
            <span class="badge badge-go">Go &rarr; PHP</span>
            <span class="badge badge-version">v<?= $state->version() ?></span>
        </div>
    </div>

    <div class="container">

        <div class="panel">
            <h2>Server Metrics (from Go)</h2>
            <div class="metrics">
                <div class="metric">
                    <div class="metric-value"><?= number_format((int)($state['server.requests'] ?? 0)) ?></div>
                    <div class="metric-label">Requests</div>
                </div>
                <div class="metric">
                    <div class="metric-value"><?= $uptimeStr ?></div>
                    <div class="metric-label">Uptime</div>
                </div>
                <div class="metric">
                    <div class="metric-value"><?= (int)($state['server.pid'] ?? 0) ?></div>
                    <div class="metric-label">PID</div>
                </div>
                <div class="metric">
                    <div class="metric-value"><?= (int)($state['server.threads'] ?? 0) ?></div>
                    <div class="metric-label">Threads</div>
                </div>
            </div>
            <p style="text-align: center; color: #888; font-size: 0.85rem;">
                Started <?= htmlspecialchars($state['server.started_at'] ?? '?') ?>
                &middot; Last request <?= htmlspecialchars($state['server.last_request'] ?? '?') ?>
            </p>
        </div>

        <div class="panel">
            <h2>Full State Snapshot</h2>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
<?php
$snapshot = $state->snapshot();
ksort($snapshot);
foreach ($snapshot as $key => $value):
    $isGo = str_starts_with($key, 'server.');
    $displayValue = is_array($value) ? json_encode($value) : var_export($value, true);
?>
                    <tr>
                        <td class="key"><?= htmlspecialchars($key) ?></td>
                        <td class="val"><?= htmlspecialchars($displayValue) ?></td>
                        <td class="source">
                            <span class="<?= $isGo ? 'source-go' : 'source-php' ?>"><?= $isGo ? 'Go' : 'PHP' ?></span>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
<?php if (empty($snapshot)): ?>
            <p class="empty">No state yet.</p>
<?php endif; ?>
        </div>

        <div class="panel">
            <h2>How it works</h2>
            <pre><code>// main.go — pushed on every HTTP request
state.Set("server.requests", count)
state.Set("server.uptime_seconds", int(time.Since(startTime).Seconds()))
state.Set("server.last_request", time.Now().Format(time.RFC3339))</code></pre>
            <pre style="margin-top: 12px;"><code>// PHP — reads from cached snapshot (zero CGo if version unchanged)
$state = new FrankenPHP\SharedArray();
$requests = $state['server.requests'];  // set by Go
$uptime   = $state['server.uptime_seconds'];</code></pre>
        </div>

    </div>

<?php include __DIR__ . '/../_footer.php'; ?>
