#ifndef FRANKENSTATE_STATE_H
#define FRANKENSTATE_STATE_H

#include <php.h>
#include <Zend/zend_types.h>
#include <Zend/zend_API.h>

#define FRANKENSTATE_MAX_KEY_LEN 1024

/**
 * State object structure.
 * All instances share the same Go-backed store.
 * Per-object cache avoids redundant CGO + JSON round-trips.
 */
typedef struct {
    zval cached_data;                    /* Request-scoped snapshot cache */
    unsigned long long cached_version;   /* Version when cache was last refreshed */
    zend_object std;
} frankenstate_state_object;

int frankenstate_state_minit(void);

/* ArrayAccess */
PHP_METHOD(SharedArray, offsetGet);
PHP_METHOD(SharedArray, offsetSet);
PHP_METHOD(SharedArray, offsetUnset);
PHP_METHOD(SharedArray, offsetExists);

/* Countable */
PHP_METHOD(SharedArray, count);

/* IteratorAggregate */
PHP_METHOD(SharedArray, getIterator);

/* Custom methods */
PHP_METHOD(SharedArray, merge);
PHP_METHOD(SharedArray, replace);
PHP_METHOD(SharedArray, keys);
PHP_METHOD(SharedArray, snapshot);
PHP_METHOD(SharedArray, version);

/* Serialization */
PHP_METHOD(SharedArray, __serialize);
PHP_METHOD(SharedArray, __unserialize);

/* --- Argument info --- */

/* offsetGet(mixed $key): mixed */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_offsetGet, 0, 1, IS_MIXED, 0)
    ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

/* offsetSet(mixed $key, mixed $value): void */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_offsetSet, 0, 2, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
    ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

/* offsetUnset(mixed $key): void */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_offsetUnset, 0, 1, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

/* offsetExists(mixed $key): bool */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_offsetExists, 0, 1, _IS_BOOL, 0)
    ZEND_ARG_TYPE_INFO(0, key, IS_MIXED, 0)
ZEND_END_ARG_INFO()

/* count(): int */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_count, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

/* getIterator(): Traversable */
ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_state_getIterator, 0, 0, Traversable, 0)
ZEND_END_ARG_INFO()

/* merge(array $data): void */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_merge, 0, 1, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* replace(array $data): array */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_replace, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* keys(): array */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_keys, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* snapshot(): array */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_snapshot, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* version(): int */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state_version, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

/* __serialize(): array */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state___serialize, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

/* __unserialize(array $data): void */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_state___unserialize, 0, 1, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

#endif /* FRANKENSTATE_STATE_H */
