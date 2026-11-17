<?php
declare(strict_types=1);

/**
 * Contador de downloads do /gtavitrend.
 *
 * POST {"res":1440,"first":true}  -> +1 (204). Chamado via sendBeacon depois de cada download.
 * GET  ?key=SEGREDO               -> painel com os números (só pra mim).
 * GET  ?key=SEGREDO&format=json   -> os mesmos números em JSON.
 *
 * Não guarda IP nem nada pessoal: só contagens.
 */

date_default_timezone_set('America/Sao_Paulo');

const DATA_DIR = __DIR__ . '/../data';
const STATS_FILE = DATA_DIR . '/stats.json';
const LOCK_FILE = DATA_DIR . '/stats.lock';
// sha256 da chave de leitura (a chave em si não fica no repo, que é público)
const KEY_SHA256 = 'd56a03c7364f00c01023bf9d4620bc9907d4339ca2d1d4023e97c95ffecdb354';
const RESOLUTIONS = [720, 1080, 1440, 2160];
const RES_LABELS = [720 => '720p', 1080 => '1080p', 1440 => '2K', 2160 => '4K'];

function empty_stats(): array
{
    return ['total' => 0, 'people' => 0, 'by_day' => [], 'by_res' => [], 'first_at' => null, 'last_at' => null];
}

function read_stats(): array
{
    if (!is_file(STATS_FILE)) {
        return empty_stats();
    }
    $data = json_decode((string) file_get_contents(STATS_FILE), true);
    return is_array($data) ? $data + empty_stats() : empty_stats();
}

function write_stats(array $stats): void
{
    $json = json_encode($stats, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $temp = STATS_FILE . '.tmp';
    if ($json === false || file_put_contents($temp, $json) === false || !rename($temp, STATS_FILE)) {
        throw new RuntimeException('could not write stats');
    }
}

function with_lock(callable $work)
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
    $handle = fopen(LOCK_FILE, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('could not lock');
    }
    try {
        return $work();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function record(int $res, bool $first): void
{
    with_lock(function () use ($res, $first): void {
        $s = read_stats();
        $now = date('c');
        $day = date('Y-m-d');
        $s['total']++;
        if ($first) {
            $s['people']++;
        }
        $s['by_day'][$day] = ($s['by_day'][$day] ?? 0) + 1;
        $s['by_res'][(string) $res] = ($s['by_res'][(string) $res] ?? 0) + 1;
        $s['first_at'] = $s['first_at'] ?? $now;
        $s['last_at'] = $now;
        write_stats($s);
    });
}

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function render_panel(array $s): void
{
    header('Content-Type: text/html; charset=utf-8');
    $today = $s['by_day'][date('Y-m-d')] ?? 0;
    $days = $s['by_day'];
    krsort($days);
    $max = max(array_merge([1], array_values($days)));
    $last = $s['last_at'] ? date('d/m H:i', strtotime($s['last_at'])) : '—';
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Downloads · gtavitrend</title><style>'
        . 'body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#160b29;color:#fff;padding:24px 18px}'
        . '.w{max-width:520px;margin:0 auto}.big{font-size:72px;font-weight:900;line-height:1;margin:6px 0;'
        . 'background:linear-gradient(100deg,#4b5bdb,#a14fd6 28%,#ff4fa3 58%,#ff8f4a 84%,#ffc65c);-webkit-background-clip:text;background-clip:text;color:transparent}'
        . '.lbl{color:#b49cc6;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em}'
        . '.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:22px 0}'
        . '.card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:12px}'
        . '.card b{display:block;font-size:26px;font-weight:900;margin-top:4px}'
        . 'table{width:100%;border-collapse:collapse;font-size:14px;table-layout:fixed}td.b{padding-right:12px}td{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08)}'
        . 'td.n{text-align:right;font-weight:800;width:56px}.bar{height:8px;border-radius:4px;background:linear-gradient(90deg,#ff4fa3,#ff8f4a)}'
        . 'h2{font-size:13px;color:#b49cc6;text-transform:uppercase;letter-spacing:.1em;margin:26px 0 8px}</style></head><body><div class="w">';
    echo '<div class="lbl">Downloads</div><div class="big">' . number_format((int) $s['total'], 0, ',', '.') . '</div>';
    echo '<div class="grid">'
        . '<div class="card"><span class="lbl">Pessoas</span><b>' . number_format((int) $s['people'], 0, ',', '.') . '</b></div>'
        . '<div class="card"><span class="lbl">Hoje</span><b>' . number_format((int) $today, 0, ',', '.') . '</b></div>'
        . '<div class="card"><span class="lbl">Último</span><b style="font-size:18px">' . h($last) . '</b></div></div>';
    echo '<h2>Por resolução</h2><table>';
    foreach (RESOLUTIONS as $r) {
        echo '<tr><td>' . RES_LABELS[$r] . '</td><td class="n">' . (int) ($s['by_res'][(string) $r] ?? 0) . '</td></tr>';
    }
    echo '</table><h2>Por dia</h2><table>';
    foreach ($days as $day => $n) {
        $pct = round($n / $max * 100);
        echo '<tr><td style="width:70px">' . h(date('d/m', strtotime($day))) . '</td>'
            . '<td class="b"><div class="bar" style="width:' . $pct . '%"></div></td><td class="n">' . (int) $n . '</td></tr>';
    }
    if (!$days) {
        echo '<tr><td>Nenhum download ainda.</td></tr>';
    }
    echo '</table></div></body></html>';
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        $body = json_decode((string) file_get_contents('php://input', false, null, 0, 1024), true);
        $res = is_array($body) ? (int) ($body['res'] ?? 0) : 0;
        if (!in_array($res, RESOLUTIONS, true)) {
            http_response_code(400);
            return;
        }
        record($res, !empty($body['first']));
        http_response_code(204);
        return;
    }

    $key = (string) ($_GET['key'] ?? '');
    if ($method !== 'GET' || $key === '' || !hash_equals(KEY_SHA256, hash('sha256', $key))) {
        http_response_code(404);
        return;
    }
    $stats = with_lock('read_stats');
    if (($_GET['format'] ?? '') === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stats, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }
    render_panel($stats);
} catch (Throwable $e) {
    http_response_code(500);
}
