<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * Tarifes configurables. id_parquing NULL = tarifa general; si un pàrquing té
 * una tarifa pròpia amb el mateix codi, aquesta substitueix la general.
 */
final class Tarifa
{
    public const CODIS = [
        'preu_hora_inicial'  => 'Preu de la 1a i 2a hora (€/h)',
        'hores_inicials'     => 'Nombre d\'hores amb el preu inicial',
        'preu_hora_extra'    => 'Preu a partir de la 3a hora (€/h)',
        'preu_nocturn'       => 'Preu de l\'hora nocturna (€/h)',
        'inici_nocturn'      => 'Hora d\'inici de la tarifa nocturna',
        'fi_nocturn'         => 'Hora de fi de la tarifa nocturna',
        'maxim_diari'        => 'Preu màxim per dia (€)',
        'percentatge_gestio' => 'Despeses de gestió (%)',
    ];

    /** Tarifes efectives d'un pàrquing: codi => valor */
    public static function perParquing(int $idParquing): array
    {
        $files = DB::all('SELECT codi, valor FROM tarifes WHERE actiu = 1 AND (id_parquing IS NULL OR id_parquing = ?)
                          ORDER BY id_parquing IS NOT NULL', [$idParquing]);
        $t = [];
        foreach ($files as $f) {
            $t[$f['codi']] = (float) $f['valor'];   // la pròpia (ordenada al final) sobreescriu la general
        }
        foreach (array_keys(self::CODIS) as $codi) {
            if (!isset($t[$codi])) {
                throw new \RuntimeException("Falta la tarifa '$codi'");
            }
        }
        return $t;
    }

    /** Tarifes efectives de tots els pàrquings actius (per a la vista prèvia en JavaScript). */
    public static function totesEfectives(): array
    {
        $res = [];
        foreach (Parquing::actius() as $p) {
            $res[$p['id_parquing']] = self::perParquing((int) $p['id_parquing']);
        }
        return $res;
    }

    public static function totes(): array
    {
        return DB::all('SELECT t.*, p.nom AS parquing FROM tarifes t LEFT JOIN parquings p ON p.id_parquing = t.id_parquing
                        ORDER BY t.id_parquing IS NOT NULL, p.nom, FIELD(t.codi, ' . self::llistaCodis() . ')');
    }

    public static function actualitzar(int $id, float $valor, bool $actiu): void
    {
        DB::run('UPDATE tarifes SET valor = ?, actiu = ? WHERE id_tarifa = ?', [$valor, (int) $actiu, $id]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM tarifes WHERE id_tarifa = ?', [$id]);
    }

    /** Crea (o reactiva) una tarifa pròpia d'un pàrquing. */
    public static function crearPropia(int $idParquing, string $codi, float $valor): void
    {
        $existent = DB::value('SELECT id_tarifa FROM tarifes WHERE id_parquing = ? AND codi = ?', [$idParquing, $codi]);
        if ($existent) {
            self::actualitzar((int) $existent, $valor, true);
            return;
        }
        DB::run('INSERT INTO tarifes (id_parquing, codi, descripcio, valor) VALUES (?,?,?,?)',
            [$idParquing, $codi, self::CODIS[$codi], $valor]);
    }

    private static function llistaCodis(): string
    {
        return "'" . implode("','", array_keys(self::CODIS)) . "'";
    }
}
