<?php
/* data.php — datos de ejemplo y helpers globales */

$usuarios = [
    ['id' => '01', 'nombre' => 'Rufino', 'correo' => 'Rufinito123@gmail.com', 'activo' => true],
    ['id' => '02', 'nombre' => 'Diego',  'correo' => 'DieguitoTec@gmail.com',  'activo' => true],
];

$materiales = [
    ['id' => '01', 'nombre' => 'Cartón', 'precio_compra' => 3, 'precio_venta' => 6,  'stock' => '200kg'],
    ['id' => '02', 'nombre' => 'PET',    'precio_compra' => 5, 'precio_venta' => 10, 'stock' => '150kg'],
    ['id' => '03', 'nombre' => 'HDPE',   'precio_compra' => 4, 'precio_venta' => 8,  'stock' => '80kg'],
];

$compras = [
    ['id' => '01', 'fecha' => '12-03-2026', 'cantidad' => '12 Kg', 'clasificacion' => 'PET'],
    ['id' => '02', 'fecha' => '14-03-2026', 'cantidad' => '8 Kg',  'clasificacion' => 'Cartón'],
];

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}