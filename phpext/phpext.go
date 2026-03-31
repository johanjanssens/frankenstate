package phpext

// #include <stdlib.h>
// #include <stdint.h>
// #cgo CFLAGS: -I../../frankenphp
// #include "frankenphp.h"
// #include "phpext.h"
//
import "C"
import (
	"unsafe"

	"github.com/johanjanssens/frankenstate/state"

	"github.com/dunglas/frankenphp"
)

func init() {
	frankenphp.RegisterExtension(unsafe.Pointer(&C.frankenstate_module_entry))
}

// --- Snapshot & version (used by request-scoped cache) ---

//export go_state_snapshot
func go_state_snapshot() (*C.char, C.bool) {
	jsonStr, err := state.SnapshotJSON()
	if err != nil {
		return C.CString(err.Error()), C.bool(false)
	}
	return C.CString(jsonStr), C.bool(true)
}

//export go_state_version
func go_state_version() C.ulonglong {
	return C.ulonglong(state.Version())
}

// --- Type-specific setters (avoid JSON overhead for scalars) ---

//export go_state_set_int
func go_state_set_int(key *C.char, val C.longlong) {
	state.Set(C.GoString(key), int64(val))
}

//export go_state_set_float
func go_state_set_float(key *C.char, val C.double) {
	state.Set(C.GoString(key), float64(val))
}

//export go_state_set_bool
func go_state_set_bool(key *C.char, val C.int) {
	state.Set(C.GoString(key), val != 0)
}

//export go_state_set_string
func go_state_set_string(key *C.char, val *C.char) {
	state.Set(C.GoString(key), C.GoString(val))
}

//export go_state_set_null
func go_state_set_null(key *C.char) {
	state.Set(C.GoString(key), nil)
}

// --- JSON setter (for complex types: arrays, objects) ---

//export go_state_set
func go_state_set(key *C.char, jsonVal *C.char) (*C.char, C.bool) {
	if err := state.SetJSON(C.GoString(key), C.GoString(jsonVal)); err != nil {
		return C.CString(err.Error()), C.bool(false)
	}
	return nil, C.bool(true)
}

// --- Mutators ---

//export go_state_delete
func go_state_delete(key *C.char) {
	state.Delete(C.GoString(key))
}

//export go_state_merge
func go_state_merge(jsonStr *C.char) (*C.char, C.bool) {
	if err := state.MergeJSON(C.GoString(jsonStr)); err != nil {
		return C.CString(err.Error()), C.bool(false)
	}
	return nil, C.bool(true)
}

//export go_state_replace
func go_state_replace(jsonStr *C.char) (*C.char, C.bool) {
	if err := state.ReplaceJSON(C.GoString(jsonStr)); err != nil {
		return C.CString(err.Error()), C.bool(false)
	}
	return nil, C.bool(true)
}

// --- Read-only (kept for Go-side use, PHP reads from cache) ---

//export go_state_has
func go_state_has(key *C.char) C.bool {
	return C.bool(state.Has(C.GoString(key)))
}

//export go_state_keys
func go_state_keys() (*C.char, C.bool) {
	jsonStr, err := state.KeysJSON()
	if err != nil {
		return C.CString(err.Error()), C.bool(false)
	}
	return C.CString(jsonStr), C.bool(true)
}

//export go_state_len
func go_state_len() C.int {
	return C.int(state.Len())
}
