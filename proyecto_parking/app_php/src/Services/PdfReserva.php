<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\MetodePagament;

/**
 * Justificant de reserva en PDF amb FPDF (paquet Ubuntu php-fpdf).
 * Inclou el número de reserva en codi de barres Code 39 per al lector de l'entrada.
 * FPDF fa servir la codificació Windows-1252: els textos es converteixen amb t().
 */
final class PdfReserva
{
    private const FPDF = ['/usr/share/php/fpdf/fpdf.php', APP_ROOT . '/vendor/fpdf/fpdf.php'];

    public static function disponible(): bool
    {
        foreach (self::FPDF as $f) {
            if (is_file($f)) {
                return true;
            }
        }
        return false;
    }

    public static function generar(array $r): string
    {
        foreach (self::FPDF as $f) {
            if (is_file($f)) {
                require_once $f;
                break;
            }
        }
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle(self::t('Reserva ' . $r['numero_reserva']), false);
        $pdf->SetAuthor('CSJ Parkings');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Capçalera fosca amb el logotip
        $pdf->SetFillColor(21, 23, 28);
        $pdf->Rect(0, 0, 210, 38, 'F');
        $pdf->Image(__DIR__ . '/logo-pdf.jpg', 12, 7, 85);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetXY(110, 11);
        $pdf->Cell(88, 7, self::t('JUSTIFICANT DE RESERVA'), 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(88, 5, self::t('Emès el ' . date('d/m/Y \a \l\e\s H:i')), 0, 2, 'R');

        // Número i codi de barres
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(12, 46);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, self::t('Número de reserva'), 0, 1);
        $pdf->SetX(12);
        $pdf->SetFont('Courier', 'B', 22);
        $pdf->Cell(0, 11, $r['numero_reserva'], 0, 1);
        self::code39($pdf, $r['numero_reserva'], 12, 64, 0.35, 16);
        $pdf->SetXY(130, 48);
        $pdf->SetFont('Helvetica', 'B', 12);
        $estat = estat_reserva($r['estat']);
        [$cr, $cg, $cb] = $r['estat'] === 'cancellada' ? [198, 40, 40] : [46, 125, 50];
        $pdf->SetTextColor($cr, $cg, $cb);
        $pdf->Cell(68, 8, self::t('Estat: ' . $estat), 0, 1, 'R');
        $pdf->SetTextColor(20, 20, 20);

        $y = 88;
        $y = self::bloc($pdf, $y, 'Pàrquing', [
            'Pàrquing'  => $r['parquing'],
            'Adreça'    => $r['adreca'] . ', ' . $r['codi_postal'] . ' Tarragona',
            'Horari'    => $r['horari'] . ($r['telefon_parquing'] ? '   ·   Tel. ' . $r['telefon_parquing'] : ''),
            'Plaça'     => ($r['codi_placa'] ?? '—') . ($r['planta'] ? ' (planta ' . $r['planta'] . ')' : ''),
        ]);
        $y = self::bloc($pdf, $y, 'Estada', [
            'Entrada'   => date('d/m/Y H:i', strtotime($r['entrada_prevista'])),
            'Sortida'   => date('d/m/Y H:i', strtotime($r['sortida_prevista'])),
        ]);
        $y = self::bloc($pdf, $y, 'Client i vehicle', [
            'Titular'   => trim($r['nom'] . ' ' . $r['primer_cognom'] . ' ' . ($r['segon_cognom'] ?? '')),
            'Vehicle'   => $r['marca'] . ' ' . $r['model'] . ($r['color'] ? ' · ' . $r['color'] : ''),
            'Matrícula' => $r['matricula'],
        ]);
        $metode = MetodePagament::descripcio(['tipus' => $r['metode_tipus'], 'marca_targeta' => $r['marca_targeta'],
            'ultims_digits' => $r['ultims_digits'], 'referencia_xifrada' => $r['referencia_xifrada']]);
        $y = self::bloc($pdf, $y, 'Import', [
            'Preu de l\'estada'  => euro($r['preu_base']),
            'Despeses de gestió' => euro($r['despeses_gestio']),
            'TOTAL'              => euro($r['preu_total']),
            'Mètode de pagament' => $metode,
        ]);

        $pdf->SetXY(12, $y + 4);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->MultiCell(186, 4.5, self::t(
            "Instruccions: a l'entrada del pàrquing el lector reconeixerà la matrícula del vehicle i obrirà la barrera. "
            . "Si el lector no funciona, mostreu aquest justificant (codi de barres) a l'interfon. Es pot entrar fins a 30 minuts "
            . "abans de l'hora reservada. Si se surt més tard de l'hora prevista (amb 5 minuts de cortesia), l'excés de temps es "
            . "cobrarà automàticament amb el mateix mètode de pagament. Les reserves es poden cancel·lar des de l'apartat "
            . "\"Les meves reserves\" abans de l'hora d'entrada (les despeses de gestió no són reemborsables)."
        ));
        $pdf->SetY(-22);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->Cell(0, 5, self::t('CSJ Parkings · Pàrquings municipals de Tarragona · https://csjparkings.local'), 0, 0, 'C');

        return $pdf->Output('S');
    }

    private static function bloc(\FPDF $pdf, float $y, string $titol, array $files): float
    {
        $pdf->SetXY(12, $y);
        $pdf->SetFillColor(255, 159, 28);
        $pdf->Rect(12, $y + 1, 2, 5, 'F');
        $pdf->SetX(16);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell(0, 7, self::t($titol), 0, 1);
        foreach ($files as $etiqueta => $valor) {
            $negreta = $etiqueta === 'TOTAL';
            $pdf->SetX(16);
            $pdf->SetFont('Helvetica', $negreta ? 'B' : '', 9.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(45, 6, self::t($etiqueta), 'B');
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetFont('Helvetica', $negreta ? 'B' : '', $negreta ? 11 : 9.5);
            $pdf->Cell(137, 6, self::t((string) $valor), 'B', 1);
        }
        return $pdf->GetY() + 4;
    }

    /** Converteix UTF-8 a Windows-1252 (accents, ·, €) per a les fonts estàndard de FPDF. */
    private static function t(string $text): string
    {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    /** Dibuixa un codi de barres Code 39 (lletres, xifres i guió) amb rectangles. */
    private static function code39(\FPDF $pdf, string $text, float $x, float $y, float $estret, float $alt): void
    {
        $taula = [
            '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw',
            '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
            'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
            'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
            'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn',
            'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
            'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn',
            'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '*' => 'nwnnwnwnn',
        ];
        $ample = $estret * 2.5;
        $pdf->SetFillColor(0, 0, 0);
        foreach (str_split('*' . strtoupper($text) . '*') as $c) {
            if (!isset($taula[$c])) {
                continue;
            }
            foreach (str_split($taula[$c]) as $i => $amplada) {
                $w = $amplada === 'w' ? $ample : $estret;
                if ($i % 2 === 0) {
                    $pdf->Rect($x, $y, $w, $alt, 'F');
                }
                $x += $w;
            }
            $x += $estret;   // separació entre caràcters
        }
    }
}
