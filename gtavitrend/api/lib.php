<?php
declare(strict_types=1);

/** Armazenamento, leitura da requisição, user-agent e geolocalização do contador. */

date_default_timezone_set('America/Sao_Paulo');

const DATA_DIR = __DIR__ . '/../data';
const STATS_FILE = DATA_DIR . '/stats.json';
const EVENTS_FILE = DATA_DIR . '/events.jsonl';
const GEO_FILE = DATA_DIR . '/geo.json';
const LOCK_FILE = DATA_DIR . '/stats.lock';
const GEO_LOCK_FILE = DATA_DIR . '/geo.lock';
// sha256 da chave de leitura (a chave em si não fica no repo, que é público)
const KEY_SHA256 = 'd56a03c7364f00c01023bf9d4620bc9907d4339ca2d1d4023e97c95ffecdb354';
const RESOLUTIONS = [720, 1080, 1440, 2160];
const RES_LABELS = [720 => '720p', 1080 => '1080p', 1440 => '2K', 2160 => '4K'];
// ip-api.com: grátis, sem chave, uso não comercial, lote de até 100 IPs (15 lotes/min)
const GEO_BATCH_URL = 'http://ip-api.com/batch?fields=status,query,country,countryCode,regionName,city,isp,mobile,proxy,hosting&lang=pt-BR';
const GEO_MAX_BATCHES_PER_VIEW = 3;

// ---------------------------------------------------------------- arquivos

function ensure_data_dir(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
}

