<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database as DB;

/** Usuaris (clients, operadors i administradors). Mai s'esborren: es desactiven. */
final class Usuari
{
    public const ROLS = ['client', 'operador', 'admin'];

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM usuaris WHERE id_usuari = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return DB::one('SELECT * FROM usuaris WHERE email = ?', [mb_strtolower($email)]);
    }

    public static function emailExisteix(string $email, ?int $excepte = null): bool
    {
        return (bool) DB::value('SELECT COUNT(*) FROM usuaris WHERE email = ? AND id_usuari <> ?', [mb_strtolower($email), $excepte ?? 0]);
    }

    public static function dniExisteix(string $dni, ?int $excepte = null): bool
    {
        return (bool) DB::value('SELECT COUNT(*) FROM usuaris WHERE dni_hash = ? AND id_usuari <> ?', [Crypto::hash($dni), $excepte ?? 0]);
    }

    public static function crear(array $d): int
    {
        DB::run('INSERT INTO usuaris (nom, primer_cognom, segon_cognom, email, telefon_xifrat, dni_xifrat, dni_hash, password_hash, rol)
                 VALUES (?,?,?,?,?,?,?,?,\'client\')', [
            $d['nom'], $d['primer_cognom'], $d['segon_cognom'] ?: null, mb_strtolower($d['email']),
            Crypto::encrypt($d['telefon'] ?: null), Crypto::encrypt($d['dni'] ?: null),
            $d['dni'] ? Crypto::hash($d['dni']) : null,
            password_hash($d['password'], PASSWORD_DEFAULT),
        ]);
        return DB::lastId();
    }

    public static function actualitzarPerfil(int $id, array $d): void
    {
        DB::run('UPDATE usuaris SET nom=?, primer_cognom=?, segon_cognom=?, email=?, telefon_xifrat=?, dni_xifrat=?, dni_hash=?
                 WHERE id_usuari=?', [
            $d['nom'], $d['primer_cognom'], $d['segon_cognom'] ?: null, mb_strtolower($d['email']),
            Crypto::encrypt($d['telefon'] ?: null), Crypto::encrypt($d['dni'] ?: null),
            $d['dni'] ? Crypto::hash($d['dni']) : null, $id,
        ]);
    }

    public static function canviarPassword(int $id, string $password): void
    {
        DB::run('UPDATE usuaris SET password_hash = ? WHERE id_usuari = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /** Suma un intent fallit; al 5è es bloqueja el compte durant 15 minuts. */
    public static function intentFallit(array $u, int $max, int $minuts): void
    {
        $intents = (int) $u['intents_fallits'] + 1;
        if ($intents >= $max) {
            DB::run('UPDATE usuaris SET intents_fallits = 0, bloquejat_fins = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id_usuari = ?',
                [$minuts, $u['id_usuari']]);
        } else {
            DB::run('UPDATE usuaris SET intents_fallits = ? WHERE id_usuari = ?', [$intents, $u['id_usuari']]);
        }
    }

    public static function loginCorrecte(int $id, ?string $nouHash = null): void
    {
        DB::run('UPDATE usuaris SET intents_fallits = 0, bloquejat_fins = NULL, ultim_acces = NOW() WHERE id_usuari = ?', [$id]);
        if ($nouHash) {
            DB::run('UPDATE usuaris SET password_hash = ? WHERE id_usuari = ?', [$nouHash, $id]);
        }
    }

    // --------------------------------------------------------- administració

    public static function cercar(string $q, string $rol): array
    {
        $sql = 'SELECT u.id_usuari, u.nom, u.primer_cognom, u.segon_cognom, u.email, u.rol, u.actiu, u.bloquejat_fins,
                       u.data_registre, u.ultim_acces, p.nom AS parquing,
                       (SELECT COUNT(*) FROM reserves r WHERE r.id_usuari = u.id_usuari) AS num_reserves
                FROM usuaris u LEFT JOIN parquings p ON p.id_parquing = u.id_parquing_assignat WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= " AND (u.email LIKE ? OR CONCAT_WS(' ', u.nom, u.primer_cognom, u.segon_cognom) LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if (in_array($rol, self::ROLS, true)) {
            $sql .= ' AND u.rol = ?';
            $params[] = $rol;
        }
        return DB::all($sql . ' ORDER BY u.rol = \'client\', u.primer_cognom, u.nom LIMIT 200', $params);
    }

    public static function actualitzarAdmin(int $id, string $rol, ?int $idParquing, bool $actiu): void
    {
        DB::run('UPDATE usuaris SET rol = ?, id_parquing_assignat = ?, actiu = ? WHERE id_usuari = ?',
            [$rol, $rol === 'operador' ? $idParquing : null, (int) $actiu, $id]);
    }

    public static function desbloquejar(int $id): void
    {
        DB::run('UPDATE usuaris SET intents_fallits = 0, bloquejat_fins = NULL WHERE id_usuari = ?', [$id]);
    }

    public static function nomComplet(array $u): string
    {
        return trim($u['nom'] . ' ' . $u['primer_cognom'] . ' ' . ($u['segon_cognom'] ?? ''));
    }
}
