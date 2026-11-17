<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Auditoria: cada canvi d'una reserva queda registrat a historial_reserves. */
final class Historial
{
    public static function registrar(int $idReserva, ?int $idUsuari, string $accio, string $detall): void
    {
        DB::run('INSERT INTO historial_reserves (id_reserva, id_usuari, accio, detall) VALUES (?,?,?,?)',
            [$idReserva, $idUsuari, $accio, mb_substr($detall, 0, 500)]);
    }

    public static function deReserva(int $idReserva): array
    {
        return DB::all("SELECT h.*, CONCAT_WS(' ', u.nom, u.primer_cognom) AS usuari, u.rol
                        FROM historial_reserves h LEFT JOIN usuaris u ON u.id_usuari = h.id_usuari
                        WHERE h.id_reserva = ? ORDER BY h.data_hora, h.id_historial", [$idReserva]);
    }
}
