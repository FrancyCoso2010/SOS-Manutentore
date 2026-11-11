<?php

declare(strict_types=1);

/**
 * Escape di un valore per l'output HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Lunghezza in caratteri, senza richiedere l'estensione mbstring.
 */
function text_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * Taglia una stringa a un numero di caratteri, senza mbstring.
 */
function text_cut(string $value, int $limit): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

/**
 * Redirect HTTP e interruzione dello script.
 */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;

    return is_numeric($value) ? (int) $value : $default;
}

function get_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;

    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Token CSRF della sessione corrente (generato alla prima richiesta).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Campo hidden pronto da inserire nei form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifica del token CSRF inviato dal form.
 */
function csrf_verify(?string $token): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;

    if (!is_string($expected) || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * Verifica CSRF su una richiesta POST: se non valida, avvisa e torna indietro.
 */
function csrf_or_redirect(string $path): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Sessione scaduta. Riprova.');

        redirect($path);
    }
}

/**
 * Messaggio da mostrare alla richiesta successiva.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Legge e svuota i messaggi in sospeso.
 *
 * @return array<int, array{type: string, message: string}>
 */
function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($messages) ? $messages : [];
}

/**
 * @return array<string, string>
 */
function roles(): array
{
    return [
        'admin'       => 'Amministratore',
        'manutentore' => 'Manutentore',
        'operatore'   => 'Operatore',
    ];
}

function role_label(string $role): string
{
    return roles()[$role] ?? $role;
}

/**
 * Temi disponibili. La scelta viene salvata sul singolo account.
 *
 * @return array<string, string>
 */
function themes(): array
{
    return [
        'chiaro'    => 'Chiaro',
        'scuro'     => 'Scuro',
        'contrasto' => 'Alto contrasto',
    ];
}

/**
 * Tema attivo per l'utente collegato (default chiaro).
 */
function current_theme(): string
{
    $user = class_exists('Auth') ? Auth::user() : null;
    $theme = is_array($user) ? (string) ($user['theme'] ?? '') : '';

    return array_key_exists($theme, themes()) ? $theme : 'chiaro';
}

/**
 * Iniziali per l'avatar, al massimo due lettere.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $letters .= strtoupper(text_cut($part, 1));

        if (text_len($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : '?';
}

/**
 * Livelli di urgenza, dal più grave al meno grave.
 *
 * @return array<string, string>
 */
function urgencies(): array
{
    return [
        'fermo' => 'Fermo impianto',
        'alta'  => 'Alta',
        'media' => 'Media',
        'bassa' => 'Bassa',
    ];
}

function urgency_label(string $urgency): string
{
    return urgencies()[$urgency] ?? $urgency;
}

/**
 * Variante colore dei badge di urgenza.
 */
function urgency_class(string $urgency): string
{
    return match ($urgency) {
        'fermo' => 'danger',
        'alta'  => 'warn',
        'media' => 'info',
        default => 'muted',
    };
}

/**
 * Priorità di ordinamento: più basso = più urgente.
 */
function urgency_rank(string $urgency): int
{
    return match ($urgency) {
        'fermo' => 0,
        'alta'  => 1,
        'media' => 2,
        default => 3,
    };
}

function status_label(string $status): string
{
    return $status === 'risolto' ? 'Risolto' : 'Aperto';
}

function fmt_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
}

/**
 * Giorni di scarto rispetto a oggi (positivo = in ritardo).
 */
function days_late(?string $date): int
{
    if ($date === null || $date === '') {
        return 0;
    }

    $timestamp = strtotime(substr($date, 0, 10) . ' 00:00:00');

    if ($timestamp === false) {
        return 0;
    }

    return (int) floor((strtotime('today') - $timestamp) / 86400);
}

function due_text(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $days = days_late($date);

    if ($days > 0) {
        return 'In ritardo di ' . $days . ($days === 1 ? ' giorno' : ' giorni');
    }

    if ($days === 0) {
        return 'Oggi';
    }

    if ($days === -1) {
        return 'Domani';
    }

    return 'Tra ' . abs($days) . ' giorni';
}

function is_overdue(?string $date): bool
{
    return days_late($date) > 0;
}

function frequency_text(int $days): string
{
    return match (true) {
        $days <= 1  => 'Ogni giorno',
        $days === 7 => 'Ogni settimana',
        $days === 14 => 'Ogni 2 settimane',
        $days === 30 => 'Ogni mese',
        default     => 'Ogni ' . $days . ' giorni',
    };
}

/**
 * Nome da mostrare per un utente.
 */
function person(?string $fullName, ?string $username): string
{
    $fullName = trim((string) $fullName);

    return $fullName !== '' ? $fullName : (string) $username;
}

/**
 * Nome del prodotto.
 */
function product_name(): string
{
    return 'SOS Manutentore';
}

/**
 * Nome dell'azienda configurata, con fallback al nome del prodotto.
 */
function app_name(): string
{
    $name = class_exists('Settings') ? Settings::get('company_name') : '';

    return $name !== '' ? $name : product_name();
}

/**
 * URL di un asset con versione basata sulla data di modifica (cache busting).
 */
function asset_url(string $path): string
{
    $file = __DIR__ . '/../public/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return $path . '?v=' . $version;
}

/**
 * Pagina a cui tornare dopo un'azione, limitata a una lista sicura.
 */
function back_target(string $default = 'dashboard.php'): string
{
    $back = post_str('back');

    $allowed = [
        'dashboard.php',
        'turno.php',
        'segnalazioni.php',
        'mie-segnalazioni.php',
        'attivita.php',
    ];

    return in_array($back, $allowed, true) ? $back : $default;
}
