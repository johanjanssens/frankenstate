# Shared State Between Go and PHP

> Base narrative for FrankenPHP conference talks

## Abstract

What if PHP and Go could share memory — no Redis, no serialization, no network hop?

With FrankenState and FrankenPHP, this is possible. A Go extension writes to a shared map. PHP
reads it via `ArrayAccess`. Same process, same memory. Reads that hit the cache cost zero CGo
crossings. Writes to scalars skip JSON entirely. The version counter tells PHP when to refresh —
if nothing changed, it's a local array lookup.

This talk shows how to build a bidirectional Go ↔ PHP communication channel inside FrankenPHP,
why in-process state changes the architecture of PHP applications, and how shared memory eliminates
an entire class of infrastructure.

## The Problem

PHP workers can't share state natively. Every request starts fresh. When you need shared data —
configuration, feature flags, cached lookups, model status — you reach for external stores:

- **Redis/Memcached** — network round-trip on every read (0.5–2 ms)
- **APCu** — designed for prefork (shared memory), not threaded FrankenPHP. No Go access.
- **Database** — 5–50 ms per query for config data
- **Environment variables** — static, no runtime updates
- **Files** — I/O overhead, no atomicity, stale reads

For FrankenPHP, there's an additional gap: Go extensions (WASM plugins, ONNX models, async tasks)
have no way to push data into PHP's runtime. The Go side knows things — model status, connection
pools, metrics — but PHP can't see any of it without an external store in between.

What if the Go process and PHP shared the same memory?

## The Key Insight

FrankenPHP embeds PHP in a Go process. They're already in the same address space. A Go
`map[string]any` protected by `sync.RWMutex` is all you need — no external infrastructure, no
serialization on the Go side, no network hop.

The trick is making it fast on the PHP side. Every CGo crossing has overhead (~200 ns). JSON
encoding has overhead. So instead of crossing on every `$state['key']` read, we cache the entire
snapshot on the PHP object and gate refreshes on an atomic version counter:

```
PHP: $state['key']
  → Is cached_version == go_state_version()?
    → YES: return from local zval cache (zero CGo)
    → NO:  fetch go_state_snapshot(), decode JSON, update cache
```

One CGo call to check the version (cheap). Zero CGo calls to read cached data. The cache
auto-invalidates when any thread writes.

```
PHP: $state['key'] = 'value'
  → C extension: type dispatch
    → int/float/bool/string/null: go_state_set_int/float/... (no JSON)
    → array/object: json_encode → go_state_set(key, json)
  → Force refresh cache
```

Writes use type-specific setters for scalars — a PHP integer goes straight to Go's `int64`
without touching JSON. Only complex types (arrays, objects) need JSON encoding.

## What This Means

- **In-process** — state lives inside the PHP process. No external infrastructure.
- **Bidirectional** — Go writes, PHP reads. PHP writes, Go reads. Same store.
- **Fast reads** — cached snapshot with version gating. Zero CGo on cache hit.
- **Fast writes** — type-specific setters skip JSON for scalars.
- **Thread-safe** — `sync.RWMutex` + atomic version counter. Multiple readers, single writer.
- **Cross-request** — state survives across PHP requests. No session, no database.
- **Simple API** — `$state['key'] = val` on the PHP side. `state.Set(k, v)` on the Go side.

## Demo Walkthrough

### Dashboard — Go Pushes Live Metrics

The Go HTTP handler pushes server metrics into state on every request:

```go
state.Set("server.requests", count)
state.Set("server.uptime_seconds", int(time.Since(startTime).Seconds()))
state.Set("server.last_request", time.Now().Format(time.RFC3339))
```

The PHP dashboard reads these:

```php
$state = new FrankenPHP\SharedArray();
$requests = $state['server.requests'];   // set by Go
$uptime   = $state['server.uptime_seconds'];
```

Refresh the page — the request counter goes up, the uptime updates. Go is pushing data that PHP
sees immediately, with no Redis, no API call, no message queue.

### Explorer — PHP CRUD with Cross-Request Persistence

A form lets you add key-value pairs:

```php
$state['theme'] = 'dark';
$state['config'] = ['debug' => true, 'version' => '1.0'];
```

Refresh the page — the values are still there. Navigate away and come back — still there. They
live in Go memory for the lifetime of the process. No session, no database, no Redis.

The explorer shows every key with a "Go" or "PHP" badge indicating who set it. Go-seeded keys
(`server.*`) are read-only from PHP's perspective. PHP keys can be edited and deleted.

## Architecture

The system has three layers:

