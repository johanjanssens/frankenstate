/**
 * FrankenState - State class implementation
 * FrankenPHP\SharedArray: ArrayAccess + Countable + IteratorAggregate
 *
 * All instances share the same Go-backed store. Per-object cache
 * (version-gated) avoids redundant CGO + JSON round-trips:
 *
 *   1. Check go_state_version() against cached_version
 *   2. If unchanged → serve from cached zval (zero CGO)
 *   3. If changed → fetch snapshot via go_state_snapshot(), decode, cache
 *
 * Writes use type-specific setters (int/float/bool/string/null) for
 * scalars, falling back to JSON only for complex types (arrays, objects).
 */

#include <stdlib.h>
#include <string.h>

#include <php.h>
#include <php_ini.h>

#include <ext/json/php_json.h>

#include <Zend/zend_exceptions.h>
#include <Zend/zend_types.h>
#include <Zend/zend_interfaces.h>
#include <Zend/zend_smart_str.h>

#include "state.h"
#include "phpext.h"
#include "phpext_cgo.h"

/* ============================================================================
 * STATIC VARIABLES & FORWARD DECLARATIONS
 * ============================================================================ */

static zend_class_entry *state_ce = NULL;
static zend_object_handlers state_object_handlers;

static inline frankenstate_state_object *frankenstate_from_obj(zend_object *obj);
static zend_object *state_create_object(zend_class_entry *ce);
static void state_free_object(zend_object *object);
static int refresh_cache(frankenstate_state_object *obj, zend_bool force);
static char *zval_to_json(zval *value);

static const zend_function_entry state_methods[];

/* ============================================================================
 * MODULE INIT
 * ============================================================================ */

int frankenstate_state_minit(void)
{
    zend_class_entry ce;

    INIT_NS_CLASS_ENTRY(ce, "FrankenPHP", "SharedArray", state_methods);

    state_ce = zend_register_internal_class(&ce);

    if (UNEXPECTED(!state_ce)) {
        return FAILURE;
    }

    state_ce->ce_flags |= ZEND_ACC_FINAL;
    state_ce->create_object = state_create_object;

    /* Implement interfaces */
    zend_class_implements(state_ce, 3,
        zend_ce_arrayaccess,
        zend_ce_countable,
        zend_ce_aggregate
    );

    memcpy(&state_object_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    state_object_handlers.offset = XtOffsetOf(frankenstate_state_object, std);
    state_object_handlers.free_obj = state_free_object;
    state_object_handlers.clone_obj = NULL;

    return SUCCESS;
}

/* ============================================================================
 * OBJECT LIFECYCLE
 * ============================================================================ */

static zend_object *state_create_object(zend_class_entry *ce)
{
    frankenstate_state_object *intern = ecalloc(1,
        sizeof(frankenstate_state_object) + zend_object_properties_size(ce));

    zend_object_std_init(&intern->std, ce);
    object_properties_init(&intern->std, ce);

    ZVAL_UNDEF(&intern->cached_data);
    intern->cached_version = 0;

    intern->std.handlers = &state_object_handlers;

    return &intern->std;
}

static void state_free_object(zend_object *object)
{
    frankenstate_state_object *intern = frankenstate_from_obj(object);

    zval_ptr_dtor(&intern->cached_data);

    zend_object_std_dtor(&intern->std);
}

static inline frankenstate_state_object *frankenstate_from_obj(zend_object *obj) {
    return (frankenstate_state_object *)((char *)(obj) - XtOffsetOf(frankenstate_state_object, std));
}

/* ============================================================================
 * REQUEST-SCOPED CACHE
 *
 * Fetches the full snapshot from Go as JSON, decodes into cached_data.
 * Skips the fetch if backend version hasn't changed since last refresh.
 * ============================================================================ */

static int refresh_cache(frankenstate_state_object *obj, zend_bool force)
{
    unsigned long long current_version = (unsigned long long)go_state_version();

    /* Cache hit: version unchanged and data exists */
    if (EXPECTED(!force
        && Z_TYPE(obj->cached_data) == IS_ARRAY
        && current_version == obj->cached_version)) {
        return SUCCESS;
    }

    /* Fetch fresh snapshot from Go */
    struct go_state_snapshot_return result = go_state_snapshot();

    if (!result.r1) {
        if (result.r0) free(result.r0);
        return FAILURE;
    }

    /* Free old cache */
    zval_ptr_dtor(&obj->cached_data);
    ZVAL_UNDEF(&obj->cached_data);

    /* Decode JSON snapshot */
    if (php_json_decode_ex(&obj->cached_data, result.r0, strlen(result.r0),
                           PHP_JSON_OBJECT_AS_ARRAY, FRANKENSTATE_JSON_DEPTH) != SUCCESS) {
        free(result.r0);
        return FAILURE;
    }

    free(result.r0);
    obj->cached_version = current_version;

    return SUCCESS;
}

/* ============================================================================
 * HELPER: encode zval to JSON C string (caller must free)
 * ============================================================================ */

static char *zval_to_json(zval *value)
{
    smart_str buf = {0};

    if (php_json_encode(&buf, value, PHP_JSON_THROW_ON_ERROR) != SUCCESS) {
        smart_str_free(&buf);
        return NULL;
    }

    smart_str_0(&buf);
    char *result = strdup(ZSTR_VAL(buf.s));
    smart_str_free(&buf);

    return result;
}

/* ============================================================================
 * HELPER: validate key length
 * ============================================================================ */

static inline int validate_key(zend_string *key)
{
    if (UNEXPECTED(ZSTR_LEN(key) > FRANKENSTATE_MAX_KEY_LEN)) {
        frankenstate_throw_exception("Key too long (max %d characters)", FRANKENSTATE_MAX_KEY_LEN);
        return FAILURE;
    }
    return SUCCESS;
}

/* ============================================================================
 * ArrayAccess: offsetGet($key)
 * Reads from cached snapshot — no CGO crossing if version unchanged.
 * ============================================================================ */

PHP_METHOD(SharedArray, offsetGet)
{
    zend_string *key;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    if (validate_key(key) != SUCCESS) {
        RETURN_THROWS();
    }

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        RETURN_NULL();
    }

    zval *val = zend_hash_find(Z_ARRVAL(intern->cached_data), key);
    if (!val) {
        RETURN_NULL();
    }

    RETURN_COPY(val);
}

