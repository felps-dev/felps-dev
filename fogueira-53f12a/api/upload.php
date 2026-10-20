<?php
/**
 * Receives a batch of photos: keeps the original untouched, writes a display
 * and a thumb copy, and appends one record per photo to the store.
 *
 * One bad file never fails the batch: it comes back in "errors" instead.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/image.php';

fg_bootstrap();
fg_require_method(array('POST'));
$token = fg_uploader_token();
fg_ensure_dirs();

/**
 * The per-file ceiling that is really in force: ours, or the host's if it is
 * lower. UPLOAD_ERR_INI_SIZE means "past the server's limit", whatever it is.
 */
function fg_upload_cap_mb()
{
    $cap = fg_memory_bytes(ini_get('upload_max_filesize'));
    if ($cap <= 0 || $cap > FG_MAX_FILE_BYTES) {
        $cap = FG_MAX_FILE_BYTES;
    }
    return max(1, (int) round($cap / 1048576));
}

/** PHP's own per-file failures, in words the uploader can act on. */
function fg_upload_error_message($code)
{
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return 'Essa foto passou do limite de ' . fg_upload_cap_mb() . ' MB por foto. Manda uma versão menor (compartilhar pelo WhatsApp já reduz).';
    }
    if ($code === UPLOAD_ERR_PARTIAL) {
        return 'O envio dessa foto foi interrompido no meio. Tenta de novo?';
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Esse arquivo chegou vazio aqui.';
    }
    if ($code === UPLOAD_ERR_NO_TMP_DIR || $code === UPLOAD_ERR_CANT_WRITE) {
        return 'O servidor não conseguiu guardar essa foto. Avisa o Felipe e tenta de novo em seguida.';
    }
    if ($code === UPLOAD_ERR_EXTENSION) {
        return 'O servidor recusou essa foto. Tenta mandar como JPG?';
    }
    return 'Não consegui receber essa foto. Tenta de novo?';
}

/** $_FILES['photos'] comes as parallel arrays for photos[]; flatten it. */
function fg_posted_files()
{
    if (!isset($_FILES['photos']) || !is_array($_FILES['photos'])) {
        return array();
    }
    $field = $_FILES['photos'];
    if (!isset($field['name'])) {
        return array();
    }
    if (!is_array($field['name'])) {
        return array(array(
            'name' => (string) $field['name'],
            'tmp_name' => isset($field['tmp_name']) ? (string) $field['tmp_name'] : '',
            'error' => isset($field['error']) ? (int) $field['error'] : UPLOAD_ERR_NO_FILE,
            'size' => isset($field['size']) ? (int) $field['size'] : 0,
        ));
    }
    $files = array();
    $count = count($field['name']);
    for ($index = 0; $index < $count; $index++) {
        $files[] = array(
            'name' => (string) $field['name'][$index],
            'tmp_name' => isset($field['tmp_name'][$index]) ? (string) $field['tmp_name'][$index] : '',
            'error' => isset($field['error'][$index]) ? (int) $field['error'][$index] : UPLOAD_ERR_NO_FILE,
            'size' => isset($field['size'][$index]) ? (int) $field['size'][$index] : 0,
        );
    }
    return $files;
}

/** One caption per file, same order; a single caption applies to all of them. */
function fg_posted_caption($index)
{
    if (!isset($_POST['caption'])) {
        return '';
    }
    $raw = $_POST['caption'];
    if (is_array($raw)) {
        $values = array_values($raw);
        return isset($values[$index]) ? fg_clean_text($values[$index], FG_CAPTION_MAX_LEN) : '';
    }
    return fg_clean_text($raw, FG_CAPTION_MAX_LEN);
}

/** A name only for the error list; never used to build a path. */
function fg_display_name($name)
{
    $name = fg_clean_text(basename((string) $name), 120);
    return $name === '' ? 'arquivo sem nome' : $name;
}

