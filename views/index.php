<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
session_start();

$menu = $_GET['menu'] ?? 'login';
$opc  = $_GET['opc']  ?? 'tabla';

/* ── Crear usuario ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear' && $menu === 'usuarios') {
    $env = require __DIR__ . '/../env.php';
    try {
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
            $env['DB_USER'], $env['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, correo, contrasena)
             VALUES (:nombre, :ap_pat, :ap_mat, :correo, :contrasena)"
        );
        $stmt->execute([
            ':nombre'     => trim($_POST['nombre']     ?? ''),
            ':ap_pat'     => trim($_POST['ap_paterno'] ?? ''),
            ':ap_mat'     => trim($_POST['ap_materno'] ?? ''),
            ':correo'     => trim($_POST['correo']     ?? ''),
            ':contrasena' => trim($_POST['password']   ?? ''),
        ]);
    } catch (PDOException $e) {
        // manejo de error
    }
    header('Location: index.php?menu=usuarios&opc=tabla');
    exit;
}

/* ── Función helper usada en las vistas ── */


/* ── Rutas ── */
if ($menu === 'login') {
    include 'login.php';

// ── DASHBOARD (ruta nueva) ──
} elseif ($menu === 'dashboard') {
    include 'dashboard.php';

} elseif ($menu === 'usuarios') {
    if ($opc === 'tabla') include 'usuarios.php';

} elseif ($menu === 'material') {
    if ($opc === 'tabla') include 'materiales.php';

} elseif ($menu === 'calendario') {
    include 'calendario.php';

} elseif ($menu === 'compras') {
    if ($opc === 'tabla') include 'compras.php';

} elseif ($menu === 'gestion_compras') {
    if ($opc === 'tabla') include 'Gestioncompras.php';

} elseif ($menu === 'respaldos') {
    if ($opc === 'tabla') include 'respaldos.php';

} elseif ($menu === 'ventas') {
    include 'ventas.php';

} elseif ($menu === 'tickets') {
    if ($opc === 'tabla') include 'tickets.php';

} elseif ($menu === 'salidas') {
    if ($opc === 'tabla') include 'salidas.php';

} elseif ($menu === 'metas') {
    if ($opc === 'tabla') include 'metas.php';

} elseif ($menu === 'auditoria') {
    if ($opc === 'tabla') include 'auditoria.php';

} else {
    header('Location: index.php?menu=login');
    exit;
}