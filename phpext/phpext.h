#ifndef FRANKENSTATE_H
#define FRANKENSTATE_H

#include <stddef.h>
#include <stdint.h>
#include <stdbool.h>

#include <php.h>
#include <Zend/zend_types.h>

#include "state.h"

#define FRANKENSTATE_VERSION "0.1.0"
#define FRANKENSTATE_JSON_DEPTH 512

// Module entry (registered via frankenphp.RegisterExtension)
extern zend_module_entry frankenstate_module_entry;

// Module lifecycle functions
int frankenstate_minit(int type, int module_number);
int frankenstate_mshutdown(int type, int module_number);
int frankenstate_rinit(int type, int module_number);
int frankenstate_rshutdown(int type, int module_number);

// Utility functions
void frankenstate_throw_exception(const char *format, ...);
void frankenstate_throw_error(const char *format, ...);

// PHP function declarations
extern const zend_function_entry frankenstate_functions[];

#endif // FRANKENSTATE_H