/* ============================================================================
 * ArrayAccess: offsetSet($key, $value)
 * Type-specific setters for scalars, JSON fallback for complex types.
 * Forces cache refresh after write.
 * ============================================================================ */

PHP_METHOD(SharedArray, offsetSet)
{
    zend_string *key;
    zval *value;

    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_STR(key)
        Z_PARAM_ZVAL(value)
    ZEND_PARSE_PARAMETERS_END();

    if (validate_key(key) != SUCCESS) {
        RETURN_THROWS();
    }

    /* Type dispatch — scalars avoid JSON overhead */
    switch (Z_TYPE_P(value)) {
        case IS_LONG:
            go_state_set_int(ZSTR_VAL(key), (GoInt64)Z_LVAL_P(value));
            break;

        case IS_DOUBLE:
            go_state_set_float(ZSTR_VAL(key), Z_DVAL_P(value));
            break;

        case IS_TRUE:
            go_state_set_bool(ZSTR_VAL(key), 1);
            break;

        case IS_FALSE:
            go_state_set_bool(ZSTR_VAL(key), 0);
            break;

        case IS_STRING:
            go_state_set_string(ZSTR_VAL(key), ZSTR_VAL(Z_STR_P(value)));
            break;

        case IS_NULL:
            go_state_set_null(ZSTR_VAL(key));
            break;

        default: {
            /* Complex types (arrays, objects) → JSON */
            char *json = zval_to_json(value);
            if (!json) {
                frankenstate_throw_exception("Failed to encode value as JSON");
                RETURN_THROWS();
            }

            struct go_state_set_return result = go_state_set(ZSTR_VAL(key), json);
            free(json);

            if (!result.r1) {
                if (result.r0) {
                    frankenstate_throw_exception("Failed to set '%s': %s", ZSTR_VAL(key), result.r0);
                    free(result.r0);
                }
                RETURN_THROWS();
            }
            break;
        }
    }

    /* Force cache refresh to reflect our write */
    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));
    refresh_cache(intern, 1);
}

/* ============================================================================
 * ArrayAccess: offsetUnset($key)
 * ============================================================================ */

PHP_METHOD(SharedArray, offsetUnset)
{
    zend_string *key;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    if (validate_key(key) != SUCCESS) {
        RETURN_THROWS();
    }

    go_state_delete(ZSTR_VAL(key));

    /* Force cache refresh */
    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));
    refresh_cache(intern, 1);
}

