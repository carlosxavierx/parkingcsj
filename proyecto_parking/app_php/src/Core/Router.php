<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador mínim: associa "MÈTODE /ruta/{id}" a [Controlador, mètode].
 * Totes les peticions POST es validen amb el token CSRF abans d'arribar al controlador.
 */
final class Router
{
    private array $rutes = [];

    public function get(string $patro, array $accio): void
    {
        $this->afegir('GET', $patro, $accio);
    }

    public function post(string $patro, array $accio): void
    {
        $this->afegir('POST', $patro, $accio);
    }

    private function afegir(string $metode, string $patro, array $accio): void
    {
        // {id} -> (\d+)   ·   {codi} -> ([A-Za-z0-9-]+)
        $regex = preg_replace(['/\{id\}/', '/\{codi\}/'], ['(\d+)', '([A-Za-z0-9-]+)'], $patro);
        $this->rutes[$metode]['#^' . $regex . '$#'] = $accio;
    }

    public function dispatch(string $metode, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->rutes[$metode] ?? [] as $regex => [$classe, $funcio]) {
            if (preg_match($regex, $path, $m)) {
                if ($metode === 'POST') {
                    Csrf::verify();
                }
                array_shift($m);
                $params = array_map(static fn($v) => ctype_digit($v) ? (int) $v : $v, $m);
                (new $classe())->$funcio(...$params);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', ['titol' => 'Pàgina no trobada']);
    }
}
