// Package state provides a thread-safe in-process key-value store.
//
// This is the Go API that any FrankenPHP extension can import to share
// state with PHP. Values set from Go are immediately visible to PHP
// via the FrankenPHP\SharedArray ArrayAccess interface, and vice versa.
//
//	import "github.com/johanjanssens/frankenstate/state"
//
//	state.Set("model.status", "loaded")
//	state.Set("config", map[string]any{"debug": true})
//	val, ok := state.Get("user.preference")
package state

import (
	"encoding/json"
	"strings"
	"sync"
	"sync/atomic"
)

// Store is a thread-safe in-process key-value store with version tracking.
type Store struct {
	mu      sync.RWMutex
	data    map[string]any
	version uint64
}

// global singleton — all access goes through package-level functions.
var global = &Store{
	data: make(map[string]any),
}

// Set stores a value by key. Value can be any JSON-serializable type.
func Set(key string, value any) {
	global.mu.Lock()
	global.data[key] = value
	atomic.AddUint64(&global.version, 1)
	global.mu.Unlock()
}

// Get retrieves a value by key.
func Get(key string) (any, bool) {
	global.mu.RLock()
	v, ok := global.data[key]
	global.mu.RUnlock()
	return v, ok
}

// Delete removes a key.
func Delete(key string) {
	global.mu.Lock()
	delete(global.data, key)
	atomic.AddUint64(&global.version, 1)
	global.mu.Unlock()
}

// Has checks if a key exists.
func Has(key string) bool {
	global.mu.RLock()
	_, ok := global.data[key]
	global.mu.RUnlock()
	return ok
}

// Keys returns all keys.
func Keys() []string {
	global.mu.RLock()
	keys := make([]string, 0, len(global.data))
	for k := range global.data {
		keys = append(keys, k)
	}
	global.mu.RUnlock()
	return keys
}

// Merge upserts multiple key-value pairs atomically.
func Merge(data map[string]any) {
	global.mu.Lock()
	for k, v := range data {
		global.data[k] = v
	}
	atomic.AddUint64(&global.version, 1)
	global.mu.Unlock()
}

// Replace atomically swaps the entire data map.
func Replace(data map[string]any) {
	global.mu.Lock()
	global.data = data
	atomic.AddUint64(&global.version, 1)
	global.mu.Unlock()
}

// Snapshot returns a shallow copy of all data.
func Snapshot() map[string]any {
	global.mu.RLock()
	snap := make(map[string]any, len(global.data))
	for k, v := range global.data {
		snap[k] = v
	}
	global.mu.RUnlock()
	return snap
}

// Version returns the current version counter.
// Incremented on every write operation.
func Version() uint64 {
	return atomic.LoadUint64(&global.version)
}

// Len returns the number of keys.
func Len() int {
	global.mu.RLock()
	n := len(global.data)
	global.mu.RUnlock()
	return n
}

// --- JSON bridge (used by CGO layer) ---

// GetJSON retrieves a value as a JSON string.
func GetJSON(key string) (string, bool) {
	global.mu.RLock()
	v, ok := global.data[key]
	global.mu.RUnlock()
	if !ok {
		return "", false
	}
	b, err := json.Marshal(v)
	if err != nil {
		return "", false
	}
	return string(b), true
}

// SetJSON stores a value from a JSON string.
// Uses json.Decoder with UseNumber() to preserve numeric precision.
func SetJSON(key string, jsonStr string) error {
	var v any
	dec := json.NewDecoder(strings.NewReader(jsonStr))
	dec.UseNumber()
	if err := dec.Decode(&v); err != nil {
		return err
	}
	Set(key, v)
	return nil
}

// SnapshotJSON returns all data as a JSON object string.
// Takes a shallow snapshot under read lock, marshals outside the lock.
func SnapshotJSON() (string, error) {
	snap := Snapshot()
	b, err := json.Marshal(snap)
	if err != nil {
		return "", err
	}
	return string(b), nil
}

// MergeJSON merges from a JSON object string.
// Uses json.Decoder with UseNumber() to preserve numeric precision.
func MergeJSON(jsonStr string) error {
	var m map[string]any
	dec := json.NewDecoder(strings.NewReader(jsonStr))
	dec.UseNumber()
	if err := dec.Decode(&m); err != nil {
		return err
	}
	Merge(m)
	return nil
}

// ReplaceJSON atomically replaces the entire map from a JSON object.
// Uses json.Decoder with UseNumber() to preserve numeric precision.
func ReplaceJSON(jsonStr string) error {
	var m map[string]any
	dec := json.NewDecoder(strings.NewReader(jsonStr))
	dec.UseNumber()
	if err := dec.Decode(&m); err != nil {
		return err
	}
	Replace(m)
	return nil
}

// KeysJSON returns all keys as a JSON array string.
func KeysJSON() (string, error) {
	keys := Keys()
	b, err := json.Marshal(keys)
	if err != nil {
		return "", err
	}
	return string(b), nil
}
