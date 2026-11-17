<?php
/**
 * Arrencada de l'aplicació: constants, zona horària, autoload de classes,
 * configuració i gestió d'errors.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Europe/Madrid');
mb_internal_encoding('UTF-8');

// Autoload PSR-4 senzill: App\Core\Router -> src/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuració no trobada (config/config.php).');
}
App\Core\Config::load(require $configFile);

// Qualsevol error no controlat: es registra al log d'Apache i es mostra una
// pàgina genèrica (mai detalls tècnics a l'usuari).
set_exception_handler(static function (Throwable $e): void {
    error_log('[csjparkings] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    App\Core\View::render('errors/500', ['titol' => 'Error del servidor']);
});