/* ============================================================================
 * ArrayAccess: offsetExists($key)
 * Uses cached snapshot — no CGO crossing if version unchanged.
 * ============================================================================ */

PHP_METHOD(SharedArray, offsetExists)
{
    zend_string *key;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    if (validate_key(key) != SUCCESS) {
        RETURN_THROWS();
    }

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        RETURN_FALSE;
    }

    RETURN_BOOL(zend_hash_exists(Z_ARRVAL(intern->cached_data), key));
}

/* ============================================================================
 * Countable: count()
 * ============================================================================ */

PHP_METHOD(SharedArray, count)
{
    ZEND_PARSE_PARAMETERS_NONE();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        RETURN_LONG(0);
    }

    RETURN_LONG(zend_hash_num_elements(Z_ARRVAL(intern->cached_data)));
}

/* ============================================================================
 * IteratorAggregate: getIterator()
 * Returns ArrayIterator over an immutable copy (safe from concurrent mutation).
 * ============================================================================ */

PHP_METHOD(SharedArray, getIterator)
{
    ZEND_PARSE_PARAMETERS_NONE();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        frankenstate_throw_exception("Failed to get state snapshot");
        RETURN_THROWS();
    }

    /* Immutable copy for safe iteration */
    zval snapshot;
    ZVAL_ARR(&snapshot, zend_array_dup(Z_ARRVAL(intern->cached_data)));

    /* Create ArrayIterator */
    zend_string *class_name = zend_string_init("ArrayIterator", sizeof("ArrayIterator") - 1, 0);
    zend_class_entry *ce = zend_lookup_class(class_name);
    zend_string_release(class_name);

    if (!ce) {
        zval_ptr_dtor(&snapshot);
        frankenstate_throw_error("ArrayIterator class not found");
        RETURN_THROWS();
    }

    object_init_ex(return_value, ce);

    zval ctor_ret;
    zend_call_method_with_1_params(Z_OBJ_P(return_value), ce, &ce->constructor, "__construct", &ctor_ret, &snapshot);
    zval_ptr_dtor(&ctor_ret);
    zval_ptr_dtor(&snapshot);
}

/* ============================================================================
 * merge(array $data): void
 * ============================================================================ */

PHP_METHOD(SharedArray, merge)
{
    zval *data;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(data)
    ZEND_PARSE_PARAMETERS_END();

    char *json = zval_to_json(data);
    if (!json) {
        frankenstate_throw_exception("Failed to encode merge data as JSON");
        RETURN_THROWS();
    }

    struct go_state_merge_return result = go_state_merge(json);
    free(json);

    if (!result.r1) {
        if (result.r0) {
            frankenstate_throw_exception("Failed to merge: %s", result.r0);
            free(result.r0);
        }
        RETURN_THROWS();
    }

    /* Force cache refresh */
    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));
    refresh_cache(intern, 1);
}

/* ============================================================================
 * replace(array $data): array
 * Atomically replaces entire store, returns previous contents.
 * ============================================================================ */

PHP_METHOD(SharedArray, replace)
{
    zval *data;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(data)
    ZEND_PARSE_PARAMETERS_END();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    /* Encode new data first (fail early) */
    char *json = zval_to_json(data);
    if (!json) {
        frankenstate_throw_exception("Failed to encode replace data as JSON");
        RETURN_THROWS();
    }

    /* Capture current snapshot as return value */
    zval old;
    if (refresh_cache(intern, 1) == SUCCESS) {
        ZVAL_ARR(&old, zend_array_dup(Z_ARRVAL(intern->cached_data)));
    } else {
        array_init(&old);
    }

    /* Replace */
    struct go_state_replace_return result = go_state_replace(json);
    free(json);

    if (!result.r1) {
        zval_ptr_dtor(&old);
        if (result.r0) {
            frankenstate_throw_exception("Failed to replace: %s", result.r0);
            free(result.r0);
        }
        RETURN_THROWS();
    }

    /* Force cache refresh */
    refresh_cache(intern, 1);

    RETVAL_ZVAL(&old, 0, 0);
}

/* ============================================================================
 * keys(): array
 * ============================================================================ */

