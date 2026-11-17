<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;
use App\Services\CalculPreu;

/**
 * Reserves. MAI s'eliminen: només canvien d'estat (cancel·lació = esborrat lògic).
 * Estats: pendent -> confirmada -> en_curs -> completada  ·  cancellada
 */
final class Reserva
{
    public const ESTATS = ['pendent', 'confirmada', 'en_curs', 'completada', 'cancellada'];
    public const ESTATS_ACTIUS = "'pendent','confirmada','en_curs'";
    public const PER_PAGINA = 25;

    // ------------------------------------------------------- disponibilitat

    /**
     * Primera plaça lliure en la franja [entrada, sortida) segons el tipus de vehicle.
     * Una plaça està lliure si està operativa i cap reserva activa s'hi solapa.
     */
    public static function placaLliure(int $idParquing, string $entrada, string $sortida, ?array $vehicle = null, int $excloure = 0): ?array
    {
        $tipus = $vehicle['tipus'] ?? 'cotxe';
        if ($tipus === 'moto') {
            $ordre = "FIELD(pl.tipus, 'moto', 'general', 'electric', 'pmr')";
            $filtre = '';
        } else {
            // Els cotxes no ocupen places de moto; els elèctrics prefereixen places amb recàrrega
            $ordre = !empty($vehicle['electric']) ? "FIELD(pl.tipus, 'electric', 'general', 'pmr')" : "FIELD(pl.tipus, 'general', 'electric', 'pmr')";
            $filtre = "AND pl.tipus <> 'moto'";
        }
        return DB::one("SELECT pl.* FROM places pl
                        WHERE pl.id_parquing = ? AND pl.estat = 'disponible' $filtre
                          AND NOT EXISTS (SELECT 1 FROM reserves r WHERE r.id_placa = pl.id_placa
                                AND r.estat IN (" . self::ESTATS_ACTIUS . ")
                                AND r.entrada_prevista < ? AND r.sortida_prevista > ? AND r.id_reserva <> ?)
                        ORDER BY $ordre, pl.codi_placa LIMIT 1", [$idParquing, $sortida, $entrada, $excloure]);
    }

    /** Nombre de places lliures d'un pàrquing en una franja. */
    public static function placesLliures(int $idParquing, string $entrada, string $sortida): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM places pl
                        WHERE pl.id_parquing = ? AND pl.estat = 'disponible'
                          AND NOT EXISTS (SELECT 1 FROM reserves r WHERE r.id_placa = pl.id_placa
                                AND r.estat IN (" . self::ESTATS_ACTIUS . ")
                                AND r.entrada_prevista < ? AND r.sortida_prevista > ?)", [$idParquing, $sortida, $entrada]);
    }

    // ------------------------------------------------------------- creació

    /**
     * Crea la reserva dins d'una transacció:
     *  1. Bloqueja la fila del pàrquing (FOR UPDATE) perquè dues reserves simultànies
     *     no puguin agafar la mateixa plaça.
     *  2. Busca una plaça lliure i calcula el preu al servidor (valor definitiu).
     *  3. Inserta la reserva, el pagament (simulat) i l'historial.
     *
     * @return array{id:int, numero:string}
     */
    public static function crear(array $usuari, array $vehicle, array $metode, int $idParquing, string $entrada, string $sortida): array
    {
        return DB::transaction(static function () use ($usuari, $vehicle, $metode, $idParquing, $entrada, $sortida) {
            DB::run('SELECT id_parquing FROM parquings WHERE id_parquing = ? AND actiu = 1 FOR UPDATE', [$idParquing]);

            $placa = self::placaLliure($idParquing, $entrada, $sortida, $vehicle);
            if (!$placa) {
                throw new \DomainException('No queden places lliures en aquest pàrquing per a la franja escollida.');
            }

            $preu = (new CalculPreu(Tarifa::perParquing($idParquing)))->calcular(new \DateTime($entrada), new \DateTime($sortida));
            $numero = self::nouNumero($entrada);

            DB::run("INSERT INTO reserves (numero_reserva, id_usuari, id_vehicle, id_parquing, id_placa, id_metode_pagament,
                        entrada_prevista, sortida_prevista, preu_base, despeses_gestio, preu_total, estat,
                        condicions_acceptades, data_acceptacio)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,'confirmada',1,NOW())", [
                $numero, $usuari['id_usuari'], $vehicle['id_vehicle'], $idParquing, $placa['id_placa'], $metode['id_metode'],
                $entrada, $sortida, $preu['preu_base'], $preu['despeses_gestio'], $preu['preu_total'],
            ]);
            $id = DB::lastId();

