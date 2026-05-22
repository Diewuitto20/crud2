<?php
/* =====================================================================
   views/index.php  –  Router principal (MVC)
   ===================================================================== */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1); // temporal para ver errores
session_start();

$menu = $_GET['menu'] ?? 'login';
$opc  = $_GET['opc']  ?? 'tabla';

/* Raíz del proyecto (un nivel arriba de views/) */
$root = dirname(__DIR__);

/* Config compartida */
require_once $root . '/config/database.php';

/* Autoload de controllers y models */
spl_autoload_register(function(string $clase) use ($root) {
    $rutas = [
        $root . '/controllers/' . $clase . '.php',
        $root . '/models/'      . $clase . '.php',
    ];
    foreach ($rutas as $ruta) {
        if (file_exists($ruta)) { require_once $ruta; return; }
    }
});

/* ── Router ── */
switch ($menu) {

    case 'login':
        include __DIR__ . '/login.php';
        break;

    case 'dashboard':
        DashboardController::manejar();
        break;

    case 'usuarios':
        UsuarioController::manejar();
        break;

    case 'material':
        MaterialController::manejar();
        break;

    case 'calendario':
        CalendarioController::manejar();
        break;

    case 'gestion_compras':
        CompraController::manejar();
        break;

    case 'respaldos':
        RespaldoController::manejar();
        break;

    case 'logs':
        LogsController::manejar();
        break;

    case 'compras':
        include __DIR__ . '/compras.php';
        break;

    case 'ventas':
        include __DIR__ . '/ventas.php';
        break;

    case 'tickets':
        include __DIR__ . '/Tickets.php';
        break;

    case 'salidas':
        include __DIR__ . '/salidas.php';
        break;

    case 'metas':
        include __DIR__ . '/metas.php';
        break;

    default:
        header('Location: index.php?menu=login');
        exit;
}