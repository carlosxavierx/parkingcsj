<?php
/**
 * Taula de rutes: URL -> [Controlador, mètode]. Totes les rutes POST
 * passen per la comprovació del token CSRF (Router::dispatch).
 */
declare(strict_types=1);

use App\Controllers\AdminController as Admin;
use App\Controllers\AuthController as AuthC;
use App\Controllers\HomeController as Home;
use App\Controllers\OperadorController as Operador;
use App\Controllers\PagamentController as Pagament;
use App\Controllers\PerfilController as Perfil;
use App\Controllers\ReservaController as Reserva;
use App\Controllers\VehicleController as Vehicle;

/** @var App\Core\Router $router */

// Públic
$router->get('/', [Home::class, 'index']);
$router->get('/condicions', [Home::class, 'condicions']);
$router->get('/api/disponibilitat', [Home::class, 'disponibilitat']);

// Autenticació
$router->get('/login', [AuthC::class, 'loginForm']);
$router->post('/login', [AuthC::class, 'login']);
$router->get('/registre', [AuthC::class, 'registreForm']);
$router->post('/registre', [AuthC::class, 'registre']);
$router->post('/logout', [AuthC::class, 'logout']);

// Perfil, vehicles i mètodes de pagament (qualsevol usuari registrat)
$router->get('/perfil', [Perfil::class, 'index']);
$router->post('/perfil', [Perfil::class, 'actualitzar']);
$router->post('/perfil/contrasenya', [Perfil::class, 'contrasenya']);
$router->get('/vehicles', [Vehicle::class, 'index']);
$router->post('/vehicles', [Vehicle::class, 'crear']);
$router->post('/vehicles/{id}', [Vehicle::class, 'actualitzar']);
$router->post('/vehicles/{id}/baixa', [Vehicle::class, 'baixa']);
$router->get('/pagaments', [Pagament::class, 'index']);
$router->post('/pagaments', [Pagament::class, 'crear']);
$router->post('/pagaments/{id}/defecte', [Pagament::class, 'perDefecte']);
$router->post('/pagaments/{id}/baixa', [Pagament::class, 'baixa']);

// Reserves del client
$router->get('/reserves', [Reserva::class, 'index']);
$router->get('/reserves/nova', [Reserva::class, 'nova']);
$router->post('/reserves', [Reserva::class, 'crear']);
$router->get('/reserves/{id}', [Reserva::class, 'veure']);
$router->get('/reserves/{id}/pdf', [Reserva::class, 'pdf']);
$router->post('/reserves/{id}/cancellar', [Reserva::class, 'cancellar']);

// Operador (i administrador): ocupació, reserves del dia i lector de matrícules
$router->get('/operador', [Operador::class, 'index']);
$router->post('/operador/lector', [Operador::class, 'lector']);
$router->post('/operador/reserves/{id}/estat', [Operador::class, 'estat']);

// Administració
$router->get('/admin', [Admin::class, 'index']);
$router->get('/admin/reserves', [Admin::class, 'reserves']);
$router->get('/admin/reserves/{id}', [Admin::class, 'reserva']);
$router->post('/admin/reserves/{id}/estat', [Admin::class, 'reservaEstat']);
$router->post('/admin/reserves/{id}/modificar', [Admin::class, 'reservaModificar']);
$router->get('/admin/parquings', [Admin::class, 'parquings']);
$router->get('/admin/parquings/nou', [Admin::class, 'parquingNou']);
$router->post('/admin/parquings', [Admin::class, 'parquingCrear']);
$router->get('/admin/parquings/{id}', [Admin::class, 'parquing']);
$router->post('/admin/parquings/{id}', [Admin::class, 'parquingActualitzar']);
$router->post('/admin/parquings/{id}/places', [Admin::class, 'placaAfegir']);
$router->post('/admin/places/{id}', [Admin::class, 'placaActualitzar']);
$router->get('/admin/tarifes', [Admin::class, 'tarifes']);
$router->post('/admin/tarifes', [Admin::class, 'tarifaCrear']);
$router->post('/admin/tarifes/{id}', [Admin::class, 'tarifaActualitzar']);
$router->get('/admin/usuaris', [Admin::class, 'usuaris']);
$router->post('/admin/usuaris/{id}', [Admin::class, 'usuariActualitzar']);
$router->post('/admin/usuaris/{id}/desbloquejar', [Admin::class, 'usuariDesbloquejar']);
