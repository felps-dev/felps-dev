<?php
/**
 * Shared bootstrap, storage and helpers for the fogueira photo API.
 *
 * Runs on PHP 7.4 and 8.x: no match(), no union types, no named arguments,
 * nothing removed in PHP 8. Only core extensions (gd, exif, fileinfo) are used.
 */

define('FG_MAX_FILES', 20);
define('FG_MAX_FILE_BYTES', 25 * 1024 * 1024);
/** Hard ceiling regardless of memory_limit: a 60 MP photo is not a phone photo. */
define('FG_MAX_PIXELS', 60000000);
define('FG_DISPLAY_MAX_SIDE', 2000);
define('FG_THUMB_MAX_SIDE', 800);
define('FG_DISPLAY_QUALITY', 82);
define('FG_THUMB_QUALITY', 78);
define('FG_AUTHOR_MAX_LEN', 80);
define('FG_CAPTION_MAX_LEN', 500);
define('FG_COOKIE_NAME', 'uploader');
define('FG_COOKIE_TTL', 365 * 24 * 60 * 60);

define('FG_ROOT_DIR', dirname(__DIR__));
define('FG_DATA_DIR', FG_ROOT_DIR . '/data');
define('FG_UPLOADS_DIR', FG_ROOT_DIR . '/uploads');
define('FG_ORIGINAL_DIR', FG_UPLOADS_DIR . '/original');
define('FG_DISPLAY_DIR', FG_UPLOADS_DIR . '/display');
define('FG_THUMB_DIR', FG_UPLOADS_DIR . '/thumb');
define('FG_STORE_FILE', FG_DATA_DIR . '/photos.json');
define('FG_LOCK_FILE', FG_DATA_DIR . '/photos.lock');
define('FG_LOG_FILE', FG_DATA_DIR . '/api.log');
define('FG_DRIVE_REMOVED_FILE', FG_DATA_DIR . '/drive-removed.txt');
define('FG_LOG_MAX_BYTES', 512 * 1024);
define('FG_PHP_ERROR_LOG', FG_DATA_DIR . '/php-errors.log');
define('FG_ADMIN_TOKEN_FILE', FG_DATA_DIR . '/admin-token.txt');
define('FG_RATE_FILE', FG_DATA_DIR . '/rate.json');
define('FG_SWEEP_STAMP', FG_DATA_DIR . '/sweep.stamp');
define('FG_STORE_FAILED', '__fg_store_write_failed__');

/*
 * Disk guards. The whole cPanel account is 2100 MB and the album shares it with
 * the rest of felps.dev, so every ceiling here is about the ACCOUNT, not about
 * one request. Numbers to watch live in DEPLOY.md section 9.
 */
/** Refuse new uploads once the album itself is this big. */
define('FG_ALBUM_MAX_BYTES', 1500 * 1024 * 1024);
/** Past this, keep serving but stop archiving the untouched original. */
define('FG_ORIGINAL_BUDGET_BYTES', 1000 * 1024 * 1024);
/** Refuse when the filesystem itself is this close to full. */
define('FG_MIN_FREE_BYTES', 300 * 1024 * 1024);
/** Hard ceiling on records, so a script cannot grow photos.json forever. */
define('FG_MAX_PHOTOS', 600);
/**
 * Photos one IP may upload per window. Deliberately generous: a whole group on
 * the same WiFi (or behind the same carrier NAT) is one IP here. 0 turns it off.
 */
define('FG_RATE_MAX_PHOTOS', 200);
define('FG_RATE_WINDOW', 3600);
/** A file younger than this may belong to a batch still being written. */
define('FG_ORPHAN_GRACE', 1800);
define('FG_SWEEP_INTERVAL', 3600);

define('FG_NO_SPACE', 'O álbum está sem espaço no servidor agora. Avisa o Felipe! As fotos que já estão aqui continuam no ar.');

define('FG_GENERIC_FAILURE', 'Algo quebrou aqui do nosso lado. Tenta de novo em alguns segundos?');

/* ---------------------------------------------------------------- bootstrap */

