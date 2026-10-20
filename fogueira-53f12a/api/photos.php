<?php
/** GET: the album, newest first. DELETE: one photo, by its own uploader. */
require __DIR__ . '/lib.php';

fg_bootstrap();
$method = fg_require_method(array('GET', 'HEAD', 'DELETE'));
$token = fg_uploader_token();

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    // Validated before it can reach any path: the only ids we ever wrote are
    // 32 hex characters from random_bytes().
    if (!fg_valid_id($id)) {
        fg_fail(400, 'Esse endereço de foto não é válido.');
    }

    // Felipe's own key, for the photo nobody else can take down (the uploader
    // closed the browser, or it was never hers). It lives in data/, never here:
    // this file is published in a public repo. See DEPLOY.md section 6.
    $isAdmin = fg_is_admin_request();

    $outcome = fg_store_mutate(function (&$list) use ($id, $token, $isAdmin) {
        foreach ($list as $index => $record) {
            if (!isset($record['id']) || $record['id'] !== $id) {
                continue;
            }
            $recordToken = isset($record['token']) ? (string) $record['token'] : '';
            if (!$isAdmin && ($recordToken === '' || !hash_equals($recordToken, $token))) {
                return array('changed' => false, 'value' => array('status' => 'forbidden'));
            }
            array_splice($list, $index, 1);
            return array('changed' => true, 'value' => array('status' => 'deleted', 'record' => $record));
        }
        return array('changed' => false, 'value' => array('status' => 'missing'));
    });

    if ($outcome['status'] === 'missing') {
        fg_fail(404, 'Essa foto não está mais aqui.');
    }
    if ($outcome['status'] === 'forbidden') {
        fg_fail(403, 'Só quem enviou a foto pode apagá-la.');
    }
    foreach (fg_record_files($outcome['record']) as $path) {
        fg_unlink_quietly($path);
    }
    // An imported photo that is deleted must stay deleted: the Drive sync
    // decides what is new by what is in the album, so without this the next
    // cron run brings it straight back.
    if (!empty($outcome['record']['driveId'])) {
        fg_drive_tombstone_add($outcome['record']['driveId']);
    }
    if ($isAdmin) {
        fg_log('admin deleted ' . $id);
    }
    fg_send(200, array('ok' => true));
}

$list = fg_store_read();
$photos = array();
$authors = array();
foreach (array_reverse($list) as $record) {
    $photos[] = fg_dto($record, $token);
    $author = isset($record['author']) ? (string) $record['author'] : '';
    if ($author !== '' && !in_array($author, $authors, true)) {
        $authors[] = $author;
    }
}

fg_send(200, array(
    'photos' => $photos,
    'stats' => array('count' => count($photos), 'authors' => $authors),
));
