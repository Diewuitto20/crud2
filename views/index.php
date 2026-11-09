<?php

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
date_default_timezone_set('America/Mexico_City');

$menu = $_GET['menu'] ?? 'login';
$opc  = $_GET['opc']  ?? 'tabla';

/* Cerrar sesión */
if ($menu === 'logout') {
    if (!empty($_SESSION['autenticado'])) { try { require_once dirname(__DIR__) . '/models/LogsModel.php'; LogsModel::registrar('sesion', 'logout', 'Cierre de sesión'); } catch (Throwable $e) {} }
    session_unset();
    session_destroy();
    header('Location: index.php?menu=login');
    exit;
}

/* Bloquear acceso sin sesión iniciada */
if ($menu !== 'login' && empty($_SESSION['autenticado'])) {
    header('Location: index.php?menu=login');
    exit;
}

/* Raíz del proyecto (un nivel arriba de views/) */
$root = dirname(__DIR__);

/* Config compartida */
require_once $root . '/config/database.php';
require_once __DIR__ . '/permisos.php';
if ($menu !== 'login' && $menu !== 'logout') validar_sesion();

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
        exigir_dueno();
        UsuarioController::manejar();
        break;

    case 'material':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') exigir_dueno();
        MaterialController::manejar();
        break;

    case 'calendario':
        CalendarioController::manejar();
        break;

    case 'gestion_compras':
        header('Location: index.php?menu=compras&opc=tabla');
        exit;

    case 'respaldos':
        exigir_dueno();
        RespaldoController::manejar();
        break;

    case 'logs':
        exigir_dueno();
        LogsController::manejar();
        break;

    case 'compras':
        include __DIR__ . '/compras.php';
        break;

    case 'ventas':
        exigir_dueno();
        include __DIR__ . '/ventas.php';
        break;

    case 'tickets':
        exigir_dueno();
        include __DIR__ . '/Tickets.php';
        break;

    case 'metas':
        exigir_dueno();
        include __DIR__ . '/metas.php';
        break;

    default:
        header('Location: index.php?menu=login');
        exit;
}