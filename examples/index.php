<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){var t=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme:dark)').matches;if(t==='dark'||(!t&&d))document.documentElement.classList.add('dark')})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FrankenState Demos</title>
    <link rel="stylesheet" href="/style.php">
    <style>
        body { padding: 40px 20px; }
        .header {
            max-width: 900px;
            margin: 0 auto 40px;
            text-align: center;
            position: relative;
        }
        .header h1 { font-size: 2.5rem; color: #1a1a2e; margin-bottom: 8px; }
        .header p { font-size: 1.1rem; color: #666; }
        .header .theme-toggle { position: absolute; top: 0; right: 0; }
        .grid {
            max-width: 900px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .card-accent { height: 6px; }
        .card-body { padding: 24px; }
        .card-body h2 { font-size: 1.3rem; margin-bottom: 8px; color: #1a1a2e; }
        .card-body p { font-size: 0.95rem; color: #666; margin-bottom: 16px; }
        .card-tags { display: flex; gap: 8px; flex-wrap: wrap; }
        .tag {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tag-go { background: #e0f2f1; color: #00695c; }
        .tag-php { background: #ede7f6; color: #4527a0; }
        .tag-state { background: #e8f5e9; color: #2e7d32; }
        html.dark .header h1 { color: #e0e0e0; }
        html.dark .header p { color: #aaa; }
        html.dark .card { background: #16213e; box-shadow: 0 2px 12px rgba(0,0,0,0.3); }
        html.dark .card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
        html.dark .card-body h2 { color: #e0e0e0; }
        html.dark .card-body p { color: #aaa; }
        html.dark .tag-go { background: #1a332e; color: #4db6ac; }
        html.dark .tag-php { background: #1f1a33; color: #b39ddb; }
        html.dark .tag-state { background: #1a332e; color: #4caf50; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FrankenState</h1>
        <p>Shared state between Go and PHP &mdash; same process, same memory, Redis protocol</p>
        <button class="theme-toggle" onclick="document.documentElement.classList.toggle('dark');localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light')" aria-label="Toggle theme">&#x25D1;</button>
    </div>

    <details class="explainer">
        <summary>How it works</summary>
        <div class="explainer-body">
            <p>FrankenState is a <strong>shared key-value store</strong> that lives inside the FrankenPHP process.
               Go extensions write to it directly, PHP accesses it via <code>ArrayAccess</code>. Both sides see the same data, zero network overhead.</p>
            <div class="explainer-stack">
                <span>PHP</span> <span class="arrow">&rarr;</span>
                <span>Zend Extension</span> <span class="arrow">&rarr;</span>
                <span>CGo Bridge</span> <span class="arrow">&rarr;</span>
                <span>Go RWMutex + map</span>
            </div>
            <p>Reads are cached per-object with version tracking &mdash; if the Go version counter hasn't changed, PHP serves from its local cache with <strong>zero CGo crossings</strong>.
               Writes use type-specific setters for scalars (no JSON overhead for <code>int</code>, <code>float</code>, <code>bool</code>, <code>string</code>).</p>
            <p class="explainer-sub">Part of the franken* family:
               <a href="https://github.com/johanjanssens/frankenasync">async</a>,
               <a href="https://github.com/johanjanssens/frankenwasm">wasm</a>,
               <a href="https://github.com/johanjanssens/frankenonnx">onnx</a>.</p>
        </div>
    </details>

    <div class="grid">
        <a href="dashboard/" class="card">
            <div class="card-accent" style="background: linear-gradient(90deg, #00ADD8, #10b981)"></div>
            <div class="card-body">
                <h2>Dashboard</h2>
                <p>Live server metrics pushed from Go at request time &mdash; request count, uptime, PID. Refresh to see the numbers update.</p>
                <div class="card-tags">
                    <span class="tag tag-go">Go &rarr; PHP</span>
                    <span class="tag tag-state">Live Metrics</span>
                </div>
            </div>
        </a>

        <a href="explorer/" class="card">
            <div class="card-accent" style="background: linear-gradient(90deg, #7c3aed, #ec4899)"></div>
            <div class="card-body">
                <h2>Explorer</h2>
                <p>Add, edit, and delete key-value pairs. Values persist across requests &mdash; no Redis, no database, same process memory.</p>
                <div class="card-tags">
                    <span class="tag tag-php">PHP &harr; Go</span>
                    <span class="tag tag-state">CRUD</span>
                </div>
            </div>
        </a>

        <a href="redis/" class="card">
            <div class="card-accent" style="background: linear-gradient(90deg, #ef4444, #f59e0b)"></div>
            <div class="card-body">
                <h2>Redis Protocol</h2>
                <p>Talk to the same shared memory over the Redis wire protocol. Raw TCP sockets, no phpredis extension needed.</p>
                <div class="card-tags">
                    <span class="tag tag-go">RESP</span>
                    <span class="tag tag-state">Port 6380</span>
                </div>
            </div>
        </a>
    </div>

    <footer>hack'd by <a href="https://bsky.app/profile/johanjanssens.bsky.social" target="_blank">Johan Janssens</a></footer>
</body>
</html>