function fg_bootstrap()
{
    $GLOBALS['fg_sent'] = false;

    error_reporting(E_ALL);
    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');
    @ini_set('log_errors', '1');
    // Without this, lsphp drops an "error_log" file next to the script, inside
    // the web root. data/ is denied by .htaccess.
    @ini_set('error_log', FG_PHP_ERROR_LOG);
    fg_raise_memory_limit();

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header_remove('X-Powered-By');

    set_error_handler('fg_error_handler');
    set_exception_handler('fg_exception_handler');
    register_shutdown_function('fg_shutdown_handler');

    // Anything that leaks to stdout (a warning the handler misses, a stray BOM)
    // is captured here and dropped, so the body stays valid JSON.
    ob_start();
}

/** Shared hosts often cap this below what GD needs for a 12 MP photo. */
function fg_raise_memory_limit()
{
    $current = fg_memory_bytes(ini_get('memory_limit'));
    if ($current !== -1 && $current < 256 * 1024 * 1024) {
        @ini_set('memory_limit', '256M');
    }
}

function fg_error_handler($number, $message, $file = '', $line = 0)
{
    fg_log('php ' . $number . ': ' . $message . ' @ ' . basename((string) $file) . ':' . $line);
    return true; // never let PHP print it: the response must stay JSON
}

function fg_exception_handler($exception)
{
    fg_log('uncaught: ' . $exception->getMessage() . ' @ ' . basename($exception->getFile()) . ':' . $exception->getLine());
    fg_send(500, array('error' => FG_GENERIC_FAILURE));
}

function fg_shutdown_handler()
{
    if (!empty($GLOBALS['fg_sent'])) {
        return;
    }
    $last = error_get_last();
    $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_USER_ERROR);
    if ($last === null || !in_array($last['type'], $fatal, true)) {
        return;
    }
    fg_log('fatal: ' . $last['message'] . ' @ ' . basename((string) $last['file']) . ':' . $last['line']);
    fg_send(500, array('error' => FG_GENERIC_FAILURE));
}

/** The one exit door: discards buffered junk, then writes the JSON body. */
function fg_send($status, $data)
{
    $GLOBALS['fg_sent'] = true;
    $junk = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_clean();
        if ($chunk !== false) {
            $junk .= $chunk;
        }
    }
    if (trim($junk) !== '') {
        fg_log('discarded output before json: ' . substr($junk, 0, 500));
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"error":"' . FG_GENERIC_FAILURE . '"}';
    }
    echo $json;
    exit;
}

function fg_fail($status, $message)
{
    fg_send($status, array('error' => $message));
}

function fg_require_method($allowed)
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if (in_array($method, $allowed, true)) {
        return $method;
    }
    if (!headers_sent()) {
        header('Allow: ' . implode(', ', $allowed));
    }
    fg_fail(405, 'Esse endereço não aceita esse tipo de pedido.');
    return $method;
}

/** Never shows a server path to the browser; only to this log file. */
function fg_log($message)
{
    if (!is_dir(FG_DATA_DIR)) {
        @mkdir(FG_DATA_DIR, 0755, true);
    }
    $size = is_file(FG_LOG_FILE) ? @filesize(FG_LOG_FILE) : false;
    if ($size !== false && $size > FG_LOG_MAX_BYTES) {
        @unlink(FG_LOG_FILE);
    }
    @file_put_contents(
        FG_LOG_FILE,
        gmdate('c') . ' ' . str_replace(array("\r", "\n"), ' ', (string) $message) . "\n",
        FILE_APPEND
    );
}

/* -------------------------------------------------------------- environment */

function fg_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

/** URL path of the site root, derived from this script's own location. */
function fg_base_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/';
    $base = str_replace('\\', '/', dirname(dirname($script)));
    if ($base === '' || $base === '.' || $base === '/') {
        return '/';
    }
    return rtrim($base, '/') . '/';
}

function fg_memory_bytes($value)
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        return (int) ($number * 1024 * 1024 * 1024);
    }
    if ($unit === 'm') {
        return (int) ($number * 1024 * 1024);
    }
    if ($unit === 'k') {
        return (int) ($number * 1024);
    }
    return (int) $number;
}

