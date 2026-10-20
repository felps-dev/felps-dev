<?php
/** Hands the browser its uploader token, setting the cookie if it has none. */
require __DIR__ . '/lib.php';

fg_bootstrap();
fg_require_method(array('GET', 'HEAD'));

// The front pre-checks with these so it never promises what the host refuses.
// max_file_uploads is PHP_INI_SYSTEM: only the host's php.ini decides it.
$perRequest = (int) ini_get('max_file_uploads');
$sizeCap = fg_memory_bytes(ini_get('upload_max_filesize'));

fg_send(200, array(
    'uploader' => fg_uploader_token(),
    'maxFiles' => FG_MAX_FILES,
    'maxPerRequest' => $perRequest > 0 ? min(FG_MAX_FILES, $perRequest) : FG_MAX_FILES,
    'maxFileBytes' => ($sizeCap > 0 && $sizeCap < FG_MAX_FILE_BYTES) ? $sizeCap : FG_MAX_FILE_BYTES,
));