function with_lock(string $lockFile, callable $work)
{
    ensure_data_dir();
    $handle = fopen($lockFile, 'c');
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

function read_json(string $path, array $default): array
{
    if (!is_file($path)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data + $default : $default;
}

function write_json(string $path, array $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $temp = $path . '.tmp';
    if ($json === false || file_put_contents($temp, $json) === false || !rename($temp, $path)) {
        throw new RuntimeException('could not write ' . basename($path));
    }
}

function empty_stats(): array
{
    return ['total' => 0, 'people' => 0, 'views' => 0, 'by_day' => [], 'by_res' => [], 'first_at' => null, 'last_at' => null];
}

/** @return array<int, array> */
function read_events(): array
{
    if (!is_file(EVENTS_FILE)) {
        return [];
    }
    $out = [];
    $fh = fopen(EVENTS_FILE, 'r');
    while ($fh && ($line = fgets($fh)) !== false) {
        $e = json_decode($line, true);
        if (is_array($e)) {
            $out[] = $e;
        }
    }
    if ($fh) {
        fclose($fh);
    }
    return $out;
}

// ---------------------------------------------------------------- requisição

function clip($value, int $max): string
{
    $s = is_scalar($value) ? trim((string) $value) : '';
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

function client_ip(): string
{
    // atrás do Cloudflare o IP real vem neste header; senão, o da conexão
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Monta o evento só com o que chega numa requisição comum + o que a página manda. */
function build_event(array $body): array
{
    $type = ($body['type'] ?? '') === 'view' ? 'view' : 'download';
    $utm = is_array($body['utm'] ?? null) ? $body['utm'] : [];
    $lang = explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))[0];
    $e = [
        't' => date('c'),
        'type' => $type,
        'ip' => client_ip(),
        'cf_country' => clip($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '', 4),
        'ua' => clip($_SERVER['HTTP_USER_AGENT'] ?? '', 400),
        'ch_platform' => trim(clip($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '', 40), '"'),
        'ch_mobile' => clip($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '', 4),
        'accept_lang' => clip($lang, 20),
        'vid' => preg_replace('/[^a-z0-9]/i', '', clip($body['vid'] ?? '', 40)),
        'ref' => clip($body['ref'] ?? '', 300),
        'utm_source' => clip($utm['source'] ?? '', 80),
        'utm_medium' => clip($utm['medium'] ?? '', 80),
        'utm_campaign' => clip($utm['campaign'] ?? '', 80),
        'lang' => clip($body['lang'] ?? '', 20),
        'tz' => clip($body['tz'] ?? '', 60),
        'screen' => clip($body['screen'] ?? '', 30),
        'viewport' => clip($body['viewport'] ?? '', 30),
        'touch' => !empty($body['touch']),
    ];
    if ($type === 'download') {
        $e['res'] = (int) ($body['res'] ?? 0);
        $e['first'] = !empty($body['first']);
        $e['method'] = ($body['method'] ?? '') === 'share' ? 'share' : 'file';
        $e['secs'] = max(0, min(86400, (int) round(((float) ($body['ms'] ?? 0)) / 1000)));
    }
    return $e;
}

function record_event(array $e): void
{
    with_lock(LOCK_FILE, function () use ($e): void {
        file_put_contents(EVENTS_FILE, json_encode($e, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $s = read_json(STATS_FILE, empty_stats());
        if ($e['type'] === 'view') {
            $s['views']++;
        } else {
            $day = substr($e['t'], 0, 10);
            $s['total']++;
            if ($e['first']) {
                $s['people']++;
            }
            $s['by_day'][$day] = ($s['by_day'][$day] ?? 0) + 1;
            $s['by_res'][(string) $e['res']] = ($s['by_res'][(string) $e['res']] ?? 0) + 1;
            $s['first_at'] = $s['first_at'] ?? $e['t'];
            $s['last_at'] = $e['t'];
        }
        write_json(STATS_FILE, $s);
    });
}

// ---------------------------------------------------------------- user-agent

function parse_ua(string $ua, bool $touch = false): array
{
    $app = '';
    $apps = [
        'Instagram' => '/Instagram/i', 'Facebook' => '/FBAN|FBAV|FB_IAB|FBIOS/i',
        'TikTok' => '/musical_ly|TikTok|BytedanceWebview|trill_/i', 'Threads' => '/Barcelona/i',
        'X/Twitter' => '/Twitter/i', 'LinkedIn' => '/LinkedInApp/i', 'Snapchat' => '/Snapchat/i',
        'Pinterest' => '/Pinterest/i', 'Telegram' => '/Telegram/i', 'Discord' => '/Discord/i',
    ];
    foreach ($apps as $name => $re) {
        if (preg_match($re, $ua)) {
            $app = $name;
            break;
        }
    }

    $os = 'Outro';
    if (preg_match('/iPhone|iPod/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/iPad/i', $ua) || (preg_match('/Macintosh/i', $ua) && $touch)) {
        $os = 'iPadOS';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/Windows/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/CrOS/i', $ua)) {
        $os = 'ChromeOS';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    $browser = 'Outro';
    foreach ([
        'Edge' => '/Edg(e|A|iOS)?\//', 'Samsung Internet' => '/SamsungBrowser/', 'Opera' => '/OPR\/|Opera/',
        'Firefox' => '/Firefox|FxiOS/', 'Chrome' => '/Chrome|CriOS/', 'Safari' => '/Version\/.*Safari/',
    ] as $name => $re) {
        if (preg_match($re, $ua)) {
            $browser = $name;
            break;
        }
    }
    if ($app !== '') {
        $browser = 'App ' . $app;
    }

    $device = 'Computador';
    if ($os === 'iPadOS' || (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua))) {
        $device = 'Tablet';
    } elseif (preg_match('/Mobi|iPhone|iPod|Android/i', $ua)) {
        $device = 'Celular';
    }

    $bot = (bool) preg_match('/bot|crawl|spider|slurp|headless|preview|facebookexternalhit|WhatsApp\//i', $ua);
    return ['app' => $app, 'os' => $os, 'browser' => $browser, 'device' => $device, 'bot' => $bot];
}

/** De onde a pessoa veio: UTM > app > domínio do referrer > direto. */
function source_of(array $e, array $ua): string
{
    if (($e['utm_source'] ?? '') !== '') {
        return 'utm: ' . $e['utm_source'];
    }
    if ($ua['app'] !== '') {
        return $ua['app'];
    }
    $host = strtolower((string) parse_url((string) ($e['ref'] ?? ''), PHP_URL_HOST));
    if ($host === '') {
        return 'Direto / sem origem';
    }
    $map = [
        '/(^|\.)instagram\.com$/' => 'Instagram', '/(^|\.)(facebook\.com|fb\.com|fb\.me)$/' => 'Facebook',
        '/^t\.co$|(^|\.)(twitter\.com|x\.com)$/' => 'X/Twitter', '/(^|\.)tiktok\.com$/' => 'TikTok',
        '/(^|\.)whatsapp\.(com|net)$|^wa\.me$/' => 'WhatsApp', '/(^|\.)google\.[a-z.]+$/' => 'Google',
        '/(^|\.)bing\.com$/' => 'Bing', '/(^|\.)(youtube\.com|youtu\.be)$/' => 'YouTube', '/(^|\.)reddit\.com$/' => 'Reddit',
        '/(^|\.)linkedin\.com$|^lnkd\.in$/' => 'LinkedIn', '/(^|\.)threads\.(net|com)$/' => 'Threads',
        '/(^|\.)(t\.me|telegram\.org)$/' => 'Telegram', '/(^|\.)felps\.dev$/' => 'felps.dev',
    ];
    foreach ($map as $re => $name) {
        if (preg_match($re, $host)) {
            return $name;
        }
    }
    return preg_replace('/^www\./', '', $host);
}

// ---------------------------------------------------------------- geolocalização

/** Busca no ip-api os IPs ainda sem cache (em lotes) e devolve o cache inteiro. */
function geo_lookup(array $ips): array
{
    return with_lock(GEO_LOCK_FILE, function () use ($ips): array {
        $cache = read_json(GEO_FILE, []);
        $missing = array_values(array_filter(array_unique($ips), function ($ip) use ($cache) {
            return $ip !== '' && !isset($cache[$ip])
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }));
        $changed = false;
        foreach (array_slice(array_chunk($missing, 100), 0, GEO_MAX_BATCHES_PER_VIEW) as $chunk) {
            $resp = http_post_json(GEO_BATCH_URL, $chunk);
            if (!is_array($resp)) {
                break; // sem rede ou limite estourado: tenta de novo na próxima abertura do painel
            }
            foreach ($resp as $row) {
                if (is_array($row) && isset($row['query'])) {
                    $cache[$row['query']] = ($row['status'] ?? '') === 'success'
                        ? array_intersect_key($row, array_flip(['country', 'countryCode', 'regionName', 'city', 'isp', 'mobile', 'proxy', 'hosting']))
                        : ['country' => '?'];
                    $changed = true;
                }
            }
        }
        if ($changed) {
            write_json(GEO_FILE, $cache);
        }
        return $cache;
    });
}

function http_post_json(string $url, array $payload): ?array
{
    $json = json_encode($payload);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $json, 'timeout' => 5, 'ignore_errors' => true,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        $code = 200;
    }
    if (!is_string($out) || $code !== 200) {
        return null;
    }
    $data = json_decode($out, true);
    return is_array($data) ? $data : null;
}
