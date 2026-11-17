<?php
declare(strict_types=1);

/**
 * Contador do /gtavitrend.
 *
 * POST {"type":"view"|"download", ...}  -> registra o evento (204). Mandado via sendBeacon pela página.
 * GET  ?key=SEGREDO                       -> painel (só pra mim).
 * GET  ?key=SEGREDO&format=json|csv       -> totais em JSON / todos os eventos em CSV.
 */

require __DIR__ . '/lib.php';

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function fmt(int $n): string
{
    return number_format($n, 0, ',', '.');
}

/** Junta evento + user-agent interpretado + geolocalização numa linha só. */
function enrich(array $e, array $geo): array
{
    $ua = parse_ua((string) ($e['ua'] ?? ''), !empty($e['touch']));
    $g = $geo[$e['ip'] ?? ''] ?? [];
    $place = implode(', ', array_filter([$g['city'] ?? '', $g['regionName'] ?? '']));
    return $e + [
        'source' => source_of($e, $ua),
        'device' => $ua['device'], 'os' => $ua['os'], 'browser' => $ua['browser'], 'bot' => $ua['bot'],
        'country' => ($g['country'] ?? '') ?: ($e['cf_country'] ?? ''),
        'place' => $place, 'isp' => $g['isp'] ?? '',
        'net' => isset($g['mobile']) ? ($g['hosting'] || $g['proxy'] ? 'VPN/datacenter' : ($g['mobile'] ? 'Dados móveis' : 'Wi-Fi/fixa')) : '',
        'who' => ($e['vid'] ?? '') !== '' ? $e['vid'] : (($e['ip'] ?? '') . '|' . ($e['ua'] ?? '')),
    ];
}

function tally(array $rows, callable $key): array
{
    $out = [];
    foreach ($rows as $r) {
        $k = (string) $key($r);
        if ($k === '') {
            $k = '—';
        }
        $out[$k] = ($out[$k] ?? 0) + 1;
    }
    arsort($out);
    return $out;
}

function bars(string $title, array $counts, int $limit = 10): string
{
    if (!$counts) {
        return '';
    }
    $max = max(array_merge([1], array_values($counts)));
    $sum = array_sum($counts);
    $html = '<section><h2>' . h($title) . '</h2><table>';
    foreach (array_slice($counts, 0, $limit, true) as $label => $n) {
        $html .= '<tr><td class="l">' . h($label) . '</td><td class="b"><div class="bar" style="width:' . round($n / $max * 100) . '%"></div></td>'
            . '<td class="n">' . fmt($n) . '</td><td class="p">' . round($n / max(1, $sum) * 100) . '%</td></tr>';
    }
    return $html . '</table></section>';
}

