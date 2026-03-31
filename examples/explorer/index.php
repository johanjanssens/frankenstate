<?php
$_title = 'Explorer';
$_prev  = ['url' => '../dashboard/', 'label' => 'Dashboard'];
$_next  = ['url' => '../redis/', 'label' => 'Redis'];
include __DIR__ . '/../_header.php';

$state = new FrankenPHP\SharedArray();

// Handle form actions
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set' && !empty($_POST['key'])) {
        $key = trim($_POST['key']);
        $val = $_POST['value'] ?? '';

        // Try to decode JSON, fall back to string
        $decoded = json_decode($val, true);
        $state[$key] = (json_last_error() === JSON_ERROR_NONE && $decoded !== null) ? $decoded : $val;

        $message = "Set <code>{$key}</code>";
    }

    if ($action === 'delete' && !empty($_POST['key'])) {
        $key = $_POST['key'];
        unset($state[$key]);
        $message = "Deleted <code>{$key}</code>";
    }

    if ($action === 'clear_php') {
        // Only clear non-server keys
        foreach ($state->keys() as $key) {
            if (!str_starts_with($key, 'server.')) {
                unset($state[$key]);
            }
        }
        $message = "Cleared all PHP keys";
    }
}
?>

    <div class="intro">
        <h1>Explorer</h1>
        <p>Add, edit, and delete key-value pairs. Values persist across page loads &mdash; no database, same process memory.</p>
        <div class="intro-badges">
            <span class="badge badge-php">PHP &harr; Go</span>
            <span class="badge badge-version">v<?= $state->version() ?></span>
        </div>
    </div>

    <div class="container">

<?php if ($message): ?>
        <div class="panel" style="padding: 12px 20px; margin-bottom: 16px; border-left: 4px solid #10b981;">
            <p style="margin: 0; font-size: 0.9rem;"><?= $message ?> &middot; version <?= $state->version() ?></p>
        </div>
<?php endif; ?>

        <div class="panel">
            <h2>Add / Update</h2>
            <form method="POST">
                <input type="hidden" name="action" value="set">
                <div class="form-row">
                    <input type="text" name="key" placeholder="key (e.g. theme)" required>
                    <input type="text" name="value" placeholder='value (e.g. dark or {"nested": true})'>
                    <button type="submit" class="btn btn-primary">Set</button>
                </div>
            </form>
            <p style="color: #888; font-size: 0.8rem; margin-top: 4px;">
                Values are stored as strings by default. JSON objects/arrays are auto-decoded.
            </p>
        </div>

        <div class="panel">
            <h2>State (<?= count($state) ?> keys)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Source</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
<?php
$snapshot = $state->snapshot();
ksort($snapshot);
foreach ($snapshot as $key => $value):
    $isGo = str_starts_with($key, 'server.');
    $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : var_export($value, true);
?>
                    <tr>
                        <td class="key"><?= htmlspecialchars($key) ?></td>
                        <td class="val"><?= htmlspecialchars($displayValue) ?></td>
                        <td class="source">
                            <span class="<?= $isGo ? 'source-go' : 'source-php' ?>"><?= $isGo ? 'Go' : 'PHP' ?></span>
                        </td>
                        <td>
<?php if (!$isGo): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
<?php endif; ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
<?php if (empty($snapshot)): ?>
            <p class="empty">No state yet. Add a key above.</p>
<?php endif; ?>
        </div>

<?php
$phpKeys = array_filter($state->keys(), fn($k) => !str_starts_with($k, 'server.'));
if (!empty($phpKeys)):
?>
        <div style="text-align: center; margin-bottom: 24px;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="clear_php">
                <button type="submit" class="btn btn-danger">Clear PHP keys</button>
            </form>
        </div>
<?php endif; ?>

        <div class="panel">
            <h2>Try it</h2>
            <pre><code>$state = new FrankenPHP\SharedArray();

// Set from PHP — Go sees it immediately
$state['theme'] = 'dark';
$state['config'] = ['debug' => true, 'version' => '1.0'];

// Read what Go pushed
$requests = $state['server.requests'];  // incremented by Go on each request
$uptime   = $state['server.uptime_seconds'];

// Iterate everything
foreach ($state as $key => $value) {
    echo "$key = $value\n";
}</code></pre>
        </div>

    </div>

<?php include __DIR__ . '/../_footer.php'; ?>