/* ------------------------------------------------------------------- cookie */

function fg_uploader_token()
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $current = isset($_COOKIE[FG_COOKIE_NAME]) ? $_COOKIE[FG_COOKIE_NAME] : '';
    if (is_string($current) && preg_match('/^[a-f0-9]{32}$/', $current)) {
        $token = $current;
        return $token;
    }
    $token = bin2hex(random_bytes(16));
    if (!headers_sent()) {
        // Readable by JS on purpose: the front never reads the value, but the
        // cookie has to travel on every request for "mine" and DELETE to work.
        setcookie(FG_COOKIE_NAME, $token, array(
            'expires' => time() + FG_COOKIE_TTL,
            'path' => fg_base_path(),
            'secure' => fg_is_https(),
            'httponly' => false,
            'samesite' => 'Lax',
        ));
    }
    return $token;
}

/* -------------------------------------------------------------------- store */

function fg_ensure_dirs()
{
    $dirs = array(FG_DATA_DIR, FG_UPLOADS_DIR, FG_ORIGINAL_DIR, FG_DISPLAY_DIR, FG_THUMB_DIR);
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        @mkdir($dir, 0755, true);
        if (!is_dir($dir)) {
            fg_log('mkdir failed: ' . $dir);
            fg_fail(500, 'Não consegui criar as pastas das fotos aqui no servidor. Avisa o Felipe!');
        }
    }
    fg_protect_private_dirs();
    // Created with the folders, not on first use: Felipe has to be able to read
    // it from the File Manager BEFORE he needs it. See DEPLOY.md section 6.
    fg_admin_token();
}

/** The deploy should carry these, but a folder created at runtime would not. */
function fg_protect_private_dirs()
{
    $deny = "# Private: metadata and untouched originals are never served.\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
    foreach (array(FG_DATA_DIR, FG_ORIGINAL_DIR) as $dir) {
        $file = $dir . '/.htaccess';
        if (is_dir($dir) && !file_exists($file)) {
            @file_put_contents($file, $deny);
        }
    }
    // LiteSpeed has no mod_php, so the php_flag guards in uploads/.htaccess are
    // dead text there; .user.ini is the switch lsphp actually reads.
    $engineOff = FG_UPLOADS_DIR . '/.user.ini';
    if (is_dir(FG_UPLOADS_DIR) && !file_exists($engineOff)) {
        @file_put_contents($engineOff, "engine = Off\n");
    }
}

/* --------------------------------------------------------------------- disk */

/** Bytes used by every stored variant. Cheap enough: a few hundred stat calls. */
function fg_dir_bytes($dir)
{
    $total = 0;
    $names = @scandir($dir);
    if (!is_array($names)) {
        return 0;
    }
    foreach ($names as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            $total += fg_dir_bytes($path);
            continue;
        }
        $size = @filesize($path);
        if ($size !== false) {
            $total += (int) $size;
        }
    }
    return $total;
}

function fg_album_bytes()
{
    static $bytes = null;
    if ($bytes === null) {
        $bytes = fg_dir_bytes(FG_UPLOADS_DIR);
    }
    return $bytes;
}

/** -1 when the host will not tell us (quota-only accounts sometimes will not). */
function fg_free_bytes()
{
    $free = @disk_free_space(FG_ROOT_DIR);
    return is_numeric($free) ? (int) $free : -1;
}

/* -------------------------------------------------------------- admin token */

/**
 * A delete key for Felipe, so one wrong photo does not mean editing photos.json
 * by hand at the party. It is generated on the server and kept in data/ (403,
 * gitignored) on purpose: lib.php itself is published in a PUBLIC repo, so a
 * constant in this file would hand the key to everyone who finds the album.
 */
