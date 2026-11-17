<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database as DB;

/**
 * Mètodes de pagament SIMULATS. De la targeta només es guarden els 4 últims
 * dígits i un token aleatori de la "passarel·la": mai el número complet ni el CVV.
 * L'email de PayPal o el telèfon de Bizum es guarden xifrats.
 */
final class MetodePagament
{
    public const TIPUS = ['targeta' => 'Targeta', 'paypal' => 'PayPal', 'bizum' => 'Bizum'];

    public static function perUsuari(int $idUsuari): array
    {
        return DB::all('SELECT * FROM metodes_pagament WHERE id_usuari = ? AND actiu = 1 ORDER BY per_defecte DESC, data_alta', [$idUsuari]);
    }

    public static function delUsuari(int $id, int $idUsuari): ?array
    {
        return DB::one('SELECT * FROM metodes_pagament WHERE id_metode = ? AND id_usuari = ? AND actiu = 1', [$id, $idUsuari]);
    }

    public static function crear(int $idUsuari, array $d): void
    {
        $primer = !DB::value('SELECT COUNT(*) FROM metodes_pagament WHERE id_usuari = ? AND actiu = 1', [$idUsuari]);
        DB::run('INSERT INTO metodes_pagament (id_usuari, tipus, titular, marca_targeta, ultims_digits, caducitat,
                 referencia_xifrada, token_passarella, per_defecte) VALUES (?,?,?,?,?,?,?,?,?)', [
            $idUsuari, $d['tipus'], $d['titular'], $d['marca_targeta'] ?? null, $d['ultims_digits'] ?? null,
            $d['caducitat'] ?? null, Crypto::encrypt($d['referencia'] ?? null), bin2hex(random_bytes(16)), (int) $primer,
        ]);
    }

    public static function marcarPerDefecte(int $id, int $idUsuari): void
    {
        DB::transaction(static function () use ($id, $idUsuari) {
            DB::run('UPDATE metodes_pagament SET per_defecte = 0 WHERE id_usuari = ?', [$idUsuari]);
            DB::run('UPDATE metodes_pagament SET per_defecte = 1 WHERE id_metode = ? AND id_usuari = ?', [$id, $idUsuari]);
        });
    }

    public static function desactivar(int $id, int $idUsuari): void
    {
        DB::run('UPDATE metodes_pagament SET actiu = 0, per_defecte = 0 WHERE id_metode = ? AND id_usuari = ?', [$id, $idUsuari]);
    }

    /** Text descriptiu: "Visa ···· 4242", "PayPal (j•••@gmail.com)"... */
    public static function descripcio(array $m): string
    {
        return match ($m['tipus']) {
            'targeta' => ($m['marca_targeta'] ?: 'Targeta') . ' ···· ' . $m['ultims_digits'],
            'paypal'  => 'PayPal · ' . Crypto::mask(Crypto::decrypt($m['referencia_xifrada'] ?? null), 10),
            'bizum'   => 'Bizum · ' . Crypto::mask(Crypto::decrypt($m['referencia_xifrada'] ?? null), 3),
            default   => $m['tipus'],
        };
    }

    /** Algorisme de Luhn: comprova que el número de targeta sigui vàlid. */
    public static function luhn(string $numero): bool
    {
        $suma = 0;
        $parell = false;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $d = (int) $numero[$i];
            if ($parell && ($d *= 2) > 9) {
                $d -= 9;
            }
            $suma += $d;
            $parell = !$parell;
        }
        return $suma % 10 === 0;
    }

    public static function marcaTargeta(string $numero): string
    {
        return match (true) {
            str_starts_with($numero, '4')                     => 'Visa',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $numero)  => 'Mastercard',
            (bool) preg_match('/^3[47]/', $numero)            => 'American Express',
            default                                           => 'Targeta',
        };
    }
}
