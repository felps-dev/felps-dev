<?php
declare(strict_types=1);

/** Providers: the only code that touches the filesystem. */

function with_lock(callable $work)
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
    $handle = fopen(LOCK_FILE, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('could not lock the data folder');
    }
    try {
        return $work();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function read_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('corrupt file: ' . basename($path));
    }
    return $decoded;
}

function write_json_file(string $path, array $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $temp = $path . '.tmp';
    if ($json === false || file_put_contents($temp, $json) === false) {
        throw new RuntimeException('could not write ' . basename($path));
    }
    if (is_file($path)) {
        copy($path, $path . '.bak');
    }
    rename($temp, $path);
}
