<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Respons HTTP tidak boleh memuat keluaran PHP mentah. Laravel merender galatnya
// sendiri, jadi display_errors tidak pernah dibutuhkan di jalur web -- sementara
// membiarkannya hidup berarti setiap warning/deprecated PHP disisipkan ke badan
// respons beserta path absolut server, ke klien mana pun tanpa autentikasi.
//
// Ini BUKAN sekadar pengetatan: di PHP 8.5, config bawaan Laravel 11 di dalam
// vendor/ memancarkan deprecated PDO::MYSQL_ATTR_SSL_CA saat config dimuat, dan
// APP_DEBUG=false tidak menutupnya karena PHP memancarkannya sebelum Laravel
// menangani apa pun. Galatnya tetap dicatat ke log server, hanya tidak dikirim
// ke klien.
//
// Dijaga oleh tests/Feature/Deployment/ErrorOutputTest.php.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
