<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Pàrquings i places. Els pàrquings no s'esborren: es desactiven (actiu = 0). */
final class Parquing
{
    public const TIPUS = ['subterrani' => 'Subterrani', 'superficie' => 'Superfície', 'plantes' => 'En plantes'];
    public const SERVEIS = ['cobert', 'cctv', 'pmr', 'barrera', 'recarrega', 'ascensor', 'moto', '24h'];
    public const TIPUS_PLACA = ['general' => 'General', 'pmr' => 'PMR', 'electric' => 'Recàrrega elèctrica', 'moto' => 'Moto'];

    /**
     * Llista amb l'ocupació actual: places operatives (disponibles) i places
     * ocupades ara (reserves vigents en aquest moment o vehicles encara dins).
     */
    public static function ambOcupacio(bool $nomesActius = true): array
    {
        $sql = "SELECT p.*,
                   (SELECT COUNT(*) FROM places pl WHERE pl.id_parquing = p.id_parquing AND pl.estat = 'disponible') AS places_operatives,
                   (SELECT COUNT(*) FROM reserves r WHERE r.id_parquing = p.id_parquing
                        AND (r.estat = 'en_curs'
                             OR (r.estat IN ('pendent','confirmada') AND NOW() >= r.entrada_prevista AND NOW() < r.sortida_prevista))
                   ) AS ocupades_ara
                FROM parquings p " . ($nomesActius ? 'WHERE p.actiu = 1 ' : '') . 'ORDER BY p.nom';
        $files = DB::all($sql);
        foreach ($files as &$f) {
            $f['lliures_ara'] = max(0, (int) $f['places_operatives'] - (int) $f['ocupades_ara']);
        }
        return $files;
    }

    public static function actius(): array
    {
        return DB::all('SELECT id_parquing, nom, adreca, horari FROM parquings WHERE actiu = 1 ORDER BY nom');
    }

    public static function tots(): array
    {
        return DB::all('SELECT id_parquing, nom, actiu FROM parquings ORDER BY nom');
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM parquings WHERE id_parquing = ?', [$id]);
    }

    public static function crear(array $d): int
    {
        DB::run('INSERT INTO parquings (nom, adreca, codi_postal, latitud, longitud, tipus, places_totals, places_reservables,
                 horari, telefon, descripcio, serveis, actiu) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?)', [
            $d['nom'], $d['adreca'], $d['codi_postal'], $d['latitud'], $d['longitud'], $d['tipus'], $d['places_totals'],
            $d['horari'], $d['telefon'] ?: null, $d['descripcio'] ?: null, $d['serveis'] ?: null, $d['actiu'],
        ]);
        return DB::lastId();
    }

    public static function actualitzar(int $id, array $d): void
    {
        DB::run('UPDATE parquings SET nom=?, adreca=?, codi_postal=?, latitud=?, longitud=?, tipus=?, places_totals=?,
                 horari=?, telefon=?, descripcio=?, serveis=?, actiu=? WHERE id_parquing=?', [
            $d['nom'], $d['adreca'], $d['codi_postal'], $d['latitud'], $d['longitud'], $d['tipus'], $d['places_totals'],
            $d['horari'], $d['telefon'] ?: null, $d['descripcio'] ?: null, $d['serveis'] ?: null, $d['actiu'], $id,
        ]);
    }

    // ------------------------------------------------------------- places

    public static function places(int $idParquing): array
    {
        return DB::all("SELECT pl.*,
                   (SELECT r.numero_reserva FROM reserves r WHERE r.id_placa = pl.id_placa
                     AND (r.estat = 'en_curs' OR (r.estat IN ('pendent','confirmada') AND NOW() >= r.entrada_prevista AND NOW() < r.sortida_prevista))
                     LIMIT 1) AS reserva_ara
                FROM places pl WHERE pl.id_parquing = ? ORDER BY pl.codi_placa", [$idParquing]);
    }

    public static function findPlaca(int $idPlaca): ?array
    {
        return DB::one('SELECT * FROM places WHERE id_placa = ?', [$idPlaca]);
    }

    public static function afegirPlaca(int $idParquing, string $codi, ?string $planta, string $tipus): void
    {
        DB::run('INSERT INTO places (id_parquing, codi_placa, planta, tipus) VALUES (?,?,?,?)', [$idParquing, $codi, $planta ?: null, $tipus]);
        self::sincronitzarReservables($idParquing);
    }

    public static function actualitzarPlaca(int $idPlaca, string $tipus, string $estat, ?string $planta): void
    {
        DB::run('UPDATE places SET tipus = ?, estat = ?, planta = ? WHERE id_placa = ?', [$tipus, $estat, $planta ?: null, $idPlaca]);
        $p = self::findPlaca($idPlaca);
        self::sincronitzarReservables((int) $p['id_parquing']);
    }

    /** places_reservables = nombre de places operatives (manté la coherència amb la taula places). */
    public static function sincronitzarReservables(int $idParquing): void
    {
        DB::run("UPDATE parquings p SET p.places_reservables = LEAST(p.places_totals,
                    (SELECT COUNT(*) FROM places pl WHERE pl.id_parquing = p.id_parquing AND pl.estat = 'disponible'))
                 WHERE p.id_parquing = ?", [$idParquing]);
    }

    /** Següent codi de plaça lliure: R-033, R-034... */
    public static function seguentCodiPlaca(int $idParquing): string
    {
        $max = (int) DB::value("SELECT MAX(CAST(SUBSTRING(codi_placa, 3) AS UNSIGNED)) FROM places
                                WHERE id_parquing = ? AND codi_placa LIKE 'R-%'", [$idParquing]);
        return 'R-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
