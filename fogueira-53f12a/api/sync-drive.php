<?php
/**
 * Imports new photos from the shared Google Drive folder.
 *
 * The group kept using the Drive folder they already had, so the album pulls
 * from it instead of asking anyone to change habits. No Google account, no API
 * key, no OAuth: the folder is shared publicly and two unauthenticated
 * endpoints are enough.
 *
 *   list     drive.google.com/embeddedfolderview?id=<folder>#list
 *            Plain HTML, no JavaScript, and — unlike the regular folder page,
 *            which stops at 50 and lazy-loads the rest — it returns every
 *            entry in one response.
 *
 *   download drive.google.com/thumbnail?id=<file>&sz=w2000
 *            Google re-encodes to JPEG at the size asked for, HEIC included.
 *            That is what makes this work at all: GD here has no libheif, so a
 *            raw .heic from an iPhone can never be decoded server side.
 *
 * Run it from cron (CLI, no token) or from the browser with ?admin=<token>.
 * Each run imports a few photos and stops; the next run continues where this
 * one left off, so a shared host's time limit can never leave a half state.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/image.php';

define('FG_DRIVE_CONFIG', FG_DATA_DIR . '/drive-folder.txt');
define('FG_SYNC_STAMP', FG_DATA_DIR . '/drive-sync.stamp');
define('FG_SYNC_LOCK', FG_DATA_DIR . '/drive-sync.lock');
define('FG_SYNC_FAILURES', FG_DATA_DIR . '/drive-failures.json');

// After this many consecutive failures an entry is parked. Without it, one
// file Drive refuses to serve is retried first on every single run, burns the
// whole budget, and the album silently stops filling.
define('FG_SYNC_MAX_FAILURES', 3);

// A run is bounded by both, whichever comes first. The time budget is what
// actually protects us: max_execution_time is 90 s here and one photo can be
// two downloads plus a GD resize.
define('FG_SYNC_MAX_PER_RUN', 8);
define('FG_SYNC_TIME_BUDGET', 45);

// The folder page is cheap; a photo is not. Keep the timeouts far below the
// execution limit so a stalled download cannot eat the whole run.
define('FG_SYNC_LIST_TIMEOUT', 20);
define('FG_SYNC_FILE_TIMEOUT', 60);

define('FG_SYNC_AUTHOR', 'Google Drive');
define('FG_SYNC_UA', 'Mozilla/5.0 (compatible; album-fogueira/1.0)');

$isCli = PHP_SAPI === 'cli';

// header() is a no-op under the CLI SAPI, so the same bootstrap gives cron the
// error handlers and the output buffer that keep the JSON clean.
fg_bootstrap();
if (!$isCli) {
    fg_require_method(array('GET', 'POST'));
    if (!fg_is_admin_request()) {
        fg_fail(403, 'Essa página é só do Felipe.');
    }
}

/** A name for the report only; never used to build a path. */
function fg_sync_label($name)
{
    $name = fg_clean_text(basename((string) $name), 120);
    return $name === '' ? 'arquivo sem nome' : $name;
}

/** The folder id lives on the server: the deploy repo is public. */
function fg_drive_folder_id()
{
    if (!is_file(FG_DRIVE_CONFIG)) {
        return '';
    }
    $raw = trim((string) @file_get_contents(FG_DRIVE_CONFIG));
    // Accept a bare id or a pasted folder URL.
    if (preg_match('#/folders/([A-Za-z0-9_-]{10,80})#', $raw, $found)) {
        return $found[1];
    }
    return preg_match('/^[A-Za-z0-9_-]{10,80}$/', $raw) === 1 ? $raw : '';
}

