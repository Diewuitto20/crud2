<?php
/* data.php — datos de ejemplo y helpers globales */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ── EVENTOS ── */
define('EVENTOS_FILE', __DIR__ . '/eventos.json');

function eventos_leer(): array {
    if (!file_exists(EVENTOS_FILE)) return [];
    $data = json_decode(file_get_contents(EVENTOS_FILE), true);
    return is_array($data) ? $data : [];
}

function eventos_guardar(array $eventos): void {
    file_put_contents(EVENTOS_FILE, json_encode($eventos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function evento_crear(string $titulo, string $descripcion, string $fecha, string $hora): void {
    $eventos = eventos_leer();
    $eventos[] = [
        'id'          => uniqid(),
        'titulo'      => $titulo,
        'descripcion' => $descripcion,
        'fecha'       => $fecha,
        'hora'        => $hora,
    ];
    usort($eventos, fn($a, $b) => strcmp($a['fecha'].$a['hora'], $b['fecha'].$b['hora']));
    eventos_guardar($eventos);
}

function evento_eliminar(string $id): void {
    $eventos = array_filter(eventos_leer(), fn($e) => $e['id'] !== $id);
    eventos_guardar(array_values($eventos));
}

/* ── COMPRAS ── */
define('COMPRAS_FILE', __DIR__ . '/compras.json');

function compras_leer(): array {
    if (!file_exists(COMPRAS_FILE)) return [];
    $data = json_decode(file_get_contents(COMPRAS_FILE), true);
    return is_array($data) ? $data : [];
}

function compras_guardar(array $compras): void {
    file_put_contents(COMPRAS_FILE, json_encode($compras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function compra_crear(string $material, string $proveedor, float $cantidad, float $precio_kg): void {
    $compras   = compras_leer();
    $total     = $cantidad * $precio_kg;
    $compras[] = [
        'id'        => uniqid(),
        'fecha'     => date('Y-m-d H:i:s'),
        'material'  => $material,
        'proveedor' => $proveedor,
        'cantidad'  => $cantidad,
        'precio_kg' => $precio_kg,
        'total'     => $total,
    ];
    usort($compras, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    compras_guardar($compras);
}

function compra_eliminar(string $id): void {
    $compras = array_filter(compras_leer(), fn($c) => $c['id'] !== $id);
    compras_guardar(array_values($compras));
}

/* ── MATERIALES ── */
define('MATERIALES_FILE', __DIR__ . '/materiales.json');

function materiales_leer(): array {
    if (!file_exists(MATERIALES_FILE)) return [];
    $data = json_decode(file_get_contents(MATERIALES_FILE), true);
    return is_array($data) ? $data : [];
}

function materiales_guardar(array $materiales): void {
    file_put_contents(MATERIALES_FILE, json_encode($materiales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function material_crear(string $nombre, string $categoria, string $unidad,
                        float $precio_kg, float $stock, float $stock_min): void {
    $materiales   = materiales_leer();
    $materiales[] = [
        'id'        => uniqid(),
        'nombre'    => $nombre,
        'categoria' => $categoria,
        'unidad'    => $unidad,
        'precio_kg' => $precio_kg,
        'stock'     => $stock,
        'stock_min' => $stock_min,
    ];
    usort($materiales, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
    materiales_guardar($materiales);
}

function material_editar(string $id, string $nombre, string $categoria, string $unidad,
                         float $precio_kg, float $stock, float $stock_min): void {
    $materiales = materiales_leer();
    foreach ($materiales as &$m) {
        if ($m['id'] === $id) {
            $m['nombre']    = $nombre;
            $m['categoria'] = $categoria;
            $m['unidad']    = $unidad;
            $m['precio_kg'] = $precio_kg;
            $m['stock']     = $stock;
            $m['stock_min'] = $stock_min;
            break;
        }
    }
    usort($materiales, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
    materiales_guardar($materiales);
}

function material_eliminar(string $id): void {
    $materiales = array_filter(materiales_leer(), fn($m) => $m['id'] !== $id);
    materiales_guardar(array_values($materiales));
}