PHP_METHOD(SharedArray, keys)
{
    ZEND_PARSE_PARAMETERS_NONE();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        array_init(return_value);
        return;
    }

    HashTable *ht = Z_ARRVAL(intern->cached_data);
    array_init_size(return_value, zend_hash_num_elements(ht));

    zend_string *key;
    ZEND_HASH_FOREACH_STR_KEY(ht, key) {
        if (key) {
            add_next_index_str(return_value, zend_string_copy(key));
        }
    } ZEND_HASH_FOREACH_END();
}

/* ============================================================================
 * snapshot(): array
 * Returns a fresh immutable copy.
 * ============================================================================ */

PHP_METHOD(SharedArray, snapshot)
{
    ZEND_PARSE_PARAMETERS_NONE();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    if (refresh_cache(intern, 0) != SUCCESS) {
        array_init(return_value);
        return;
    }

    ZVAL_ARR(return_value, zend_array_dup(Z_ARRVAL(intern->cached_data)));
}

/* ============================================================================
 * version(): int
 * ============================================================================ */

PHP_METHOD(SharedArray, version)
{
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_LONG((zend_long)go_state_version());
}

/* ============================================================================
 * __serialize(): array
 * Returns ['data' => [...snapshot...]] for var_export / session support.
 * ============================================================================ */

PHP_METHOD(SharedArray, __serialize)
{
    ZEND_PARSE_PARAMETERS_NONE();

    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));

    array_init(return_value);

    if (refresh_cache(intern, 0) == SUCCESS) {
        zval data_copy;
        ZVAL_ARR(&data_copy, zend_array_dup(Z_ARRVAL(intern->cached_data)));
        add_assoc_zval(return_value, "data", &data_copy);
    } else {
        zval empty;
        array_init(&empty);
        add_assoc_zval(return_value, "data", &empty);
    }
}

/* ============================================================================
 * __unserialize(array $data): void
 * Restores by replacing entire store with serialized data.
 * ============================================================================ */

PHP_METHOD(SharedArray, __unserialize)
{
    zval *data;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY(data)
    ZEND_PARSE_PARAMETERS_END();

    zval *payload = zend_hash_str_find(Z_ARRVAL_P(data), "data", sizeof("data") - 1);
    if (!payload || Z_TYPE_P(payload) != IS_ARRAY) {
        frankenstate_throw_exception("Invalid serialization data: missing 'data' key");
        RETURN_THROWS();
    }

    char *json = zval_to_json(payload);
    if (!json) {
        frankenstate_throw_exception("Failed to encode unserialized data as JSON");
        RETURN_THROWS();
    }

    struct go_state_replace_return result = go_state_replace(json);
    free(json);

    if (!result.r1) {
        if (result.r0) {
            frankenstate_throw_exception("Failed to restore state: %s", result.r0);
            free(result.r0);
        }
        RETURN_THROWS();
    }

    /* Force cache refresh */
    frankenstate_state_object *intern = frankenstate_from_obj(Z_OBJ_P(ZEND_THIS));
    refresh_cache(intern, 1);
}

/* ============================================================================
 * METHOD TABLE
 * ============================================================================ */

static const zend_function_entry state_methods[] = {
    /* ArrayAccess */
    PHP_ME(SharedArray, offsetGet,      arginfo_state_offsetGet,      ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, offsetSet,      arginfo_state_offsetSet,      ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, offsetUnset,    arginfo_state_offsetUnset,    ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, offsetExists,   arginfo_state_offsetExists,   ZEND_ACC_PUBLIC)
    /* Countable */
    PHP_ME(SharedArray, count,          arginfo_state_count,          ZEND_ACC_PUBLIC)
    /* IteratorAggregate */
    PHP_ME(SharedArray, getIterator,    arginfo_state_getIterator,    ZEND_ACC_PUBLIC)
    /* Custom */
    PHP_ME(SharedArray, merge,          arginfo_state_merge,          ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, replace,        arginfo_state_replace,        ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, keys,           arginfo_state_keys,           ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, snapshot,       arginfo_state_snapshot,       ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, version,        arginfo_state_version,        ZEND_ACC_PUBLIC)
    /* Serialization */
    PHP_ME(SharedArray, __serialize,    arginfo_state___serialize,    ZEND_ACC_PUBLIC)
    PHP_ME(SharedArray, __unserialize,  arginfo_state___unserialize,  ZEND_ACC_PUBLIC)
    PHP_FE_END
};