```
┌─────────────────────────────────────────────────────┐
│  PHP                                                │
│  $state = new SharedArray()                         │
│  $state['key'] = val    // ArrayAccess              │
│  foreach ($state ...)   // IteratorAggregate        │
│  count($state)          // Countable                │
└──────────────────────┬──────────────────────────────┘
                       │ CGo (version-gated cache)
┌──────────────────────▼──────────────────────────────┐
│  Zend C Extension (phpext/)                         │
│  Per-object cached snapshot + version tracking      │
│  Type-specific setters (int/float/bool/string/null) │
│  JSON fallback for complex types                    │
└──────────────────────┬──────────────────────────────┘
                       │ direct Go calls
┌──────────────────────▼──────────────────────────────┐
│  Go State Store (state/)                            │
│  sync.RWMutex + map[string]any                      │
│  Atomic version counter                             │
│  Importable by any Go extension                     │
└─────────────────────────────────────────────────────┘
```

The store is a process-lifetime singleton. Any Go code in the same binary can import the `state`
package and call `state.Set()` / `state.Get()` directly — no CGo, no JSON, no overhead. The CGo
bridge only exists for the PHP side.

### The Composability Argument

FrankenPHP's [PR #2287](https://github.com/php/frankenphp/pull/2287) proposes "background
workers/sidekicks" — a monolithic subsystem that couples three concerns:

1. Background execution (new thread types, Caddyfile directives)
2. Shared state (`pemalloc`, `set_vars`/`get_vars`)
3. Event signaling (pipe fd streams, PHP async loop integration)

FrankenState and [FrankenAsync](https://github.com/johanjanssens/frankenasync) separate these
into two composable primitives:

```
PR #2287:  Redis → sidekick thread → pemalloc → HTTP workers
           (new thread type, Caddyfile config, signaling streams)

Composable: Redis → any thread (via async) → SharedArray → any thread (via read)
            (no new infrastructure, two independent primitives)
```

No `pemalloc`, no signaling streams, no new thread types, no Caddyfile directives.

## Use Cases

### Go Extension → PHP Communication

Any franken\* extension can push data for PHP to consume:

```go
// frankenonnx: model loaded, tell PHP
state.Set("onnx.models", []string{"sentiment", "embedding"})
state.Set("onnx.status", "ready")

// frankenwasm: plugin registry
state.Set("wasm.plugins", []string{"markdown", "highlight"})
```

```php
$state = new SharedArray();
if ($state['onnx.status'] === 'ready') {
    $models = $state['onnx.models'];
}
```

### Feature Flags / Configuration

```php
// Set from admin panel or deploy script
$state['feature_flags'] = ['new_ui' => true, 'beta_search' => false];

// Read from any request handler — no database query
$flags = $state['feature_flags'];
if ($flags['new_ui']) { ... }
```

### Cross-Request Caching

```php
// Request 1: expensive computation
$state['user.123'] = fetchUserProfile(123);  // 50 ms database call

// Request 2: instant read from shared memory
$user = $state['user.123'];  // ~0 ms (cached)
```

### Coordination Between Workers

```php
// Worker 1: publish rate limit counter
$state['rate.api.count'] = ($state['rate.api.count'] ?? 0) + 1;

// Worker 2: check limit
if (($state['rate.api.count'] ?? 0) > 1000) {
    return new Response('Rate limited', 429);
}
```

## The Economics

| Approach | Read Latency | Write Latency | Infrastructure |
|----------|-------------|---------------|----------------|
| Redis | 0.5–2 ms | 0.5–2 ms | Redis server |
| APCu | ~1 μs | ~1 μs | None (but no Go access) |
| Database | 5–50 ms | 5–50 ms | Database server |
| SharedArray (cached) | ~0 ns | ~1 μs | None |
| SharedArray (miss) | ~50 μs | ~1 μs | None |

Cached reads are local memory lookups — the same speed as reading a PHP variable. Cache misses
require one CGo crossing + JSON decode (~50 μs), which is still 10–40x faster than Redis.

The real win isn't raw speed — it's eliminating infrastructure. No Redis to provision, monitor,
and scale. No connection pool to manage. No serialization format to debug. No network partition
to handle. The state is just... there.

## Key Takeaway

Shared state doesn't have to be an external service. When PHP runs inside Go (via FrankenPHP),
they share an address space — a `sync.RWMutex` + `map` is all the infrastructure you need.
Version-gated caching makes reads nearly free. Type-specific setters make writes efficient.
The API is `$state['key']` on both sides.

PHP and Go can share memory now. The barrier isn't technical — it's just awareness.

---

This work is licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
You are free to share and adapt this material with appropriate attribution.