function fg_admin_token()
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $raw = is_file(FG_ADMIN_TOKEN_FILE) ? @file_get_contents(FG_ADMIN_TOKEN_FILE) : false;
    if (is_string($raw) && preg_match('/^[a-f0-9]{32}$/', trim($raw))) {
        $token = trim($raw);
        return $token;
    }
    $token = bin2hex(random_bytes(16));
    if (!is_dir(FG_DATA_DIR)) {
        @mkdir(FG_DATA_DIR, 0755, true);
    }
    if (@file_put_contents(FG_ADMIN_TOKEN_FILE, $token . "\n") === false) {
        fg_log('could not store the admin token');
        $token = '';
        return $token;
    }
    @chmod(FG_ADMIN_TOKEN_FILE, 0640);
    return $token;
}

function fg_is_admin_request()
{
    $given = isset($_GET['admin']) ? (string) $_GET['admin'] : '';
    if ($given === '' || !preg_match('/^[a-f0-9]{32}$/', $given)) {
        return false;
    }
    $token = fg_admin_token();
    return $token !== '' && hash_equals($token, $given);
}

/* ---------------------------------------------------------------- throttling */

/**
 * A per-IP ceiling on photos per hour. Not a security boundary (the album has
 * no password on purpose) — it only keeps a one-line curl loop from filling the
 * account before anyone notices. Never blocks because its own file is broken.
 */
function fg_rate_allows($photos)
{
    if (FG_RATE_MAX_PHOTOS <= 0 || $photos <= 0) {
        return true;
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key = substr(hash('sha256', $ip), 0, 16);
    $now = time();
    $handle = @fopen(FG_RATE_FILE, 'c+');
    if ($handle === false) {
        return true;
    }
    $allowed = true;
    if (flock($handle, LOCK_EX)) {
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : array();
        $buckets = is_array($decoded) ? $decoded : array();
        $fresh = array();
        foreach ($buckets as $bucketKey => $bucket) {
            if (is_array($bucket) && isset($bucket['until']) && (int) $bucket['until'] > $now) {
                $fresh[$bucketKey] = $bucket;
            }
        }
        $bucket = isset($fresh[$key]) ? $fresh[$key] : array('count' => 0, 'until' => $now + FG_RATE_WINDOW);
        if ((int) $bucket['count'] + $photos > FG_RATE_MAX_PHOTOS) {
            $allowed = false;
        } else {
            $bucket['count'] = (int) $bucket['count'] + $photos;
            $fresh[$key] = $bucket;
            @ftruncate($handle, 0);
            @rewind($handle);
            @fwrite($handle, (string) json_encode($fresh));
            @fflush($handle);
        }
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return $allowed;
}

function fg_store_read()
{
    $raw = is_file(FG_STORE_FILE) ? @file_get_contents(FG_STORE_FILE) : false;
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        // The captions, authors and dates live only in this file: move the
        // broken copy aside instead of letting the next write overwrite it.
        $target = FG_STORE_FILE . '.corrupt-' . gmdate('Ymd-His');
        @rename(FG_STORE_FILE, $target);
        fg_log('photos.json unreadable, quarantined as ' . basename($target));
        return array();
    }
    return $list;
}

/** Returns false instead of exiting: the caller owns the files to roll back. */
function fg_store_write($list)
{
    $json = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fg_log('json_encode failed while writing the store');
        return false;
    }
    $tmp = FG_STORE_FILE . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json . "\n") === false) {
        fg_log('could not write ' . basename($tmp));
        return false;
    }
    @chmod($tmp, 0640);
    // Captions, authors and dates live only in this file, and section 6 of
    // DEPLOY.md tells Felipe to edit it by hand: keep the last good copy.
    if (is_file(FG_STORE_FILE)) {
        @copy(FG_STORE_FILE, FG_STORE_FILE . '.bak');
    }
    if (!@rename($tmp, FG_STORE_FILE)) {
        @unlink($tmp);
        fg_log('could not rename the store temp file into place');
        return false;
    }
    return true;
}

/* ------------------------------------------------- rollback of written files */

/**
 * Files already on disk for a record that is not in the index yet. If the index
 * write fails, these are deleted instead of being left behind as orphans.
 */
