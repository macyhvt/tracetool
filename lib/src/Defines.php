<?php // lib\src\Defines.php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined ('_FTK_APP_')     OR define('_FTK_APP_', 1);

// Define app root
//defined ('FTKPATH_BASE')  OR define('FTKPATH_BASE', dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
//defined ('FTKPATH_BASE')  OR define('FTKPATH_BASE', $_SERVER['CONTEXT_DOCUMENT_ROOT']);
defined ('FTKPATH_BASE')  OR define('FTKPATH_BASE', $_SERVER['DOCUMENT_ROOT']);

// Toggle debug mode
defined ('FTK_DEBUG')     OR define('FTK_DEBUG', 0);
defined ('FTK_PROFILING') OR define('FTK_PROFILING', 0);

// Global definitions
$parts = explode(DIRECTORY_SEPARATOR, FTKPATH_BASE);		// FTKPATH_BASE is defined in index.php
array_pop($parts);

// Paths.
define('FTKPATH_ROOT',           implode(DIRECTORY_SEPARATOR, $parts));

// define('FTKPATH_BASE',      /* DEFINED IN index.php */);
define('FTKPATH_ASSETS',         FTKPATH_BASE   . 'assets');
define('FTKPATH_DOWNLOADS',      FTKPATH_BASE   . 'downloads');
define('FTKPATH_DRAWINGS',       FTKPATH_ASSETS . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'drawings');
define('FTKPATH_LAYOUTS',        FTKPATH_BASE   . 'layouts');
define('FTKPATH_LANGUAGE',       FTKPATH_BASE   . 'language');
define('FTKPATH_LIBRARIES',      FTKPATH_BASE   . 'lib');
define('FTKPATH_LOGS',           FTKPATH_BASE   . 'logs');
define('FTKPATH_TEMP',           FTKPATH_BASE   . 'tmp');
define('FTKPATH_VIEWS',          FTKPATH_BASE   . 'views');
define('FTKPATH_MEDIA',          FTKPATH_ASSETS . DIRECTORY_SEPARATOR . 'mediafiles');
define('FTKPATH_MEDIA_ARTICLES', FTKPATH_MEDIA  . DIRECTORY_SEPARATOR . 'articles');
define('FTKPATH_MEDIA_DEVICE',   FTKPATH_MEDIA  . DIRECTORY_SEPARATOR . 'device');
define('FTKPATH_MEDIA_PART',     FTKPATH_MEDIA  . DIRECTORY_SEPARATOR . 'parts');
define('FTKPATH_UPLOAD_BATCH',   FTKPATH_BASE   . 'tmp'    . DIRECTORY_SEPARATOR . 'batch-upload');

//define('FTKURI_BASE',            /* DEFINED IN index.php */);

//define('FTKRULE_EDIT_TRACKING_EXPIRES',        '5d');			// time in days a user can edit its own tracking (default: 5 days)	-	load and process via DateInterval(sprintf('P%s', mb_strtoupper(FTKRULE_EDIT_TRACKING_EXPIRES)))
//define('FTKRULE_EDIT_TRACKING_EXPIRES',        '1 days');		// time in hours a user can edit its own tracking (default: 5 days)	-	load and process via DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES)
//define('FTKRULE_EDIT_TRACKING_EXPIRES',        't1h');		// time in hours a user can edit its own tracking (default: 1 hour)	-	load and process via DateInterval(sprintf('P%s', mb_strtoupper(FTKRULE_EDIT_TRACKING_EXPIRES)))
//define('FTKRULE_EDIT_TRACKING_EXPIRES',        '1 hour');		// time in hours a user can edit its own tracking (default: 5 minutes)	-	load and process via DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES)
//define('FTKRULE_EDIT_TRACKING_EXPIRES',        't60m');		// time in minutes a user can edit its own tracking (default: 5 minutes)	-	load and process via DateInterval(sprintf('P%s', mb_strtoupper(FTKRULE_EDIT_TRACKING_EXPIRES)))
define('FTKRULE_EDIT_TRACKING_EXPIRES',        '480 minutes');	// time in hours a user can edit its own tracking (default: 5 minutes)	-	load and process via DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES)
define('FTKRULE_NULLDATE',                     '0000-00-00 00:00:00');
define('FTKRULE_DATE_FORMAT',                  'Y-m-d');
define('FTKRULE_DATETIME_FORMAT',              'Y-m-d H:i:s');
define('FTKRULE_TIME_FORMAT',                  'H:i:s');
define('FTKRULE_TIMEZONE',                     'Europe/Berlin');
// FIXME - process ini_get('upload_max_filesize'), which returns '50M'
//define('FTKRULE_UPLOAD_MAX_SIZE',              5242880);	// apply php.ini limit or fall back to  5MB ... 1 MB = 1024 KB = 1048576 B         ( see: https://fischerclan.de/byte_umrechner.html )
//define('FTKRULE_UPLOAD_MAX_SIZE',              '5M');		// same limit but different notation - must be resolved via ini_parse_quantity()   ( see: https://www.php.net/manual/de/function.ini-parse-quantity.php )
define('FTKRULE_UPLOAD_MAX_SIZE',              52428800);	// apply php.ini limit or fall back to 50MB ... 1 MB = 1024 KB = 1048576 B         ( see: https://fischerclan.de/byte_umrechner.html )
//define('FTKRULE_UPLOAD_MAX_SIZE',              '50M');	// same limit but different notation - must be resolved via ini_parse_quantity()   ( see: https://www.php.net/manual/de/function.ini-parse-quantity.php )

