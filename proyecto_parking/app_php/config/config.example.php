<?php
/**
 * CSJ Parkings · EXEMPLE de configuració (sense contrasenyes reals).
 * Al servidor el fitxer real és /srv/www/csjparkings/config/config.php
 * (fora de la carpeta pública, propietari root:www-data, permisos 640).
 *
 * app_key: 32 bytes aleatoris en base64 per xifrar telèfon i DNI (AES-256).
 *   Es genera amb:  openssl rand -base64 32
 *   L'script infra/07-desplegar-app.sh l'afegeix automàticament si no hi és.
 *   ATENCIÓ: si es perd o es canvia, les dades xifrades ja no es poden llegir.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'csj_parkings',
        'user'    => 'csj_app',
        'pass'    => 'CANVIA_AQUESTA_CONTRASENYA',
        'charset' => 'utf8mb4',
    ],
    'app_key' => 'GENERA_AMB_openssl_rand_-base64_32',
];