function fg_cleanup_register($paths)
{
    if (!isset($GLOBALS['fg_pending_files'])) {
        $GLOBALS['fg_pending_files'] = array();
    }
    foreach ((array) $paths as $path) {
        $GLOBALS['fg_pending_files'][] = $path;
    }
}

function fg_cleanup_clear()
{
    $GLOBALS['fg_pending_files'] = array();
}

function fg_cleanup_run()
{
    if (empty($GLOBALS['fg_pending_files'])) {
        return;
    }
    foreach ($GLOBALS['fg_pending_files'] as $path) {
        fg_unlink_quietly($path);
    }
    fg_log('rolled back ' . count($GLOBALS['fg_pending_files']) . ' file(s) after a failed index write');
    fg_cleanup_clear();
}

/**
 * Read-modify-write under an exclusive lock on a separate lock file.
 *
 * The lock cannot live on photos.json itself: the write replaces that file by
 * rename(), so two writers would end up holding locks on different inodes and
 * one of the batches would vanish.
 *
 * $callback receives the list by reference and returns
 * array('changed' => bool, 'value' => mixed).
 */
function fg_store_mutate($callback, $soft = false)
{
    fg_ensure_dirs();
    $handle = @fopen(FG_LOCK_FILE, 'c');
    if ($handle === false) {
        fg_log('could not open the lock file');
        return fg_store_failed($soft, 'Não consegui salvar a lista de fotos aqui no servidor. Avisa o Felipe!');
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        fg_log('could not acquire the store lock');
        return fg_store_failed($soft, 'O álbum está ocupado recebendo outras fotos. Tenta de novo em alguns segundos?');
    }
    try {
        $list = fg_store_read();
        $outcome = call_user_func_array($callback, array(&$list));
        if (!is_array($outcome)) {
            $outcome = array('changed' => false, 'value' => $outcome);
        }
        if (!empty($outcome['changed'])) {
            if (!fg_store_write($list)) {
                return fg_store_failed($soft, 'Não consegui salvar a lista de fotos aqui no servidor. Avisa o Felipe!');
            }
            // Only a write that landed makes the caller's files worth keeping.
            // Clearing unconditionally would disarm the rollback on the paths
            // that change nothing (a duplicate, a full album), and the files
            // already on disk would have no record pointing at them.
            fg_cleanup_clear();
        }
        return isset($outcome['value']) ? $outcome['value'] : null;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Either a sentinel the caller handles, or the end of the request. */
function fg_store_failed($soft, $message)
{
    fg_cleanup_run();
    if ($soft) {
        return FG_STORE_FAILED;
    }
    fg_fail(500, $message);
    return FG_STORE_FAILED;
}

/* ------------------------------------------------------------ drive memory */

/**
 * Drive ids that must never be imported again.
 *
 * Without this, deleting an imported photo only frees it to come back on the
 * next cron run five minutes later — and the documented "zerar o álbum"
 * reset would re-import the whole folder.
 */
function fg_drive_tombstones()
{
    if (!is_file(FG_DRIVE_REMOVED_FILE)) {
        return array();
    }
    $lines = @file(FG_DRIVE_REMOVED_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return array();
    }
    $out = array();
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[$line] = true;
        }
    }
    return $out;
}

function fg_drive_tombstone_add($driveId)
{
    $driveId = trim((string) $driveId);
    if ($driveId === '' || preg_match('/^[A-Za-z0-9_-]{10,80}$/', $driveId) !== 1) {
        return;
    }
    @file_put_contents(FG_DRIVE_REMOVED_FILE, $driveId . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------------------------------------------------------------------- dto */

function fg_dto($record, $token)
{
    $id = isset($record['id']) ? (string) $record['id'] : '';
    $ext = isset($record['ext']) ? (string) $record['ext'] : 'jpg';
    $recordToken = isset($record['token']) ? (string) $record['token'] : '';
    return array(
        'id' => $id,
        'url' => 'uploads/display/' . $id . '.' . $ext,
        'thumbUrl' => 'uploads/thumb/' . $id . '.' . $ext,
        'width' => isset($record['width']) ? (int) $record['width'] : 0,
        'height' => isset($record['height']) ? (int) $record['height'] : 0,
        'caption' => isset($record['caption']) ? (string) $record['caption'] : '',
        'author' => isset($record['author']) ? (string) $record['author'] : '',
        'createdAt' => isset($record['createdAt']) ? (string) $record['createdAt'] : '',
        'mine' => $recordToken !== '' && $token !== '' && hash_equals($recordToken, $token),
    );
}

function fg_valid_id($id)
{
    return is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1;
}

function fg_clean_text($value, $maxLength)
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if ($value === null) {
        return ''; // invalid UTF-8 made preg_replace bail out
    }
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string) $value);
    if (function_exists('mb_strlen')) {
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            $value = mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return $value;
    }
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function fg_now_iso()
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function fg_unlink_quietly($path)
{
    if (is_string($path) && $path !== '' && is_file($path)) {
        @unlink($path);
    }
}