function render_panel(array $stats, array $rows): void
{
    $rows = array_values(array_filter($rows, fn ($r) => !$r['bot']));
    $views = array_values(array_filter($rows, fn ($r) => $r['type'] === 'view'));
    $dls = array_values(array_filter($rows, fn ($r) => $r['type'] === 'download'));
    $visitors = count(array_unique(array_column($views, 'who')));
    $dlPeople = count(array_unique(array_column($dls, 'who')));
    $conv = $visitors ? round($dlPeople / $visitors * 100) : 0;
    $today = date('Y-m-d');
    $todayDl = (int) ($stats['by_day'][$today] ?? 0);
    $todayViews = count(array_filter($views, fn ($r) => substr($r['t'], 0, 10) === $today));
    $since = $rows ? date('d/m H:i', strtotime($rows[0]['t'])) : null;
    $secs = array_column($dls, 'secs');
    sort($secs);
    $median = $secs ? $secs[intdiv(count($secs), 2)] : null;

    $days = [];
    $byDay = $stats['by_day'];
    krsort($byDay);
    foreach ($byDay as $day => $n) {
        $days[date('d/m', strtotime($day))] = $n;
    }
    $hours = array_fill(0, 24, 0);
    foreach ($dls as $r) {
        $hours[(int) date('G', strtotime($r['t']))]++;
    }
    $hourLabels = [];
    foreach ($hours as $hr => $n) {
        if ($n) {
            $hourLabels[sprintf('%02dh', $hr)] = $n;
        }
    }
    ksort($hourLabels);

    $srcViews = tally($views, fn ($r) => $r['source']);
    $srcDls = tally($dls, fn ($r) => $r['source']);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Painel · gtavitrend</title><style>'
        . 'body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#160b29;color:#fff;padding:24px 16px 40px}'
        . '.w{max-width:760px;margin:0 auto}.big{font-size:72px;font-weight:900;line-height:1;margin:6px 0;'
        . 'background:linear-gradient(100deg,#4b5bdb,#a14fd6 28%,#ff4fa3 58%,#ff8f4a 84%,#ffc65c);-webkit-background-clip:text;background-clip:text;color:transparent}'
        . '.lbl{color:#b49cc6;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em}'
        . '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:18px 0 6px}'
        . '.card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:12px}'
        . '.card b{display:block;font-size:24px;font-weight:900;margin-top:4px}.card small{color:#b49cc6;font-weight:600}'
        . '.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:0 28px}'
        . 'table{width:100%;border-collapse:collapse;font-size:13.5px;table-layout:fixed}td,th{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}'
        . 'td.l{width:42%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:8px}td.b{padding-right:10px}'
        . 'td.n{text-align:right;font-weight:800;width:48px}td.p{text-align:right;color:#b49cc6;width:40px}'
        . '.bar{height:8px;margin-top:5px;border-radius:4px;background:linear-gradient(90deg,#ff4fa3,#ff8f4a)}'
        . 'h2{font-size:12px;color:#b49cc6;text-transform:uppercase;letter-spacing:.1em;margin:26px 0 6px}'
        . '.log{overflow-x:auto}.log table{table-layout:auto;min-width:880px;font-size:12.5px}.log th{text-align:left;color:#b49cc6;font-weight:700;padding-right:12px}'
        . '.log td{padding-right:12px;white-space:nowrap}.muted{color:#b49cc6}a{color:#fff}</style></head><body><div class="w">';

    echo '<div class="lbl">Downloads</div><div class="big">' . fmt((int) $stats['total']) . '</div>';
    echo '<div class="grid">'
        . '<div class="card"><span class="lbl">Pessoas que baixaram</span><b>' . fmt((int) $stats['people']) . '</b></div>'
        . '<div class="card"><span class="lbl">Visitantes</span><b>' . fmt($visitors) . '</b><small>' . fmt(count($views)) . ' visitas</small></div>'
        . '<div class="card"><span class="lbl">Conversão</span><b>' . $conv . '%</b><small>visitante → baixou</small></div>'
        . '<div class="card"><span class="lbl">Hoje</span><b>' . fmt($todayDl) . '</b><small>' . fmt($todayViews) . ' visitas</small></div>'
        . '<div class="card"><span class="lbl">Tempo até baixar</span><b>' . ($median === null ? '—' : ($median >= 60 ? floor($median / 60) . 'min ' . ($median % 60) . 's' : $median . 's')) . '</b><small>mediana</small></div>'
        . '<div class="card"><span class="lbl">Último download</span><b style="font-size:18px">' . h($stats['last_at'] ? date('d/m H:i', strtotime($stats['last_at'])) : '—') . '</b></div>'
        . '</div>';
    if ($since) {
        echo '<p class="muted" style="font-size:12.5px">Detalhes (local, origem, aparelho…) registrados desde ' . h($since)
            . '. Bots e prévias de link são ignorados. <a href="?key=' . h($_GET['key'] ?? '') . '&amp;format=csv">Baixar CSV</a></p>';
    }

    $originRows = [];
    foreach (array_keys($srcViews + $srcDls) as $src) {
        $originRows[$src] = [$srcViews[$src] ?? 0, $srcDls[$src] ?? 0];
    }
    uasort($originRows, fn ($a, $b) => ($b[1] <=> $a[1]) ?: ($b[0] <=> $a[0]));
    if ($originRows) {
        echo '<section><h2>De onde vieram</h2><table><tr><th class="l" style="text-align:left">Origem</th><th style="text-align:right">Visitas</th><th style="text-align:right">Downloads</th></tr>';
        foreach (array_slice($originRows, 0, 12, true) as $src => [$v, $d]) {
            echo '<tr><td class="l">' . h($src) . '</td><td style="text-align:right">' . fmt($v) . '</td><td style="text-align:right;font-weight:800">' . fmt($d) . '</td></tr>';
        }
        echo '</table></section>';
    }

    echo '<div class="cols">';
    echo bars('País', tally($dls, fn ($r) => $r['country']));
    echo bars('Cidade', tally($dls, fn ($r) => $r['place'] ? $r['place'] : ''));
    echo bars('Aparelho', tally($dls, fn ($r) => $r['device'] . ' · ' . $r['os']));
    echo bars('Navegador / app', tally($dls, fn ($r) => $r['browser']));
    echo bars('Resolução', tally($dls, fn ($r) => RES_LABELS[$r['res'] ?? 0] ?? '?'));
    echo bars('Como salvou', tally($dls, fn ($r) => ($r['method'] ?? '') === 'share' ? 'Compartilhar (celular)' : 'Download de arquivo'));
    echo bars('Conexão', tally($dls, fn ($r) => $r['net']));
    echo bars('Operadora / provedor', tally($dls, fn ($r) => $r['isp']));
    echo bars('Idioma', tally($dls, fn ($r) => $r['lang'] ?: $r['accept_lang']));
    echo bars('Fuso horário', tally($dls, fn ($r) => $r['tz']));
    echo bars('Horário (Brasília)', $hourLabels, 24);
    echo bars('Por dia', $days, 60);
    echo '</div>';

    $recent = array_slice(array_reverse($dls), 0, 60);
    if ($recent) {
        echo '<section class="log"><h2>Últimos downloads</h2><table><tr><th>Quando</th><th>IP</th><th>Local</th><th>Provedor</th><th>Origem</th><th>Aparelho</th><th>Navegador</th><th>Res.</th><th>Tela</th><th>Levou</th></tr>';
        foreach ($recent as $r) {
            echo '<tr><td>' . h(date('d/m H:i:s', strtotime($r['t']))) . '</td><td>' . h($r['ip']) . '</td>'
                . '<td>' . h(implode(' · ', array_filter([$r['place'], $r['country']]))) . '</td><td>' . h($r['isp']) . '</td>'
                . '<td>' . h($r['source']) . '</td><td>' . h($r['device'] . ' · ' . $r['os']) . '</td><td>' . h($r['browser']) . '</td>'
                . '<td>' . h(RES_LABELS[$r['res'] ?? 0] ?? '?') . '</td><td>' . h($r['screen']) . '</td><td>' . h(($r['secs'] ?? '') . 's') . '</td></tr>';
        }
        echo '</table></section>';
    }
    echo '</div></body></html>';
}

