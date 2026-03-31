<?php
$_title = 'Redis';
$_prev  = ['url' => '../explorer/', 'label' => 'Explorer'];
$_next  = null;

$_styleExtra = <<<'CSS'
.redis-log { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem; }
.redis-log .cmd { color: #1976d2; font-weight: 600; }
.redis-log .reply { color: #2e7d32; }
.redis-log .line { padding: 3px 0; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px; }
html.dark .redis-log .cmd { color: #64b5f6; }
html.dark .redis-log .reply { color: #4caf50; }
html.dark .redis-log .line { border-bottom-color: #2a3a5c; }
CSS;

include __DIR__ . '/../_header.php';

/**
 * Tiny RESP client — no phpredis extension needed.
 * RESP is just text over TCP.
 */
class Redis {
    private $sock;

    function __construct(string $host = '127.0.0.1', int $port = 6380) {
        $this->sock = stream_socket_client("tcp://{$host}:{$port}", $errno, $err, 1);
        if (!$this->sock) throw new RuntimeException("Redis connect failed: $err");
    }

    function command(string ...$args): mixed {
        // Encode RESP array
        $cmd = "*" . count($args) . "\r\n";
        foreach ($args as $arg) {
            $cmd .= "$" . strlen($arg) . "\r\n" . $arg . "\r\n";
        }
        fwrite($this->sock, $cmd);
        return $this->read();
    }

    private function read(): mixed {
        $line = trim(fgets($this->sock));
        $type = $line[0];
        $data = substr($line, 1);

        return match($type) {
            '+' => $data,                                    // simple string
            '-' => "ERR: $data",                             // error
            ':' => (int)$data,                               // integer
            '$' => $data == '-1' ? null                      // bulk string
                   : trim(fread($this->sock, (int)$data + 2)),
            '*' => array_map(                                // array
                fn() => $this->read(),
                $data == '-1' ? [] : range(1, (int)$data)
            ),
            default => $line,
        };
    }

    function __destruct() {
        if ($this->sock) fclose($this->sock);
    }
}

// Connect to our own RESP server
$redis = new Redis('127.0.0.1', 6380);
$state = new FrankenPHP\SharedArray();

// Run demo commands and capture log
$log = [];

function redis_do(Redis $redis, array &$log, string ...$args): mixed {
    $cmdStr = implode(' ', $args);
    $result = $redis->command(...$args);
    $display = is_array($result) ? implode(', ', $result) : var_export($result, true);
    $log[] = ['cmd' => $cmdStr, 'reply' => $display];
    return $result;
}

// 1. Write via Redis, read via SharedArray
redis_do($redis, $log, 'SET', 'demo.source', 'redis-cli');
redis_do($redis, $log, 'SET', 'demo.number', '42');
redis_do($redis, $log, 'SET', 'demo.greeting', 'hello from RESP');

$fromSharedArray = $state['demo.greeting'];

// 2. Write via SharedArray, read via Redis
$state['demo.php_says'] = 'hello from PHP';
$fromRedis = redis_do($redis, $log, 'GET', 'demo.php_says');

// 3. Atomic counter via INCR
redis_do($redis, $log, 'DEL', 'demo.counter');
redis_do($redis, $log, 'INCR', 'demo.counter');
redis_do($redis, $log, 'INCR', 'demo.counter');
redis_do($redis, $log, 'INCR', 'demo.counter');
$counterViaState = $state['demo.counter'];

// 4. Bulk ops
redis_do($redis, $log, 'MSET', 'demo.a', '1', 'demo.b', '2', 'demo.c', '3');
redis_do($redis, $log, 'MGET', 'demo.a', 'demo.b', 'demo.c');
redis_do($redis, $log, 'KEYS', 'demo.*');
redis_do($redis, $log, 'DBSIZE');

// 5. Cleanup
redis_do($redis, $log, 'DEL', 'demo.source', 'demo.number', 'demo.greeting', 'demo.php_says', 'demo.counter', 'demo.a', 'demo.b', 'demo.c');
?>

    <div class="intro">
        <h1>Redis Protocol</h1>
        <p>Same shared memory, accessed over the Redis wire protocol (RESP). No phpredis extension &mdash; just raw TCP sockets.</p>
        <div class="intro-badges">
            <span class="badge badge-go">RESP</span>
            <span class="badge badge-version">:6380</span>
        </div>
    </div>

    <div class="container">

        <div class="panel">
            <h2>The Round-Trip</h2>
            <table>
                <tr>
                    <th>Direction</th>
                    <th>Write</th>
                    <th>Read</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td><span class="source-go">Redis</span> &rarr; <span class="source-php">SharedArray</span></td>
                    <td class="val">SET demo.greeting "hello from RESP"</td>
                    <td class="val">$state['demo.greeting']</td>
                    <td class="val"><?= htmlspecialchars(var_export($fromSharedArray, true)) ?></td>
                </tr>
                <tr>
                    <td><span class="source-php">SharedArray</span> &rarr; <span class="source-go">Redis</span></td>
                    <td class="val">$state['demo.php_says'] = 'hello from PHP'</td>
                    <td class="val">GET demo.php_says</td>
                    <td class="val"><?= htmlspecialchars(var_export($fromRedis, true)) ?></td>
                </tr>
                <tr>
                    <td><span class="source-go">Redis INCR</span> &rarr; <span class="source-php">SharedArray</span></td>
                    <td class="val">INCR demo.counter (x3)</td>
                    <td class="val">$state['demo.counter']</td>
                    <td class="val"><?= htmlspecialchars(var_export($counterViaState, true)) ?></td>
                </tr>
            </table>
        </div>

        <div class="panel">
            <h2>Command Log</h2>
            <div class="redis-log">
<?php foreach ($log as $entry): ?>
                <div class="line">
                    <span class="cmd">&gt; <?= htmlspecialchars($entry['cmd']) ?></span>
                    <span class="reply"><?= htmlspecialchars($entry['reply']) ?></span>
                </div>
<?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <h2>Three Doors, One Store</h2>
            <pre><code>// 1. Go writes directly (zero overhead)
state.Set("server.requests", count)

// 2. PHP reads/writes via SharedArray (cached, version-gated)
$state = new FrankenPHP\SharedArray();
$state['theme'] = 'dark';

// 3. Any Redis client talks RESP over TCP
$redis = stream_socket_client('tcp://127.0.0.1:6380');
// ... or: redis-cli -p 6380 SET theme dark</code></pre>
        </div>

        <div class="panel">
            <h2>The RESP Client (40 lines of PHP)</h2>
            <pre><code>class Redis {
    private $sock;

    function __construct(string $host, int $port) {
        $this->sock = stream_socket_client("tcp://{$host}:{$port}");
    }

    function command(string ...$args): mixed {
        $cmd = "*" . count($args) . "\r\n";
        foreach ($args as $arg) {
            $cmd .= "$" . strlen($arg) . "\r\n" . $arg . "\r\n";
        }
        fwrite($this->sock, $cmd);
        return $this->read();
    }
}</code></pre>
            <p style="color: #888; font-size: 0.85rem; margin-top: 8px;">
                No phpredis extension. RESP is just text over TCP &mdash; <code>*3\r\n$3\r\nSET\r\n$5\r\ntheme\r\n$4\r\ndark\r\n</code>
            </p>
        </div>

    </div>

<?php include __DIR__ . '/../_footer.php'; ?>