/** One GET, following redirects, with the body in memory or on disk. */
function fg_drive_get($url, $timeout, $toPath = null, $maxBytes = FG_MAX_FILE_BYTES)
{
    $handle = curl_init($url);
    if ($handle === false) {
        return array('ok' => false, 'status' => 0, 'error' => 'curl indisponível');
    }

    $sink = null;
    $options = array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => max(5, (int) $timeout),
        CURLOPT_USERAGENT => FG_SYNC_UA,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        // Drive serves whatever is in the folder, including a 400 MB video
        // nobody meant to send. Content-Length is not always there, so the
        // ceiling is enforced on bytes actually received.
        CURLOPT_MAXFILESIZE => $maxBytes,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($resource, $expected, $received) use ($maxBytes) {
            return ($expected > $maxBytes || $received > $maxBytes) ? 1 : 0;
        },
    );

    if ($toPath === null) {
        $options[CURLOPT_RETURNTRANSFER] = true;
    } else {
        $sink = @fopen($toPath, 'wb');
        if ($sink === false) {
            curl_close($handle);
            return array('ok' => false, 'status' => 0, 'error' => 'não consegui escrever o arquivo temporário');
        }
        $options[CURLOPT_FILE] = $sink;
    }

    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($sink !== null) {
        fclose($sink);
    }

    if ($body === false && $toPath === null) {
        return array('ok' => false, 'status' => $status, 'error' => $error !== '' ? $error : 'falha na conexão');
    }
    if ($error !== '') {
        if ($toPath !== null) {
            fg_unlink_quietly($toPath);
        }
        $tooBig = stripos($error, 'maxfilesize') !== false
            || stripos($error, 'callback') !== false
            || stripos($error, 'aborted') !== false;
        return array('ok' => false, 'status' => $status, 'error' => $tooBig ? 'arquivo grande demais' : $error);
    }
    if ($status !== 200) {
        return array('ok' => false, 'status' => $status, 'error' => 'HTTP ' . $status);
    }

    return array('ok' => true, 'status' => $status, 'body' => $toPath === null ? $body : '');
}

/**
 * Every entry in the folder, as array(id => name).
 *
 * The markup is Google's and can change without notice; a parse that suddenly
 * finds nothing is reported as an error, never as "the folder is empty" —
 * otherwise a silent breakage would look like a quiet, working sync.
 */
function fg_drive_list($folderId)
{
    $url = 'https://drive.google.com/embeddedfolderview?id=' . rawurlencode($folderId) . '#list';
    $response = fg_drive_get($url, FG_SYNC_LIST_TIMEOUT, null, 8 * 1024 * 1024);
    if (empty($response['ok'])) {
        return array('ok' => false, 'message' => 'Não consegui abrir a pasta do Drive (' . $response['error'] . ').');
    }

    $html = $response['body'];
    $found = array();
    if (preg_match_all(
        '#id="entry-([A-Za-z0-9_-]{10,80})".*?<div class="flip-entry-title">([^<]{1,200})</div>#s',
        $html,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $match) {
            $found[$match[1]] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
        }
    }

    if (count($found) === 0) {
        $shared = strpos($html, 'flip-entry') !== false;
        return array('ok' => false, 'message' => $shared
            ? 'A pasta abriu mas não reconheci a lista de arquivos — o Google deve ter mudado a página.'
            : 'A pasta não abriu como pública. Confere o compartilhamento ("qualquer pessoa com o link").');
    }

    return array('ok' => true, 'files' => $found);
}

/** Only what GD can open, and only by what Google actually sends us. */
function fg_drive_fetch_photo($fileId, $name, $tmpPath, $timeout = FG_SYNC_FILE_TIMEOUT)
{
    // The thumbnail endpoint always answers JPEG, which is the whole point:
    // it is the only way a HEIC becomes something GD can read.
    $url = 'https://drive.google.com/thumbnail?id=' . rawurlencode($fileId) . '&sz=w' . FG_DISPLAY_MAX_SIDE;
    $response = fg_drive_get($url, $timeout, $tmpPath, FG_MAX_FILE_BYTES);
    if (empty($response['ok'])) {
        return array('ok' => false, 'message' => 'não consegui baixar do Drive (' . $response['error'] . ')');
    }
    if (!is_file($tmpPath) || filesize($tmpPath) < 1024) {
        return array('ok' => false, 'message' => 'o Drive devolveu um arquivo vazio');
    }

    $inspection = fg_inspect_image($tmpPath, $name);
    if (empty($inspection['ok'])) {
        return array('ok' => false, 'message' => $inspection['message']);
    }
    return array('ok' => true, 'inspection' => $inspection);
}

