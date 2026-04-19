<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
session_start();

$menu = $_GET['menu'] ?? 'login';
$opc  = $_GET['opc']  ?? 'tabla';

if ($menu === 'login') {
    include 'login.php';

} elseif ($menu === 'usuarios') {
    if ($opc === 'tabla') include 'usuarios.php';

} elseif ($menu === 'material') {
    if ($opc === 'tabla') include 'materiales.php';

} elseif ($menu === 'calendario') {
    include 'calendario.php';

} elseif ($menu === 'compras') {
    if ($opc === 'tabla') include 'compras.php';

} else {
    header('Location: index.php?menu=login');
    exit;
}