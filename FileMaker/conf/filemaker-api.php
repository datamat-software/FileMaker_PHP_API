<?php
/**
 * FileMaker API for PHP configuration file.
 *
 * All settings are in the $__FM_CONFIG array to maintain a
 * clean global namespace.
 * 
 * These three configuration parameters are set by FileMaker Server when PHP settings are configured.
 * This can be done either via `SET CWPCONFIG local/charset/prevalidate` or via the Admin API.
 * Without this file, FileMaker Server will report the error "PHP Config not found".
 * The other parameters, `useFMPHP`, `enablePHP`, and `enableXML`, have no effect without this file.
 */

$__FM_CONFIG = [];

/**
 * The default character encoding ('UTF-8' or 'ISO-8859-1', case matters).
 */
$__FM_CONFIG['charset'] = 'ISO-8859-1';

/**
 * The default locale for providing string translations of error
 * codes. Options are: 'en', 'de', 'fr', 'it', 'ja'
 */
$__FM_CONFIG['locale'] = 'it';

/**
 * Do pre-validation (validate in PHP engine) on Record data?
 */
$__FM_CONFIG['prevalidate'] = true;
