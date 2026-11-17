<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuració carregada de config/config.php (fora de la carpeta pública).
 * Accés amb notació de punts: Config::get('db.host').
 */
final class Config
{
    private static array $dades = [];

    public static function load(array $config): void
    {
        self::$dades = $config;
    }

    public static function get(string $clau, mixed $perDefecte = null): mixed
    {
        $valor = self::$dades;
        foreach (explode('.', $clau) as $part) {
            if (!is_array($valor) || !array_key_exists($part, $valor)) {
                return $perDefecte;
            }
            $valor = $valor[$part];
        }
        return $valor;
    }
}
