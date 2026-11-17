<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Registre d'entrades i sortides del lector de matrícules. */
final class Acces
{
    public static function registrar(array $d): void
    {
        DB::run('INSERT INTO accessos (id_parquing, matricula, id_vehicle, id_reserva, id_operador, tipus, data_hora,
                 barrera_oberta, motiu, minuts_exces) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $d['id_parquing'], $d['matricula'], $d['id_vehicle'] ?? null, $d['id_reserva'] ?? null, $d['id_operador'] ?? null,
            $d['tipus'], $d['data_hora'], (int) $d['barrera_oberta'], mb_substr((string) ($d['motiu'] ?? ''), 0, 100) ?: null,
            $d['minuts_exces'] ?? null,
        ]);
    }

    /** Últim accés amb barrera oberta d'una matrícula en un pàrquing (per saber si és dins). */
    public static function ultimObert(string $matricula, int $idParquing): ?array
    {
        return DB::one('SELECT * FROM accessos WHERE matricula = ? AND id_parquing = ? AND barrera_oberta = 1
                        ORDER BY id_acces DESC LIMIT 1', [$matricula, $idParquing]);
    }

    /** Vehicles sense reserva que són dins ara (ocupen places de rotació). */
    public static function rotacioDins(int $idParquing): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM accessos a
                WHERE a.id_parquing = ? AND a.tipus = 'entrada' AND a.barrera_oberta = 1 AND a.id_reserva IS NULL
                  AND NOT EXISTS (SELECT 1 FROM accessos b WHERE b.id_parquing = a.id_parquing AND b.matricula = a.matricula
                                  AND b.barrera_oberta = 1 AND b.id_acces > a.id_acces)", [$idParquing]);
    }

    public static function recents(int $idParquing, int $limit = 15): array
    {
        return DB::all("SELECT a.*, r.numero_reserva FROM accessos a LEFT JOIN reserves r ON r.id_reserva = a.id_reserva
                        WHERE a.id_parquing = ? ORDER BY a.id_acces DESC LIMIT $limit", [$idParquing]);
    }
}