/** Every file of one record; built from the record, never from user input. */
function fg_record_files($record)
{
    if (!isset($record['id']) || !fg_valid_id($record['id'])) {
        return array();
    }
    $id = $record['id'];
    $ext = isset($record['ext']) ? preg_replace('/[^a-z0-9]/', '', (string) $record['ext']) : '';
    $originalExt = isset($record['originalExt']) ? preg_replace('/[^a-z0-9]/', '', (string) $record['originalExt']) : '';
    $files = array();
    if ($ext !== '') {
        $files[] = FG_DISPLAY_DIR . '/' . $id . '.' . $ext;
        $files[] = FG_THUMB_DIR . '/' . $id . '.' . $ext;
    }
    if ($originalExt !== '') {
        $files[] = FG_ORIGINAL_DIR . '/' . $id . '.' . $originalExt;
    }
    return $files;
}

/**
 * Deletes variant files no record points at. Runs at most once an hour and only
 * touches files older than the grace window, so a batch being written right now
 * is never swept.
 */
function fg_sweep_orphans()
{
    $now = time();
    $stamp = is_file(FG_SWEEP_STAMP) ? @filemtime(FG_SWEEP_STAMP) : false;
    if ($stamp !== false && $now - $stamp < FG_SWEEP_INTERVAL) {
        return 0;
    }
    if (@file_put_contents(FG_SWEEP_STAMP, (string) $now) === false) {
        return 0;
    }
    $known = array();
    foreach (fg_store_read() as $record) {
        if (isset($record['id']) && fg_valid_id($record['id'])) {
            $known[$record['id']] = true;
        }
    }
    $removed = 0;
    foreach (array(FG_ORIGINAL_DIR, FG_DISPLAY_DIR, FG_THUMB_DIR) as $dir) {
        $names = @scandir($dir);
        if (!is_array($names)) {
            continue;
        }
        foreach ($names as $name) {
            if (!preg_match('/^([a-f0-9]{32})\.[a-z0-9]{2,5}$/', $name, $match)) {
                continue; // .htaccess, .gitkeep, anything we did not write
            }
            if (isset($known[$match[1]])) {
                continue;
            }
            $path = $dir . '/' . $name;
            $mtime = @filemtime($path);
            if ($mtime === false || $now - $mtime < FG_ORPHAN_GRACE) {
                continue;
            }
            fg_unlink_quietly($path);
            $removed++;
        }
    }
    // A run killed by the OOM killer or the process limit never reaches its
    // own cleanup, so the half-downloaded file stays in data/ forever.
    $names = @scandir(FG_DATA_DIR);
    if (is_array($names)) {
        foreach ($names as $name) {
            if (preg_match('/^\.drive-[a-f0-9]{16}\.tmp$/', $name) !== 1) {
                continue;
            }
            $path = FG_DATA_DIR . '/' . $name;
            $mtime = @filemtime($path);
            if ($mtime === false || $now - $mtime < FG_ORPHAN_GRACE) {
                continue;
            }
            fg_unlink_quietly($path);
            $removed++;
        }
    }

    if ($removed > 0) {
        fg_log('sweep removed ' . $removed . ' orphan file(s)');
    }
    return $removed;
}
