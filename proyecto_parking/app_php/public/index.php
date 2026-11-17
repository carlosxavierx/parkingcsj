<?php
/**
 * CSJ Parkings · controlador frontal (front controller)
 * Totes les peticions entren per aquí: s'inicia la sessió, es carreguen les
 * rutes i el Router crida el mètode del controlador corresponent.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Router;
use App\Core\Session;

Session::start();

$router = new Router();
require APP_ROOT . '/src/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