/**
 * The untouched bytes, for the archive copy only — never served.
 *
 * Downloaded to a temp path and only then moved into uploads/original: a
 * refused download must never leave a partial file where the album believes
 * the archive copy lives.
 */
function fg_drive_fetch_original($fileId, $finalPath, $timeout)
{
    $tmpPath = FG_DATA_DIR . '/.drive-' . bin2hex(random_bytes(8)) . '.tmp';
    $url = 'https://drive.usercontent.google.com/download?id=' . rawurlencode($fileId) . '&export=download';
    $response = fg_drive_get($url, $timeout, $tmpPath, FG_MAX_FILE_BYTES);

    if (empty($response['ok']) || !is_file($tmpPath) || filesize($tmpPath) < 1024) {
        fg_unlink_quietly($tmpPath);
        return false;
    }

    // Drive answers 200 with an HTML page for the virus-scan confirmation and
    // for its own download quota. A few KB of HTML clears every size check, so
    // without this the album records an "original" that is an error page.
    $mime = fg_finfo_mime($tmpPath);
    $head = (string) @file_get_contents($tmpPath, false, null, 0, 64);
    if (stripos((string) $mime, 'text/') === 0
        || stripos($head, '<!DOCTYPE') !== false
        || stripos($head, '<html') !== false) {
        fg_unlink_quietly($tmpPath);
        fg_log('drive sync: original refused, Drive answered a page for ' . $fileId);
        return false;
    }

    if (!@rename($tmpPath, $finalPath)) {
        fg_unlink_quietly($tmpPath);
        return false;
    }
    return true;
}

/**
 * The archive filename comes from a name someone typed in Drive, so it is a
 * whitelist, not a filter: "foto.php" must not become <id>.php on disk, even
 * with the uploads/ .htaccess turning the engine off.
 */
function fg_drive_extension($name)
{
    $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'hif', 'avif', 'tif', 'tiff');
    $suffix = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    return in_array($suffix, $allowed, true) ? $suffix : 'bin';
}

/** driveId => consecutive failures, so a broken entry stops eating every run. */
function fg_sync_failures()
{
    if (!is_file(FG_SYNC_FAILURES)) {
        return array();
    }
    $decoded = json_decode((string) @file_get_contents(FG_SYNC_FAILURES), true);
    return is_array($decoded) ? $decoded : array();
}

function fg_sync_note_failure(&$failures, $fileId, $message)
{
    $count = isset($failures[$fileId]['count']) ? (int) $failures[$fileId]['count'] : 0;
    $failures[$fileId] = array('count' => $count + 1, 'last' => substr((string) $message, 0, 200));
    fg_log('drive sync: ' . $fileId . ' falhou (' . ($count + 1) . 'x): ' . substr((string) $message, 0, 160));
}

function fg_sync_failures_write($failures)
{
    @file_put_contents(FG_SYNC_FAILURES, json_encode($failures), LOCK_EX);
}

function fg_sync_finish($payload)
{
    @touch(FG_SYNC_STAMP);
    fg_send(empty($payload['ok']) ? 500 : 200, $payload);
}

/* ------------------------------------------------------------------- run */

$startedAt = time();
fg_ensure_dirs();

