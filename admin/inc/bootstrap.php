<?php
declare(strict_types=1);

/*
 * Base do painel: sessão, login, proteção contra tentativas em massa,
 * leitura e gravação dos preços.
 * Este arquivo não é aberto direto pelo navegador (ver .htaccess desta pasta).
 */

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'bootstrap.php') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

define('ADMIN_ROOT', dirname(__DIR__));
define('SITE_ROOT', dirname(ADMIN_ROOT));
define('AUTH_FILE', ADMIN_ROOT . '/data/auth.json.php');
define('PRICES_FILE', SITE_ROOT . '/data/precos.json');

const SESSION_IDLE = 1800;      // sai após 30 min parado
const SESSION_MAX = 28800;      // sai após 8 h, mesmo usando
const LOCK_SECONDS = 900;       // janela de bloqueio: 15 min
const MAX_FAILS_IP = 5;         // erros por IP dentro da janela
const MAX_FAILS_ALL = 25;       // erros somados de todos os IPs
const MIN_PASSWORD = 10;
const DEFAULT_NOTE = 'Valores por diária, sujeitos à disponibilidade e à temporada. Confirme pelo WhatsApp.';

// Hash falso: faz a checagem de senha levar o mesmo tempo mesmo quando o usuário está errado
const DUMMY_HASH = '$2y$10$/EI4dmsQUE.4tKYJ28EAhu.CuDkCP9wYwQx4vABgYlw7MIKbG0TM2';

const ROOMS = [
    'standard' => 'Standard',
    'triplo-simples' => 'Triplo Simples',
    'casal-superior' => 'Casal Superior',
    'superior-luxo' => 'Superior Luxo',
    'triplo-luxo' => 'Triplo Luxo',
    'familia-luxo' => 'Família Luxo',
];

/* ---------- Utilidades ---------- */

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function is_local(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return $ip === '127.0.0.1' || $ip === '::1';
}

function client_ip(): string
{
    // Só REMOTE_ADDR: cabeçalhos como X-Forwarded-For podem ser forjados por quem ataca
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
}

/** Grava em um arquivo temporário e troca de nome, para nunca deixar o arquivo pela metade. */
function write_atomic(string $path, string $content, int $mode): bool
{
    $dir = dirname($path);
    $tmp = @tempnam($dir, 'tmp');
    if ($tmp !== false) {
        if (@file_put_contents($tmp, $content, LOCK_EX) !== false) {
            @chmod($tmp, $mode);
            if (@rename($tmp, $path)) {
                return true;
            }
        }
        @unlink($tmp);
    }
    // Hospedagens que não deixam criar arquivos novos na pasta, mas deixam gravar no existente
    return is_file($path) && @file_put_contents($path, $content, LOCK_EX) !== false;
}

/* ---------- Sessão e cabeçalhos ---------- */

