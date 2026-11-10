<?php
declare(strict_types=1);

/** Steps: data in, data out. No filesystem, no globals. */

function clip(string $text, int $limit): string
{
    $text = trim($text);
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
}

function state_from_seed(array $rows, int $now): array
{
    $state = ['rev' => 1, 'tasks' => [], 'shopping' => [], 'inventory' => []];
    foreach ($rows as $list => $listRows) {
        foreach ($listRows as $index => $row) {
            $item = ['id' => 'seed-' . $list . '-' . ($index + 1), 'title' => $row[0], 'done' => false,
                'createdAt' => $now + $index, 'updatedAt' => $now];
            $item += $list === 'inventory'
                ? ['place' => $row[1], 'room' => $row[2], 'decision' => $row[3], 'notes' => $row[4]]
                : ['room' => $row[1], 'priority' => $row[2], 'notes' => ''];
            $state[$list][] = $item;
        }
    }
    return $state;
}

function new_item(string $list, string $id, int $now): array
{
    $item = ['id' => $id, 'title' => '', 'room' => '', 'notes' => '', 'done' => false,
        'createdAt' => $now, 'updatedAt' => $now];
    return $item + ($list === 'inventory' ? ['place' => 'seropedica', 'decision' => ''] : ['priority' => 1]);
}

/** Keeps only the fields a list accepts, coerced to their types. */
function sanitize_patch(string $list, array $patch): array
{
    $clean = [];
    foreach (TEXT_LIMITS as $field => $limit) {
        if (isset($patch[$field]) && is_string($patch[$field])) {
            $clean[$field] = clip($patch[$field], $limit);
        }
    }
    if (isset($patch['done'])) {
        $clean['done'] = (bool) $patch['done'];
    }
    if (in_array($list, PRIORITY_LISTS, true) && isset($patch['priority']) && is_numeric($patch['priority'])) {
        $clean['priority'] = max(0, min(MAX_PRIORITY, (int) $patch['priority']));
    }
    if ($list === 'inventory') {
        if (isset($patch['place']) && in_array($patch['place'], PLACES, true)) {
            $clean['place'] = $patch['place'];
        }
        if (isset($patch['decision']) && in_array($patch['decision'], DECISIONS, true)) {
            $clean['decision'] = $patch['decision'];
        }
    }
    return $clean;
}

function apply_op(array $state, array $op, int $now): array
{
    $list = $op['list'] ?? '';
    $id = $op['id'] ?? '';
    if (!in_array($list, LISTS, true) || !is_string($id) || !preg_match('/^[a-z0-9-]{4,40}$/', $id)) {
        return $state;
    }
    $index = array_search($id, array_column($state[$list], 'id'), true);

    if (($op['type'] ?? '') === 'delete') {
        if ($index !== false) {
            array_splice($state[$list], $index, 1);
        }
        return $state;
    }
    if (($op['type'] ?? '') !== 'upsert' || !is_array($op['patch'] ?? null)) {
        return $state;
    }
    $patch = sanitize_patch($list, $op['patch']);
    if ($index === false) {
        if (($patch['title'] ?? '') === '' || count($state[$list]) >= MAX_ITEMS_PER_LIST) {
            return $state;
        }
        $state[$list][] = array_merge(new_item($list, $id, $now), $patch);
        return $state;
    }
    if (($patch['title'] ?? null) === '') {
        unset($patch['title']);
    }
    $state[$list][$index] = array_merge($state[$list][$index], $patch, ['updatedAt' => $now]);
    return $state;
}

function apply_ops(array $state, array $ops, int $now): array
{
    foreach (array_slice($ops, 0, MAX_OPS_PER_REQUEST) as $offset => $op) {
        if (is_array($op)) {
            $state = apply_op($state, $op, $now + $offset);
        }
    }
    $state['rev'] = (int) ($state['rev'] ?? 0) + 1;
    return $state;
}

function empty_auth(): array
{
    return ['hash' => '', 'sessions' => [], 'fails' => 0, 'lastFailAt' => 0];
}

function session_is_valid(array $auth, string $token, int $nowSeconds): bool
{
    $expires = $token === '' ? 0 : ($auth['sessions'][hash('sha256', $token)] ?? 0);
    return $expires > $nowSeconds;
}

function auth_with_session(array $auth, string $token, int $nowSeconds): array
{
    $live = array_filter($auth['sessions'], function ($expires) use ($nowSeconds) {
        return $expires > $nowSeconds;
    });
    $live[hash('sha256', $token)] = $nowSeconds + SESSION_TTL_SECONDS;
    asort($live);
    $auth['sessions'] = array_slice($live, -MAX_SESSIONS, null, true);
    $auth['fails'] = 0;
    return $auth;
}

function auth_without_session(array $auth, string $token): array
{
    unset($auth['sessions'][hash('sha256', $token)]);
    return $auth;
}

function auth_with_failure(array $auth, int $nowSeconds): array
{
    $auth['fails'] = (int) $auth['fails'] + 1;
    $auth['lastFailAt'] = $nowSeconds;
    return $auth;
}

/** Seconds until another password attempt is allowed; 0 when it is allowed now. */
function lockout_remaining(array $auth, int $nowSeconds): int
{
    $extra = (int) $auth['fails'] - FREE_LOGIN_ATTEMPTS;
    if ($extra < 0) {
        return 0;
    }
    $lockout = min(MAX_LOCKOUT_SECONDS, 15 * (2 ** min($extra, 10)));
    return max(0, (int) $auth['lastFailAt'] + $lockout - $nowSeconds);
}