// A run is mostly time blocked in curl, which PHP's execution timer does not
// count, and under CLI there is no limit at all — so a slow Drive makes the
// 5-minute cron overlap itself. Overlapping runs walk the identical pending
// list and re-download the same photos.
$lockHandle = @fopen(FG_SYNC_LOCK, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if ($lockHandle !== false) {
        fclose($lockHandle);
    }
    fg_sync_finish(array('ok' => true, 'imported' => 0, 'skipped' => 'já tem uma sincronização rodando'));
}
fg_raise_memory_limit();
@set_time_limit(FG_SYNC_TIME_BUDGET + 60);
@ignore_user_abort(true);

$folderId = fg_drive_folder_id();
if ($folderId === '') {
    fg_sync_finish(array(
        'ok' => false,
        'error' => 'Nenhuma pasta configurada. Escreve o id (ou a URL) da pasta do Drive em data/drive-folder.txt.',
    ));
}

if (!function_exists('curl_init')) {
    fg_sync_finish(array('ok' => false, 'error' => 'A extensão curl do PHP não está ligada neste host.'));
}

$listing = fg_drive_list($folderId);
if (empty($listing['ok'])) {
    fg_log('drive sync: ' . $listing['message']);
    fg_sync_finish(array('ok' => false, 'error' => $listing['message']));
}

$known = fg_drive_tombstones();
$albumSize = 0;
foreach (fg_store_read() as $record) {
    $albumSize++;
    if (!empty($record['driveId'])) {
        $known[(string) $record['driveId']] = true;
    }
}

$failures = fg_sync_failures();
$pending = array();
$parked = 0;
foreach ($listing['files'] as $fileId => $name) {
    if (isset($known[$fileId])) {
        continue;
    }
    if (isset($failures[$fileId]['count']) && $failures[$fileId]['count'] >= FG_SYNC_MAX_FAILURES) {
        $parked++;
        continue;
    }
    $pending[$fileId] = $name;
}

$imported = array();
$errors = array();
$stopped = '';

