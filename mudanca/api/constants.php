<?php
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';
const STATE_FILE = DATA_DIR . '/state.json';
const AUTH_FILE = DATA_DIR . '/auth.json';
const LOCK_FILE = DATA_DIR . '/app.lock';

const SESSION_COOKIE = 'mudanca_s';
const SESSION_TTL_SECONDS = 180 * 24 * 3600;
const MAX_SESSIONS = 20;
const MIN_PASSWORD_LENGTH = 6;
const FREE_LOGIN_ATTEMPTS = 5;
const MAX_LOCKOUT_SECONDS = 900;

const LISTS = ['tasks', 'shopping', 'inventory'];
const PRIORITY_LISTS = ['tasks', 'shopping'];
const PLACES = ['seropedica', 'recreio'];
const DECISIONS = ['', 'sell', 'keep', 'other'];

const MAX_OPS_PER_REQUEST = 200;
const MAX_ITEMS_PER_LIST = 2000;
const MAX_PRIORITY = 9999;
const TEXT_LIMITS = ['title' => 200, 'room' => 40, 'notes' => 4000];
