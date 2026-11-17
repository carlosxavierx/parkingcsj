<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Càlcul del preu d'una estada amb les tarifes de la BD.
 *
 * Regles (taula tarifes):
 *  - Es compta minut a minut des de l'hora d'entrada.
 *  - Minuts entre inici_nocturn (22 h) i fi_nocturn (6 h): preu_nocturn €/h.
 *  - Resta de minuts: durant les primeres "hores_inicials" (2 h) de l'estada,
 *    preu_hora_inicial €/h; a partir de la 3a hora, preu_hora_extra €/h.
 *  - Cada dia natural té un màxim (maxim_diari €).
 *  - Despeses de gestió: percentatge_gestio % sobre el preu base.
 *
 * public/js/app.js fa EXACTAMENT el mateix càlcul com a vista prèvia; el valor
 * que es guarda a la BD és sempre el que calcula aquesta classe al servidor.
 */
final class CalculPreu
{
    public const MINUTS_MINIMS = 30;
    public const DIES_MAXIMS = 30;

    public function __construct(private array $tarifes)
    {
    }

    /**
     * @return array{minuts:int, preu_base:float, despeses_gestio:float, preu_total:float, detall:array}
     */
    public function calcular(\DateTimeInterface $entrada, \DateTimeInterface $sortida): array
    {
        $t = $this->tarifes;
        $minutsTotals = intdiv($sortida->getTimestamp() - $entrada->getTimestamp(), 60);
        if ($minutsTotals <= 0) {
            return ['minuts' => 0, 'preu_base' => 0.0, 'despeses_gestio' => 0.0, 'preu_total' => 0.0, 'detall' => []];
        }

        $minutsInicials = (int) round((float) $t['hores_inicials'] * 60);
        $iniciNit = (float) $t['inici_nocturn'];
        $fiNit = (float) $t['fi_nocturn'];

        // Minuts de cada tipus agrupats per dia natural
        $dies = [];
        $cursor = \DateTimeImmutable::createFromInterface($entrada);
        for ($m = 0; $m < $minutsTotals; $m++) {
            $dia = $cursor->format('Y-m-d');
            $hora = (int) $cursor->format('G') + ((int) $cursor->format('i')) / 60;
            $nit = $iniciNit > $fiNit ? ($hora >= $iniciNit || $hora < $fiNit) : ($hora >= $iniciNit && $hora < $fiNit);
            $tipus = $nit ? 'nocturn' : ($m < $minutsInicials ? 'inicial' : 'extra');
            $dies[$dia][$tipus] = ($dies[$dia][$tipus] ?? 0) + 1;
            $cursor = $cursor->modify('+1 minute');
        }

        $base = 0.0;
        $detall = [];
        foreach ($dies as $dia => $minuts) {
            $import = (($minuts['inicial'] ?? 0) * (float) $t['preu_hora_inicial']
                    + ($minuts['extra'] ?? 0) * (float) $t['preu_hora_extra']
                    + ($minuts['nocturn'] ?? 0) * (float) $t['preu_nocturn']) / 60;
            $topat = $import > (float) $t['maxim_diari'];
            $import = round(min($import, (float) $t['maxim_diari']), 2);
            $detall[] = ['dia' => $dia, 'minuts' => $minuts, 'import' => $import, 'maxim_aplicat' => $topat];
            $base += $import;
        }

        $base = round($base, 2);
        $gestio = round($base * (float) $t['percentatge_gestio'] / 100, 2);

        return [
            'minuts'          => $minutsTotals,
            'preu_base'       => $base,
            'despeses_gestio' => $gestio,
            'preu_total'      => round($base + $gestio, 2),
            'detall'          => $detall,
        ];
    }
}