$files = fg_posted_files();

if (count($files) === 0) {
    // When the body is bigger than post_max_size, PHP drops $_POST and $_FILES
    // entirely and the request looks empty: say what actually happened.
    $declared = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $allowed = fg_memory_bytes(ini_get('post_max_size'));
    if ($allowed > 0 && $declared > $allowed) {
        fg_fail(413, 'Esse envio ficou grande demais de uma vez. Manda em duas levas, com menos fotos em cada uma.');
    }
    fg_fail(400, 'Nenhuma foto chegou aqui. Escolhe as fotos da galeria e tenta de novo.');
}

if (count($files) > FG_MAX_FILES) {
    fg_fail(400, 'Dá para enviar no máximo ' . FG_MAX_FILES . ' fotos de uma vez. Manda em duas levas que a gente junta tudo!');
}

$author = fg_clean_text(isset($_POST['author']) ? $_POST['author'] : '', FG_AUTHOR_MAX_LEN);
$saved = array();
$errors = array();

// max_file_uploads is PHP_INI_SYSTEM: .user.ini cannot raise it, and PHP drops
// the files past it WITHOUT any error. Say so instead of losing them silently.
$iniMaxFiles = (int) ini_get('max_file_uploads');
if ($iniMaxFiles > 0 && count($files) >= $iniMaxFiles) {
    $errors[] = array(
        'file' => 'esse envio',
        'message' => 'O servidor aceita no máximo ' . $iniMaxFiles . ' arquivos por envio, então pode ser que nem todas as fotos tenham chegado. Confere o álbum e manda o resto numa segunda leva!',
    );
}

