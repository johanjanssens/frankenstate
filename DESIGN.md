# FrankenState Design

## Problem

FrankenPHP extensions written in Go have no way to push data into PHP's runtime.
External stores (Redis, Memcached) add network overhead for in-process data. APCu
is designed for prefork shared memory, not threaded FrankenPHP, and has no Go access.

[PR #2287](https://github.com/php/frankenphp/pull/2287) proposes "background
workers/sidekicks" but couples three concerns — background execution, shared state,
and event notification — into one monolithic subsystem with `pemalloc`, signaling
streams, new thread types, and Caddyfile directives.

FrankenState + [FrankenAsync](https://github.com/johanjanssens/frankenasync)
replace this with two composable primitives. State is orthogonal to execution.

## Approach

A Go `map[string]any` protected by `sync.RWMutex` with an atomic version counter.
Three access paths to the same store:

| Path | Mechanism | Latency |
|------|-----------|---------|
| Go `state.Set(k, v)` | Direct function call | ~0 ns |
| PHP `$state['key']` | CGo bridge, version-gated cache | ~0 ns (hit) / ~50 μs (miss) |
| `redis-cli -p 6380` | RESP over TCP via [redcon](https://github.com/tidwall/redcon) | ~50 μs |

**PHP read path:** Each `SharedArray` object caches the full snapshot as a zval.
`offsetGet` checks `go_state_version()` — if unchanged, serves from local cache
(zero CGo). On miss, fetches `go_state_snapshot()`, decodes JSON, updates cache.

**PHP write path:** Type dispatch in C — `int`, `float`, `bool`, `string`, `null`
use type-specific CGo exports (no JSON). Arrays/objects fall back to `json_encode`
→ `go_state_set(key, json)`. Forces cache refresh after write.

**RESP server:** [`tidwall/redcon`](https://github.com/tidwall/redcon) on port 6380.
Implements: `GET`, `SET`, `DEL`, `EXISTS`, `KEYS`, `MGET`, `MSET`, `INCR`, `DECR`,
`INCRBY`, `DECRBY`, `DBSIZE`, `FLUSHDB`, `INFO`, `TYPE`, `PING`.

## Known Limitations

- **RESP `INCR`/`DECR` not truly atomic** — read-modify-write (`Get` → add →
  `Set`) without a single lock. Concurrent `INCR` calls can race. Fix: add
  `IncrBy(key, delta)` to `state` package under a single write lock, or move to
  sharded backend with native CAS.

- **RESP `DEL` double-locks** — `Has()` (read lock) then `Delete()` (write lock)
  per key. Fix: `DeleteIfExists(key)` under a single write lock.

- **Single global store** — all keys share one `RWMutex`. Under high contention
  this becomes a bottleneck. See sharded backend below.

## Future Scope

### Named Instances (Namespaces)

```php
$metrics = new SharedArray('metrics');
$config  = new SharedArray('config');
```

Per-namespace isolated backends. RESP maps namespaces to key prefixes
(`namespace:key`) — server strips/adds transparently. Go and PHP APIs work
with the namespace natively.

### Sharded Backend

Replace single `RWMutex` with 256 sharded spinlocks (~0.4% collision rate).
Per-shard locks, sub-microsecond for uncontended ops.

### Atomic Operations

`cas(key, expected, new)`, `increment(key, delta)` / `decrement(key, delta)`.
CAS loop with retry. Enables locks, rate limiters, counters.

### TTL / Expiration

Per-key TTL, lazy expiry on access + explicit `sweep()`, LRU eviction.

### Watch / Change Notification

`$state->watch(fn($s) => ...)` — callback on version change. Event-driven
via coroutine + async handles, no polling.

### Persistent Backend

Disk-backed with debounced writes (gob encoding), atomic file writes
(temp + rename).

## References

- [PR #2287](https://github.com/php/frankenphp/pull/2287) — background workers/sidekicks discussion
- [Discussion #2223](https://github.com/php/frankenphp/discussions/2223) — PHP extensions with FrankenPHP
- [`tidwall/redcon`](https://github.com/tidwall/redcon) — Redis-compatible server in Go
