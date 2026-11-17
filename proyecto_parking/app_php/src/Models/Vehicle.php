<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Vehicles de l'usuari. No s'esborren (poden tenir reserves): es desactiven. */
final class Vehicle
{
    public const TIPUS = ['cotxe' => 'Cotxe', 'moto' => 'Moto', 'furgoneta' => 'Furgoneta'];

    public static function perUsuari(int $idUsuari): array
    {
        return DB::all('SELECT * FROM vehicles WHERE id_usuari = ? AND actiu = 1 ORDER BY data_alta', [$idUsuari]);
    }

    /** Vehicle d'un usuari concret (impedeix accedir a vehicles d'altres usuaris). */
    public static function delUsuari(int $id, int $idUsuari): ?array
    {
        return DB::one('SELECT * FROM vehicles WHERE id_vehicle = ? AND id_usuari = ? AND actiu = 1', [$id, $idUsuari]);
    }

    public static function perMatricula(string $matricula): ?array
    {
        return DB::one('SELECT * FROM vehicles WHERE matricula = ?', [$matricula]);
    }

    public static function crear(int $idUsuari, array $d): void
    {
        $existent = self::perMatricula($d['matricula']);
        if ($existent) {
            // La matrícula és única: si l'havia donada de baixa el mateix usuari, es reactiva
            DB::run('UPDATE vehicles SET marca=?, model=?, color=?, tipus=?, electric=?, actiu=1 WHERE id_vehicle=? AND id_usuari=?',
                [$d['marca'], $d['model'], $d['color'] ?: null, $d['tipus'], $d['electric'], $existent['id_vehicle'], $idUsuari]);
            return;
        }
        DB::run('INSERT INTO vehicles (id_usuari, matricula, marca, model, color, tipus, electric) VALUES (?,?,?,?,?,?,?)',
            [$idUsuari, $d['matricula'], $d['marca'], $d['model'], $d['color'] ?: null, $d['tipus'], $d['electric']]);
    }

    public static function actualitzar(int $id, array $d): void
    {
        DB::run('UPDATE vehicles SET marca=?, model=?, color=?, tipus=?, electric=? WHERE id_vehicle=?',
            [$d['marca'], $d['model'], $d['color'] ?: null, $d['tipus'], $d['electric'], $id]);
    }

    public static function desactivar(int $id): void
    {
        DB::run('UPDATE vehicles SET actiu = 0 WHERE id_vehicle = ?', [$id]);
    }

    public static function teReservesActives(int $id): bool
    {
        return (bool) DB::value("SELECT COUNT(*) FROM reserves WHERE id_vehicle = ? AND estat IN ('pendent','confirmada','en_curs')", [$id]);
    }

    /** Matrícula espanyola actual (1234ABC) o antiga provincial (T1234AB); s'admeten també formats estrangers senzills. */
    public static function matriculaValida(string $m): bool
    {
        return (bool) preg_match('/^(\d{4}[BCDFGHJKLMNPRSTVWXYZ]{3}|[A-Z]{1,2}\d{4}[A-Z]{0,2}|[A-Z0-9]{4,10})$/', $m);
    }
}