if (!fg_rate_allows(count($files))) {
    fg_log('rate limited ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
    fg_fail(429, 'Muitas fotos de uma vez só daqui. Espera alguns minutos e manda o resto — tá tudo guardado.');
}

// The account is 2100 MB and the album shares it with the rest of felps.dev:
// stop before filling it, and drop the archive copy before dropping the album.
$albumBytes = fg_album_bytes();
$freeBytes = fg_free_bytes();
if ($albumBytes >= FG_ALBUM_MAX_BYTES || ($freeBytes >= 0 && $freeBytes < FG_MIN_FREE_BYTES)) {
    fg_log('out of space: album=' . $albumBytes . ' free=' . $freeBytes);
    fg_fail(507, FG_NO_SPACE);
}
$keepOriginals = $albumBytes < FG_ORIGINAL_BUDGET_BYTES;
if (!$keepOriginals) {
    fg_log('original archive paused: album=' . $albumBytes);
}
$albumFull = false;

foreach ($files as $index => $file) {
    // max_execution_time counts the whole request, so 20 photos had to fit in
    // one budget. Restarting the clock per photo makes it a per-photo budget.
    @set_time_limit(90);
    @ignore_user_abort(true);

    $name = fg_display_name($file['name']);
    $tmpPath = $file['tmp_name'];

    if ($albumFull) {
        fg_unlink_quietly($tmpPath);
        $errors[] = array('file' => $name, 'message' => 'O álbum chegou no limite de fotos do servidor. Avisa o Felipe!');
        continue;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = array('file' => $name, 'message' => fg_upload_error_message($file['error']));
        continue;
    }
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $errors[] = array('file' => $name, 'message' => 'Não consegui receber essa foto. Tenta de novo?');
        continue;
    }
    if ($file['size'] <= 0) {
        fg_unlink_quietly($tmpPath);
        $errors[] = array('file' => $name, 'message' => 'Esse arquivo chegou vazio aqui.');
        continue;
    }
    // Checked here too: .user.ini may be capped by the host, or ignored.
    if ($file['size'] > FG_MAX_FILE_BYTES) {
        fg_unlink_quietly($tmpPath);
        $errors[] = array('file' => $name, 'message' => 'Essa foto passou do limite de ' . fg_upload_cap_mb() . ' MB por foto. Manda uma versão menor (compartilhar pelo WhatsApp já reduz).');
        continue;
    }

    $inspection = fg_inspect_image($tmpPath, $file['name']);
    if (empty($inspection['ok'])) {
        fg_unlink_quietly($tmpPath);
        $errors[] = array('file' => $name, 'message' => $inspection['message']);
        continue;
    }
    $willRotate = fg_will_rotate($tmpPath, $inspection['type']);
    if (!fg_fits_in_memory($inspection['width'], $inspection['height'], $willRotate)) {
        fg_unlink_quietly($tmpPath);
        $errors[] = array('file' => $name, 'message' => fg_too_big_message($inspection['width'], $inspection['height']));
        continue;
    }

    $id = bin2hex(random_bytes(16));
    $originalExt = $inspection['ext'];
    $rendered = null;
    try {
        $rendered = fg_render_variants($tmpPath, $id, $inspection['type']);

        // Last step: the archive copy. It is never served over the web; it only
        // exists on disk for Felipe to download later — and it is the first
        // thing dropped when the account starts running out of room.
        if ($keepOriginals) {
            $originalPath = FG_ORIGINAL_DIR . '/' . $id . '.' . $originalExt;
            if (!@move_uploaded_file($tmpPath, $originalPath)) {
                throw new FgImageError('Não consegui guardar essa foto aqui no servidor. Avisa o Felipe e tenta de novo?');
            }
            @chmod($originalPath, 0644);
        } else {
            $originalExt = '';
            fg_unlink_quietly($tmpPath);
        }

        $record = array(
            'id' => $id,
            'ext' => $rendered['ext'],
            'originalExt' => $originalExt,
            'width' => $rendered['width'],
            'height' => $rendered['height'],
            'caption' => fg_posted_caption($index),
            'author' => $author,
            'createdAt' => fg_now_iso(),
            'token' => $token,
        );

        // Persisted one by one: a timeout or a fatal in the middle of a batch
        // then costs the photo being written, not the whole batch, and never
        // leaves a file on disk that no record points at.
        fg_cleanup_register(fg_record_files($record));
        $outcome = fg_store_mutate(function (&$list) use ($record) {
            if (count($list) >= FG_MAX_PHOTOS) {
                return array('changed' => false, 'value' => 'full');
            }
            $list[] = $record;
            return array('changed' => true, 'value' => 'ok');
        }, true);

        if ($outcome === 'full') {
            fg_cleanup_run();
            $albumFull = true;
            throw new FgImageError('O álbum chegou no limite de fotos do servidor. Avisa o Felipe!');
        }
        if ($outcome === FG_STORE_FAILED) {
            // fg_store_mutate already deleted this photo's files.
            fg_cleanup_clear();
            throw new FgImageError('Não consegui salvar a lista de fotos aqui no servidor. Avisa o Felipe!');
        }

        $saved[] = $record;
    } catch (Exception $error) {
        fg_cleanup_clear();
        if ($rendered !== null) {
            foreach ($rendered['files'] as $path) {
                fg_unlink_quietly($path);
            }
        }
        if ($originalExt !== '') {
            fg_unlink_quietly(FG_ORIGINAL_DIR . '/' . $id . '.' . $originalExt);
        }
        fg_unlink_quietly($tmpPath);
        fg_log('upload failed for ' . $name . ': ' . $error->getMessage());
        $message = $error instanceof FgImageError
            ? $error->getMessage()
            : 'Não consegui processar essa foto. Pode ser um formato que o celular salvou de um jeito diferente.';
        $errors[] = array('file' => $name, 'message' => $message);
    }
}

// Anything a crash left behind in an earlier request: at most once an hour.
fg_sweep_orphans();

$photos = array();
foreach ($saved as $record) {
    $photos[] = fg_dto($record, $token);
}

fg_send(201, array('photos' => $photos, 'errors' => $errors));