if (count($pending) > 0) {
    $albumBytes = fg_album_bytes();
    $freeBytes = fg_free_bytes();
    if ($albumBytes >= FG_ALBUM_MAX_BYTES || ($freeBytes >= 0 && $freeBytes < FG_MIN_FREE_BYTES)) {
        fg_log('drive sync: out of space album=' . $albumBytes . ' free=' . $freeBytes);
        fg_sync_finish(array('ok' => false, 'error' => FG_NO_SPACE));
    }
    $keepOriginals = $albumBytes < FG_ORIGINAL_BUDGET_BYTES;

    foreach ($pending as $fileId => $name) {
        if (count($imported) >= FG_SYNC_MAX_PER_RUN) {
            $stopped = 'limite de ' . FG_SYNC_MAX_PER_RUN . ' fotos por execução';
            break;
        }
        if (time() - $startedAt >= FG_SYNC_TIME_BUDGET) {
            $stopped = 'tempo da execução';
            break;
        }
        if ($albumSize >= FG_MAX_PHOTOS) {
            $stopped = 'álbum no limite de fotos';
            break;
        }

        $label = fg_sync_label($name);
        $tmpPath = FG_DATA_DIR . '/.drive-' . bin2hex(random_bytes(8)) . '.tmp';

        // One entry must never be able to overrun the whole run: the download
        // gets what is left of the budget, not a flat minute.
        $left = FG_SYNC_TIME_BUDGET - (time() - $startedAt);
        $fetched = fg_drive_fetch_photo($fileId, $name, $tmpPath, min(FG_SYNC_FILE_TIMEOUT, max(10, $left)));
        if (empty($fetched['ok'])) {
            fg_unlink_quietly($tmpPath);
            fg_sync_note_failure($failures, $fileId, $fetched['message']);
            $errors[] = array('file' => $label, 'message' => $fetched['message']);
            continue;
        }

        $inspection = $fetched['inspection'];
        if (!fg_fits_in_memory($inspection['width'], $inspection['height'], fg_will_rotate($tmpPath, $inspection['type']))) {
            fg_unlink_quietly($tmpPath);
            $errors[] = array('file' => $label, 'message' => fg_too_big_message($inspection['width'], $inspection['height']));
            continue;
        }

        $id = bin2hex(random_bytes(16));
        $rendered = null;
        $originalExt = '';
        try {
            $rendered = fg_render_variants($tmpPath, $id, $inspection['type']);
            fg_unlink_quietly($tmpPath);

            // Re-checked per photo, not once per run: eight archive copies
            // are up to 200 MB against a 2100 MB account shared with the rest
            // of felps.dev.
            $freeNow = fg_free_bytes();
            if ($keepOriginals && ($freeNow < 0 || $freeNow > FG_MIN_FREE_BYTES + FG_MAX_FILE_BYTES)) {
                $candidate = fg_drive_extension($name);
                $originalPath = FG_ORIGINAL_DIR . '/' . $id . '.' . $candidate;
                $left = FG_SYNC_TIME_BUDGET - (time() - $startedAt);
                if (fg_drive_fetch_original($fileId, $originalPath, min(FG_SYNC_FILE_TIMEOUT, max(10, $left)))) {
                    @chmod($originalPath, 0644);
                    $originalExt = $candidate;
                }
            }

            $record = array(
                'id' => $id,
                'ext' => $rendered['ext'],
                'originalExt' => $originalExt,
                'width' => $rendered['width'],
                'height' => $rendered['height'],
                'caption' => '',
                'author' => FG_SYNC_AUTHOR,
                'createdAt' => fg_now_iso(),
                // No uploader token: nobody owns a Drive import, so the delete
                // button never shows and only the admin key can remove it.
                'token' => '',
                'driveId' => (string) $fileId,
                'driveName' => fg_clean_text($name, 120),
            );

            fg_cleanup_register(fg_record_files($record));
            $outcome = fg_store_mutate(function (&$list) use ($record) {
                // Re-checked under the lock: another run (or an upload) may
                // have added this very file while this one was downloading.
                foreach ($list as $existing) {
                    if (!empty($existing['driveId']) && $existing['driveId'] === $record['driveId']) {
                        return array('changed' => false, 'value' => 'duplicate');
                    }
                }
                if (count($list) >= FG_MAX_PHOTOS) {
                    return array('changed' => false, 'value' => 'full');
                }
                $list[] = $record;
                return array('changed' => true, 'value' => 'ok');
            }, true);

            if ($outcome !== 'ok') {
                // fg_store_mutate leaves the rollback armed when it changed
                // nothing, so these are the files this iteration just wrote.
                fg_cleanup_run();
                if ($outcome === 'duplicate') {
                    continue;
                }
                $errors[] = array('file' => $label, 'message' => 'não consegui salvar no álbum (' . $outcome . ')');
                if ($outcome === 'full') {
                    $stopped = 'álbum no limite de fotos';
                    break;
                }
                continue;
            }

            fg_cleanup_clear();
            unset($failures[$fileId]);
            $albumSize++;
            $imported[] = array('file' => $label, 'id' => $id, 'driveId' => $fileId);
        } catch (Exception $error) {
            fg_cleanup_clear();
            fg_unlink_quietly($tmpPath);
            if ($rendered !== null) {
                foreach ($rendered['files'] as $path) {
                    fg_unlink_quietly($path);
                }
            }
            fg_sync_note_failure($failures, $fileId, $error->getMessage());
            $errors[] = array('file' => $label, 'message' => $error->getMessage());
        }
    }

    fg_sync_failures_write($failures);
}

fg_sweep_orphans();

$remaining = max(0, count($pending) - count($imported));
fg_log('drive sync: ' . count($imported) . ' importadas, ' . $remaining . ' restantes, ' . count($errors) . ' erros');

fg_sync_finish(array(
    'ok' => true,
    'folder' => $folderId,
    'inDrive' => count($listing['files']),
    'imported' => count($imported),
    'remaining' => $remaining,
    'parked' => $parked,
    'photos' => $imported,
    'errors' => $errors,
    'stopped' => $stopped,
));
