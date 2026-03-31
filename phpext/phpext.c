/**
 * FrankenState PHP Extension - Main Module
 * Core module initialization and utility functions
 */

#include <stdarg.h>

#include <php.h>
#include <php_ini.h>

#include <ext/standard/info.h>

#include <Zend/zend_exceptions.h>
#include <ext/spl/spl_exceptions.h>

#include "state.h"

#include "phpext.h"
#include "phpext_cgo.h"

/* ============================================================================
 * UTILITY FUNCTIONS
 * ============================================================================ */

void frankenstate_throw_exception(const char *format, ...) {
    va_list args;
    va_start(args, format);

    zend_string *message = zend_vstrpprintf(0, format, args);
    va_end(args);

    zend_throw_exception(spl_ce_RuntimeException, ZSTR_VAL(message), 0);
    zend_string_release(message);
}

void frankenstate_throw_error(const char *format, ...) {
    va_list args;
    va_start(args, format);

    zend_string *message = zend_vstrpprintf(0, format, args);
    va_end(args);

    zend_throw_exception_ex(zend_ce_error, E_ERROR, "%s", ZSTR_VAL(message));
    zend_string_release(message);
}

/* ============================================================================
 * MODULE LIFECYCLE FUNCTIONS
 * ============================================================================ */

#ifdef COMPILE_DL_FRANKENSTATE
ZEND_GET_MODULE(frankenstate)
#endif

PHP_MINFO_FUNCTION(frankenstate)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "FrankenState Support", "enabled");
    php_info_print_table_row(2, "Version", FRANKENSTATE_VERSION);
    php_info_print_table_end();
}

zend_module_entry frankenstate_module_entry = {
    STANDARD_MODULE_HEADER,
    "frankenstate",
    frankenstate_functions,
    frankenstate_minit,
    frankenstate_mshutdown,
    frankenstate_rinit,
    frankenstate_rshutdown,
    PHP_MINFO(frankenstate),
    FRANKENSTATE_VERSION,
    STANDARD_MODULE_PROPERTIES
};

int frankenstate_minit(int type, int module_number) {
    if (frankenstate_state_minit() != SUCCESS) {
        php_error(E_WARNING, "Failed to register FrankenPHP\\SharedArray class.");
        return FAILURE;
    }

    return SUCCESS;
}

int frankenstate_rinit(int type, int module_number) {
    return SUCCESS;
}

int frankenstate_rshutdown(int type, int module_number) {
    return SUCCESS;
}

int frankenstate_mshutdown(int type, int module_number) {
    return SUCCESS;
}

/* ============================================================================
 * FUNCTION TABLE
 * ============================================================================ */

const zend_function_entry frankenstate_functions[] = {
    ZEND_FE_END
};