function admin_headers(): void
{
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('azimute_admin');
    $path = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'))), '/') . '/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path,
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function end_session(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => (bool)$p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    start_session();
    $sent = (string)($_POST['csrf'] ?? '');
    $real = (string)($_SESSION['csrf'] ?? '');
    return $sent !== '' && $real !== '' && hash_equals($real, $sent);
}

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    start_session();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/* ---------- Usuário e senha ---------- */

function auth_load(): ?array
{
    $raw = @file_get_contents(AUTH_FILE);
    if ($raw === false) {
        return null;
    }
    $nl = strpos($raw, "\n");
    $data = json_decode($nl === false ? '' : substr($raw, $nl + 1), true);
    return is_array($data) && isset($data['usuario'], $data['hash']) ? $data : null;
}

function auth_save(array $data): bool
{
    $content = "<?php http_response_code(404); exit; ?>\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    return write_atomic(AUTH_FILE, $content, 0640);
}

/** Exige login. Se a senha ainda é a provisória, leva para a troca de senha. */
function require_login(bool $allowPasswordChange = false): array
{
    start_session();
    admin_headers();
    $auth = auth_load();

    if (empty($_SESSION['auth']) || $auth === null) {
        redirect('login.php');
    }

    $now = time();
    $expired = ($now - (int)($_SESSION['last'] ?? 0)) > SESSION_IDLE
        || ($now - (int)($_SESSION['login_at'] ?? 0)) > SESSION_MAX
        || (int)($_SESSION['versao'] ?? 0) !== (int)($auth['versao'] ?? 1);
    if ($expired) {
        end_session();
        redirect('login.php?expirou=1');
    }

    $_SESSION['last'] = $now;

    if (!$allowPasswordChange && !empty($auth['trocar_senha'])) {
        redirect('senha.php');
    }
    return $auth;
}

/* ---------- Limite de tentativas de login ---------- */

function rl_path(string $key): string
{
    return sys_get_temp_dir() . '/azimute_rl_' . hash('sha256', $key) . '.json';
}

function rl_fails(string $key): array
{
    $raw = @file_get_contents(rl_path($key));
    $list = $raw ? json_decode($raw, true) : [];
    $limit = time() - LOCK_SECONDS;
    return array_values(array_filter(is_array($list) ? $list : [], static function ($t) use ($limit) {
        return is_int($t) && $t > $limit;
    }));
}

/** Segundos que faltam para liberar; 0 se não está bloqueado. */
function rl_wait(string $key, int $max): int
{
    $fails = rl_fails($key);
    if (count($fails) < $max) {
        return 0;
    }
    sort($fails);
    return max(1, $fails[count($fails) - $max] + LOCK_SECONDS - time());
}

function rl_hit(string $key): void
{
    $fails = rl_fails($key);
    $fails[] = time();
    @file_put_contents(rl_path($key), json_encode($fails), LOCK_EX);
}

function rl_clear(string $key): void
{
    @unlink(rl_path($key));
}

/* ---------- Preços ---------- */

function prices_load(): array
{
    $out = ['atualizado' => null, 'observacao' => DEFAULT_NOTE, 'quartos' => []];
    $raw = @file_get_contents(PRICES_FILE);
    $json = $raw ? json_decode($raw, true) : null;

    if (is_array($json)) {
        $out['atualizado'] = is_string($json['atualizado'] ?? null) ? $json['atualizado'] : null;
        if (isset($json['observacao']) && is_string($json['observacao'])) {
            $out['observacao'] = $json['observacao'];
        }
    }
    foreach (ROOMS as $slug => $name) {
        $p = is_array($json) ? ($json['quartos'][$slug]['preco'] ?? null) : null;
        $out['quartos'][$slug] = ['nome' => $name, 'preco' => is_numeric($p) ? (float)$p : null];
    }
    return $out;
}

function prices_save(array $prices, string $note): bool
{
    $quartos = [];
    foreach (ROOMS as $slug => $name) {
        $v = $prices[$slug] ?? null;
        if ($v !== null) {
            $v = ((float)$v == floor((float)$v)) ? (int)$v : round((float)$v, 2);
        }
        $quartos[$slug] = ['nome' => $name, 'preco' => $v];
    }
    $data = [
        'atualizado' => date('c'),
        'observacao' => $note,
        'quartos' => $quartos,
    ];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    // Guarda a versão anterior para desfazer um engano
    if (is_file(PRICES_FILE)) {
        @copy(PRICES_FILE, dirname(PRICES_FILE) . '/precos.anterior.json');
    }
    return write_atomic(PRICES_FILE, $json . "\n", 0644);
}

/**
 * Converte o que foi digitado ("180", "180,50", "1.250,00", "R$ 220") em número.
 * Retorna [valor|null, erro|null]. Vazio vale "sob consulta" (null, sem erro).
 */
function parse_price(string $input): array
{
    $s = trim(str_ireplace(['R$', ' '], '', $input));
    if ($s === '') {
        return [null, null];
    }
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
        return [null, 'Use só números, por exemplo 180 ou 180,50.'];
    }
    $v = (float)$s;
    if ($v < 1 || $v > 99999.99) {
        return [null, 'Informe um valor entre 1 e 99.999,99.'];
    }
    return [$v, null];
}

function format_price($v): string
{
    return $v === null ? '' : number_format((float)$v, 2, ',', '.');
}

function format_date(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    try {
        return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y \à\s H:i');
    } catch (Exception $e) {
        return '—';
    }
}
