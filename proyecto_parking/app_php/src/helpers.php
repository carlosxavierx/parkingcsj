<?php
/**
 * Funcions d'ajuda globals que es fan servir a controladors i vistes.
 */
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/** Escapa qualsevol valor per mostrar-lo en HTML (protecció XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirigeix i atura l'execució. */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

/** Missatge d'un sol ús que es mostra a la pàgina següent (ok, error, info). */
function flash(string $tipus, string $missatge): void
{
    $_SESSION['_flash'][] = ['tipus' => $tipus, 'missatge' => $missatge];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Camp ocult amb el token CSRF per a tots els formularis. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Valor antic d'un formulari (després d'un error de validació). */
function old(string $camp, mixed $perDefecte = ''): string
{
    return (string) ($_SESSION['_old'][$camp] ?? $perDefecte);
}

function guardar_old(array $dades): void
{
    unset($dades['password'], $dades['password2'], $dades['password_actual'], $dades['numero_targeta'], $dades['cvv'], $dades['_csrf']);
    $_SESSION['_old'] = $dades;
}

function netejar_old(): void
{
    unset($_SESSION['_old']);
}

/** Format de preu: 1.234,50 € */
function euro(float|string|null $import): string
{
    return number_format((float) $import, 2, ',', '.') . ' €';
}

/** Data i hora en format català: 21/11/2026 10:30 */
function data_hora(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

function data(?string $dt): string
{
    return $dt ? date('d/m/Y', strtotime($dt)) : '—';
}

/** Valor per a un <input type="datetime-local"> */
function dt_input(?string $dt): string
{
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}

/** Etiquetes en català dels estats de reserva */
function estat_reserva(string $estat): string
{
    return [
        'pendent'    => 'Pendent',
        'confirmada' => 'Confirmada',
        'en_curs'    => 'En curs',
        'completada' => 'Completada',
        'cancellada' => 'Cancel·lada',
    ][$estat] ?? $estat;
}

/** Etiqueta de color amb l'estat d'una reserva */
function badge_estat(string $estat): string
{
    return '<span class="badge badge-' . e($estat) . '">' . e(estat_reserva($estat)) . '</span>';
}

function etiqueta_rol(string $rol): string
{
    return ['client' => 'Client', 'operador' => 'Operador', 'admin' => 'Administrador'][$rol] ?? $rol;
}

function etiqueta_servei(string $s): string
{
    return [
        'cobert' => 'Cobert', 'cctv' => 'Videovigilància', 'pmr' => 'Places PMR', 'barrera' => 'Barrera',
        'recarrega' => 'Recàrrega elèctrica', 'ascensor' => 'Ascensor', 'moto' => 'Motos', '24h' => 'Obert 24 h',
    ][$s] ?? $s;
}

/** Usuari autenticat (o null) */
function usuari(): ?array
{
    return App\Core\Auth::user();
}

/** Ruta actual (per marcar el menú) */
function ruta_activa(string $prefix): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($prefix === '/') {
        return $path === '/' ? 'actiu' : '';
    }
    return str_starts_with($path, $prefix) ? 'actiu' : '';
}

/** Normalitza una matrícula: majúscules, sense espais ni guions. */
function normalitzar_matricula(string $m): string
{
    return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($m)));
}

/** Converteix un "datetime-local" del formulari a format SQL o null si no és vàlid. */
function parse_datetime(?string $valor): ?string
{
    if (!$valor) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $valor) ?: DateTime::createFromFormat('Y-m-d H:i:s', $valor)
        ?: DateTime::createFromFormat('Y-m-d H:i', $valor);
    return $d ? $d->format('Y-m-d H:i:00') : null;
}

function post(string $camp, string $perDefecte = ''): string
{
    $v = $_POST[$camp] ?? $perDefecte;
    return is_string($v) ? trim($v) : $perDefecte;
}

function get(string $camp, string $perDefecte = ''): string
{
    $v = $_GET[$camp] ?? $perDefecte;
    return is_string($v) ? trim($v) : $perDefecte;
}