            self::pagament($id, (int) $metode['id_metode'], 'reserva', $preu['preu_total']);
            Historial::registrar($id, (int) $usuari['id_usuari'], 'creacio',
                sprintf('Reserva creada i pagada (%s). Plaça %s. Condicions acceptades.', euro($preu['preu_total']), $placa['codi_placa']));

            return ['id' => $id, 'numero' => $numero];
        });
    }

    /** Número de reserva únic: CSJ-AAAAMMDD-XXXXXX (data d'entrada + 6 caràcters aleatoris). */
    private static function nouNumero(string $entrada): string
    {
        $alfabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sense 0/O ni 1/I per evitar confusions
        do {
            $codi = '';
            for ($i = 0; $i < 6; $i++) {
                $codi .= $alfabet[random_int(0, strlen($alfabet) - 1)];
            }
            $numero = 'CSJ-' . date('Ymd', strtotime($entrada)) . '-' . $codi;
        } while (DB::value('SELECT COUNT(*) FROM reserves WHERE numero_reserva = ?', [$numero]));
        return $numero;
    }

    /** Registra un pagament simulat (la passarel·la sempre accepta). */
    public static function pagament(int $idReserva, int $idMetode, string $concepte, float $import): void
    {
        DB::run("INSERT INTO pagaments (id_reserva, id_metode_pagament, concepte, import, estat, referencia)
                 VALUES (?,?,?,?,'completat',?)", [$idReserva, $idMetode, $concepte, round($import, 2), 'SIM-' . strtoupper(bin2hex(random_bytes(6)))]);
    }

    // ------------------------------------------------------------- consultes

    public static function find(int $id): ?array
    {
        return DB::one("SELECT r.*, u.nom, u.primer_cognom, u.segon_cognom, u.email,
                               v.matricula, v.marca, v.model, v.color, v.tipus AS tipus_vehicle,
                               p.nom AS parquing, p.adreca, p.codi_postal, p.telefon AS telefon_parquing, p.horari,
                               pl.codi_placa, pl.planta, pl.tipus AS tipus_placa,
                               mp.tipus AS metode_tipus, mp.marca_targeta, mp.ultims_digits, mp.referencia_xifrada
                        FROM reserves r
                        JOIN usuaris u ON u.id_usuari = r.id_usuari
                        JOIN vehicles v ON v.id_vehicle = r.id_vehicle
                        JOIN parquings p ON p.id_parquing = r.id_parquing
                        LEFT JOIN places pl ON pl.id_placa = r.id_placa
                        JOIN metodes_pagament mp ON mp.id_metode = r.id_metode_pagament
                        WHERE r.id_reserva = ?", [$id]);
    }

    public static function perNumero(string $numero): ?array
    {
        $id = DB::value('SELECT id_reserva FROM reserves WHERE numero_reserva = ?', [$numero]);
        return $id ? self::find((int) $id) : null;
    }

    public static function perUsuari(int $idUsuari): array
    {
        return DB::all('SELECT * FROM v_reserves_detall WHERE id_usuari = ? ORDER BY entrada_prevista DESC', [$idUsuari]);
    }

    public static function pagaments(int $idReserva): array
    {
        return DB::all('SELECT * FROM pagaments WHERE id_reserva = ? ORDER BY data_pagament, id_pagament', [$idReserva]);
    }

    /**
     * Cerca avançada (panel d'administració): text lliure (número, client, email),
     * matrícula, pàrquing, estat i interval de dates d'entrada.
     * @return array{files:array, total:int}
     */
    public static function cercar(array $f, int $pagina = 1): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(numero_reserva LIKE ? OR client LIKE ? OR email LIKE ?)';
            array_push($params, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        if (($f['matricula'] ?? '') !== '') {
            $where[] = 'matricula LIKE ?';
            $params[] = '%' . normalitzar_matricula($f['matricula']) . '%';
        }
        if (!empty($f['parquing'])) {
            $where[] = 'id_parquing = ?';
            $params[] = (int) $f['parquing'];
        }
        if (in_array($f['estat'] ?? '', self::ESTATS, true)) {
            $where[] = 'estat = ?';
            $params[] = $f['estat'];
        }
        if (!empty($f['des_de'])) {
            $where[] = 'entrada_prevista >= ?';
            $params[] = $f['des_de'] . ' 00:00:00';
        }
        if (!empty($f['fins_a'])) {
            $where[] = 'entrada_prevista <= ?';
            $params[] = $f['fins_a'] . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        $total = (int) DB::value("SELECT COUNT(*) FROM v_reserves_detall WHERE $w", $params);
        $offset = max(0, ($pagina - 1) * self::PER_PAGINA);
        $files = DB::all("SELECT * FROM v_reserves_detall WHERE $w ORDER BY entrada_prevista DESC LIMIT " . self::PER_PAGINA . " OFFSET $offset", $params);
        return ['files' => $files, 'total' => $total];
    }

    /** Reserves d'un pàrquing que comencen, acaben o estan en curs un dia concret (operador). */
    public static function delDia(int $idParquing, string $dia): array
    {
        return DB::all("SELECT * FROM v_reserves_detall WHERE id_parquing = ?
                        AND (DATE(entrada_prevista) = ? OR DATE(sortida_prevista) = ? OR estat = 'en_curs')
                        ORDER BY entrada_prevista", [$idParquing, $dia, $dia]);
    }

    // ------------------------------------------------------- canvis d'estat

    /** Una reserva es pot cancel·lar si encara no s'ha fet servir (pendent o confirmada). */
    public static function esCancellable(array $r): bool
    {
        return in_array($r['estat'], ['pendent', 'confirmada'], true);
    }

    /**
     * Cancel·lació (esborrat lògic). Es reemborsa el preu base;
     * les despeses de gestió no són reemborsables (condicions d'ús).
     */
    public static function cancellar(array $r, int $idUsuari, string $motiu): void
    {
        DB::transaction(static function () use ($r, $idUsuari, $motiu) {
            DB::run("UPDATE reserves SET estat = 'cancellada', cancellada_a = NOW(), cancellada_per = ?, motiu_cancellacio = ?
                     WHERE id_reserva = ?", [$idUsuari, mb_substr($motiu, 0, 255), $r['id_reserva']]);
            if ((float) $r['preu_base'] > 0) {
                self::pagament((int) $r['id_reserva'], (int) $r['id_metode_pagament'], 'reemborsament', (float) $r['preu_base']);
            }
            Historial::registrar((int) $r['id_reserva'], $idUsuari, 'cancellacio',
                sprintf('estat: %s -> cancellada. Motiu: %s. Reemborsament: %s', $r['estat'], $motiu, euro($r['preu_base'])));
        });
    }

    /** Canvi d'estat manual (administrador/operador). */
    public static function canviarEstat(array $r, string $nouEstat, int $idUsuari, string $nota = ''): void
    {
        if ($nouEstat === 'cancellada') {
            self::cancellar($r, $idUsuari, $nota ?: 'Cancel·lada per l\'administració');
            return;
        }
        DB::transaction(static function () use ($r, $nouEstat, $idUsuari, $nota) {
            DB::run('UPDATE reserves SET estat = ? WHERE id_reserva = ?', [$nouEstat, $r['id_reserva']]);
            Historial::registrar((int) $r['id_reserva'], $idUsuari, 'canvi_estat',
                "estat: {$r['estat']} -> $nouEstat" . ($nota !== '' ? ". $nota" : ''));
        });
    }

    /**
     * Modificació de dates/observacions per l'administració. Es torna a comprovar
     * la disponibilitat i es recalcula el preu; la diferència es cobra o es reemborsa.
     */
    public static function modificar(array $r, string $entrada, string $sortida, string $observacions, int $idUsuari): void
    {
        DB::transaction(static function () use ($r, $entrada, $sortida, $observacions, $idUsuari) {
            DB::run('SELECT id_parquing FROM parquings WHERE id_parquing = ? FOR UPDATE', [$r['id_parquing']]);
            $canvis = [];
            $idPlaca = $r['id_placa'];
            $preu = ['preu_base' => (float) $r['preu_base'], 'despeses_gestio' => (float) $r['despeses_gestio'], 'preu_total' => (float) $r['preu_total']];

            if ($entrada !== $r['entrada_prevista'] || $sortida !== $r['sortida_prevista']) {
                $vehicle = ['tipus' => $r['tipus_vehicle'], 'electric' => 0];
                // Si la plaça actual continua lliure es manté; si no, se'n busca una altra
                $placa = self::placaLliure((int) $r['id_parquing'], $entrada, $sortida, $vehicle, (int) $r['id_reserva']);
                $actualLliure = $r['id_placa'] && !DB::value("SELECT COUNT(*) FROM reserves WHERE id_placa = ? AND id_reserva <> ?
                        AND estat IN (" . self::ESTATS_ACTIUS . ") AND entrada_prevista < ? AND sortida_prevista > ?",
                        [$r['id_placa'], $r['id_reserva'], $sortida, $entrada]);
                if (!$actualLliure && !$placa) {
                    throw new \DomainException('No hi ha cap plaça lliure per a la nova franja.');
                }
                if (!$actualLliure) {
                    $idPlaca = $placa['id_placa'];
                    $canvis[] = "plaça: {$r['codi_placa']} -> {$placa['codi_placa']}";
                }
                $preu = (new CalculPreu(Tarifa::perParquing((int) $r['id_parquing'])))->calcular(new \DateTime($entrada), new \DateTime($sortida));
                $canvis[] = 'franja: ' . data_hora($r['entrada_prevista']) . '–' . data_hora($r['sortida_prevista'])
                          . ' -> ' . data_hora($entrada) . '–' . data_hora($sortida);
                $diferencia = round($preu['preu_total'] - (float) $r['preu_total'], 2);
                if ($diferencia != 0.0) {
                    $canvis[] = 'preu: ' . euro($r['preu_total']) . ' -> ' . euro($preu['preu_total']);
                    self::pagament((int) $r['id_reserva'], (int) $r['id_metode_pagament'],
                        $diferencia > 0 ? 'reserva' : 'reemborsament', abs($diferencia));
                }
            }
            if ($observacions !== (string) $r['observacions']) {
                $canvis[] = 'observacions actualitzades';
            }
            if (!$canvis) {
                return;
            }
            DB::run('UPDATE reserves SET entrada_prevista = ?, sortida_prevista = ?, id_placa = ?, preu_base = ?,
                     despeses_gestio = ?, preu_total = ?, observacions = ? WHERE id_reserva = ?', [
                $entrada, $sortida, $idPlaca, $preu['preu_base'], $preu['despeses_gestio'], $preu['preu_total'],
                $observacions !== '' ? mb_substr($observacions, 0, 255) : null, $r['id_reserva'],
            ]);
            Historial::registrar((int) $r['id_reserva'], $idUsuari, 'modificacio', implode('; ', $canvis));
        });
    }

    // ------------------------------------------------------------ estadístiques

    public static function estadistiques(): array
    {
        return DB::one("SELECT
                (SELECT COUNT(*) FROM reserves WHERE DATE(entrada_prevista) = CURDATE() AND estat <> 'cancellada') AS reserves_avui,
                (SELECT COUNT(*) FROM reserves WHERE estat = 'en_curs') AS en_curs,
                (SELECT COUNT(*) FROM reserves WHERE estat IN ('pendent','confirmada') AND entrada_prevista > NOW()) AS futures,
                (SELECT COUNT(*) FROM usuaris WHERE rol = 'client' AND actiu = 1) AS clients,
                (SELECT IFNULL(SUM(CASE WHEN concepte = 'reemborsament' THEN -import ELSE import END), 0)
                   FROM pagaments WHERE estat = 'completat' AND YEAR(data_pagament) = YEAR(CURDATE())
                   AND MONTH(data_pagament) = MONTH(CURDATE())) AS ingressos_mes") ?? [];
    }
}