function send_csv(array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gtavitrend-eventos-' . date('Y-m-d') . '.csv"');
    $cols = ['t', 'type', 'ip', 'country', 'place', 'isp', 'net', 'source', 'ref', 'utm_source', 'utm_medium', 'utm_campaign',
        'device', 'os', 'browser', 'bot', 'res', 'method', 'secs', 'first', 'lang', 'accept_lang', 'tz', 'screen', 'viewport', 'touch', 'vid', 'ua'];
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM pro Excel/Numbers abrirem acentos certo
    fputcsv($out, $cols);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn ($c) => is_bool($r[$c] ?? null) ? ($r[$c] ? '1' : '0') : (string) ($r[$c] ?? ''), $cols));
    }
    fclose($out);
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        $body = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
        if (!is_array($body)) {
            http_response_code(400);
            return;
        }
        $event = build_event($body);
        if ($event['type'] === 'download' && !in_array($event['res'], RESOLUTIONS, true)) {
            http_response_code(400);
            return;
        }
        record_event($event);
        http_response_code(204);
        return;
    }

    $key = (string) ($_GET['key'] ?? '');
    if ($method !== 'GET' || $key === '' || !hash_equals(KEY_SHA256, hash('sha256', $key))) {
        http_response_code(404);
        return;
    }
    $stats = with_lock(LOCK_FILE, fn () => read_json(STATS_FILE, empty_stats()));
    $format = (string) ($_GET['format'] ?? '');
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stats, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }
    $events = read_events();
    $geo = geo_lookup(array_column($events, 'ip'));
    $rows = array_map(fn ($e) => enrich($e, $geo), $events);
    if ($format === 'csv') {
        send_csv($rows);
        return;
    }
    render_panel($stats, $rows);
} catch (Throwable $e) {
    http_response_code(500);
}