// Thumbnails for drawings.
define('FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER', 'https://via.placeholder.com');
//define('FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER', 'https://dummyimage.com');   // via.placeholder.com is an unreliable source that should be avoided
define('FTKRULE_DRAWING_THUMB_WIDTH',          288);		// prev. 280
define('FTKRULE_DRAWING_THUMB_HEIGHT',         204);		// prev. 198
define('FTKRULE_DRAWING_THUMB_EXTENSION',      'png');

// Password generation requirements.
define('FTKPARAM_PASSWORD_MIN_LENGTH',         10);
define('FTKPARAM_PASSWORD_MAX_LENGTH',         55);
define('FTKPARAM_PASSWORD_REQUIRE_UPPERCASE',  true);
define('FTKPARAM_PASSWORD_REQUIRE_LOWERCASE',  true);
define('FTKPARAM_PASSWORD_REQUIRE_NUMBERS',    true);
define('FTKPARAM_PASSWORD_REQUIRE_SYMBOLS',    false);

// Interval and timeout limits.
define('FTKRULE_INTERVAL_AUTOREFRESH_PROJECT_MATRIX',   60);	// 60s

// Help section persona definition.
define('FTKPARAM_PERSONA_PROJECT_MANAGEMENT',			'Sebastian Mathes');
define('FTKPARAM_EMAIL_PROJECT_MANAGEMENT',				'smathes@froetekgroup.com');
define('FTKPARAM_PERSONA_MOULD_PROJECT_MANAGEMENT',		'Benjamin Bakos');
define('FTKPARAM_EMAIL_MOULD_PROJECT_MANAGEMENT',		'bbakos@froetekgroup.com');
define('FTKPARAM_PERSONA_PROGRAMMING_AND_TECH_SUPPORT',	'Manoj Kumar');
define('FTKPARAM_EMAIL_PROGRAMMING_AND_TECH_SUPPORT',	'mkumar@froetekgroup.com');

// Common regular expressions.
define('FTKREGEX_DRAWING_NUMBER',      '^([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3})$');
define('FTKREGEX_DRAWING_INDEX',       '^[0-9A-Z]{1}$');
define('FTKREGEX_DRAWING_FILE',        '^([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3}\.)([\w\d]{1})(\.(pdf|PDF))$');
define('FTKREGEX_ERROR_NUMBER',        '^[0-9A-Z]{4,10}$');
define('FTKREGEX_ERROR_WINCARAT_CODE', '^[0-9A-Z]{4,10}$');
define('FTKREGEX_LOT_NUMBER',          '^[A-Za-z0-9]{50}$');
define('FTKREGEX_LOT_ITEM_NUMBER',     '^([A-Z0-9]{3}\-){2}[A-Z0-9]{3}@([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3})$');
define('FTKREGEX_PASSWORD',            '^[A-Za-z0-9]{' . FTKPARAM_PASSWORD_MIN_LENGTH . ',' . FTKPARAM_PASSWORD_MAX_LENGTH . '}$'); // pattern for a min. 10 chars long password
define('FTKREGEX_PROJECT_NUMBER',      '^[0-9A-Z]{3}$'); // pattern for a 3-char alnum code
define('FTKREGEX_TRACKINGCODE',        '^([A-Z0-9]{3}\-){2}[A-Z0-9]{3}$'); // pattern for a proper 3x3-char alnum part code
define('FTKREGEX_TRACKINGCODE_INLINE', '\b([a-zA-Z0-9]{3}\-[a-zA-Z0-9]{3}\-[a-zA-Z0-9]{3})\b'); // pattern for a proper 3x3-char alnum part code within any text

// Required for Joomla libraries.
define('JPATH_ROOT', FTKPATH_ROOT);	// Should be obsolete as of library version 2+. Check whether disabling it will not break functionality, after library has reached v2+
