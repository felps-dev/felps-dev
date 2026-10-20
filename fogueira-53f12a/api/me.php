<?php
/** Hands the browser its uploader token, setting the cookie if it has none. */
require __DIR__ . '/lib.php';

fg_bootstrap();
fg_require_method(array('GET', 'HEAD'));

// The front pre-checks with these so it never promises what the host refuses.
// max_file_uploads is PHP_INI_SYSTEM: only the host's php.ini decides it.
$perRequest = (int) ini_get('max_file_uploads');
$sizeCap = fg_memory_bytes(ini_get('upload_max_filesize'));

// post_max_size bounds the WHOLE multipart body, not each file, and the host
// can set it well below upload_max_filesize * max_file_uploads. Without this
// number the browser packs a batch the server answers with 413. The margin
// covers the multipart boundaries, the field names and the captions.
$bodyCap = fg_memory_bytes(ini_get('post_max_size'));
$requestBytes = $bodyCap > 0 ? max(1048576, $bodyCap - 262144) : 0;

fg_send(200, array(
    'uploader' => fg_uploader_token(),
    'maxFiles' => FG_MAX_FILES,
    'maxPerRequest' => $perRequest > 0 ? min(FG_MAX_FILES, $perRequest) : FG_MAX_FILES,
    'maxFileBytes' => ($sizeCap > 0 && $sizeCap < FG_MAX_FILE_BYTES) ? $sizeCap : FG_MAX_FILE_BYTES,
    'maxRequestBytes' => $requestBytes,
));
