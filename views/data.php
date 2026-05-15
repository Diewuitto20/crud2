<?php
/* =====================================================================
   data.php  –  Helpers globales usando MySQL (PDO) en lugar de JSON
   Tablas: materiales, gestion_compras, agenda
   ===================================================================== */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* Conexión compartida (singleton) */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $env = require __DIR__ . '/../env.php';
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
            $env['DB_USER'], $env['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

/* ══════════════════════════════════════════════════════════════════════
   MATERIALES
   Tabla: materiales
   Columnas: id_material (varchar PK), nombre, stock, stock_min,
             capacidad_maxima, precio_kg, id_usuario, categoria, unidad
   ══════════════════════════════════════════════════════════════════════ */

function materiales_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM materiales ORDER BY nombre ASC")->fetchAll();
    /* Normalizar claves para compatibilidad con el resto del proyecto */
    return array_map(function($r) {
        return [
            'id'              => $r['id_material'],
            'id_material'     => $r['id_material'],
            'nombre'          => $r['nombre'],
            'categoria'       => $r['categoria'],
            'unidad'          => $r['unidad'],
            'precio_kg'       => $r['precio_kg'],
            'stock'           => $r['stock'],
            'stock_min'       => $r['stock_min'],
            'capacidad_maxima'=> $r['capacidad_maxima'],
            'id_usuario'      => $r['id_usuario'],
        ];
    }, $rows);
}

function materiales_guardar(array $materiales): void {
    /* Usado en restauración de respaldo: reemplaza todos los registros */
    $pdo = get_pdo();
    $pdo->exec("DELETE FROM materiales");
    $stmt = $pdo->prepare(
        "INSERT INTO materiales (id_material, nombre, stock, stock_min, capacidad_maxima, precio_kg, id_usuario, categoria, unidad)
         VALUES (:id, :nombre, :stock, :stock_min, :cap, :precio_kg, :uid, :categoria, :unidad)"
    );
    foreach ($materiales as $m) {
        $stmt->execute([
            ':id'       => $m['id_material'] ?? $m['id'] ?? uniqid(),
            ':nombre'   => $m['nombre'],
            ':stock'    => $m['stock']           ?? 0,
            ':stock_min'=> $m['stock_min']        ?? 0,
            ':cap'      => $m['capacidad_maxima'] ?? 0,
            ':precio_kg'=> $m['precio_kg']        ?? 0,
            ':uid'      => $m['id_usuario']       ?? null,
            ':categoria'=> $m['categoria']        ?? '',
            ':unidad'   => $m['unidad']           ?? 'kg',
        ]);
    }
}

function material_crear(string $nombre, string $categoria, string $unidad,
                        float $precio_kg, float $stock, float $stock_min,
                        float $capacidad_maxima = 0, ?int $id_usuario = null): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "INSERT INTO materiales (id_material, nombre, categoria, unidad, precio_kg, stock, stock_min, capacidad_maxima, id_usuario)
         VALUES (:id, :nombre, :categoria, :unidad, :precio_kg, :stock, :stock_min, :cap, :uid)"
    )->execute([
        ':id'       => uniqid(),
        ':nombre'   => $nombre,
        ':categoria'=> $categoria,
        ':unidad'   => $unidad,
        ':precio_kg'=> $precio_kg,
        ':stock'    => $stock,
        ':stock_min'=> $stock_min,
        ':cap'      => $capacidad_maxima,
        ':uid'      => $id_usuario ?? $_SESSION['id_usuario'] ?? null,
    ]);
}

function material_editar(string $id, string $nombre, string $categoria, string $unidad,
                         float $precio_kg, float $stock, float $stock_min,
                         float $capacidad_maxima = 0): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "UPDATE materiales
         SET nombre=:nombre, categoria=:categoria, unidad=:unidad,
             precio_kg=:precio_kg, stock=:stock, stock_min=:stock_min,
             capacidad_maxima=:cap
         WHERE id_material=:id"
    )->execute([
        ':nombre'   => $nombre,
        ':categoria'=> $categoria,
        ':unidad'   => $unidad,
        ':precio_kg'=> $precio_kg,
        ':stock'    => $stock,
        ':stock_min'=> $stock_min,
        ':cap'      => $capacidad_maxima,
        ':id'       => $id,
    ]);
}

function material_eliminar(string $id): void {
    get_pdo()->prepare("DELETE FROM materiales WHERE id_material=:id")->execute([':id' => $id]);
}

/* ══════════════════════════════════════════════════════════════════════
   COMPRAS  (gestion_compras)
   Columnas: id_gestion_venta (AI PK), fecha, kilos, clasificacion,
             nombre_empresa, dinero_recibido
   ══════════════════════════════════════════════════════════════════════ */

function compras_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM gestion_compras ORDER BY fecha DESC")->fetchAll();
    return array_map(function($r) {
        return [
            'id'             => $r['id_gestion_venta'],
            'id_gestion_venta'=> $r['id_gestion_venta'],
            'fecha'          => $r['fecha'],
            'material'       => $r['clasificacion'],   // alias para compatibilidad
            'clasificacion'  => $r['clasificacion'],
            'proveedor'      => $r['nombre_empresa'],  // alias
            'nombre_empresa' => $r['nombre_empresa'],
            'cantidad'       => $r['kilos'],
            'kilos'          => $r['kilos'],
            'precio_kg'      => $r['dinero_recibido'] > 0 && $r['kilos'] > 0
                                 ? round($r['dinero_recibido'] / $r['kilos'], 2)
                                 : 0,
            'dinero_recibido'=> $r['dinero_recibido'],
            'total'          => $r['dinero_recibido'],
        ];
    }, $rows);
}

function compras_guardar(array $compras): void {
    $pdo = get_pdo();
    $pdo->exec("DELETE FROM gestion_compras");
    $stmt = $pdo->prepare(
        "INSERT INTO gestion_compras (fecha, kilos, clasificacion, nombre_empresa, dinero_recibido)
         VALUES (:fecha, :kilos, :clas, :empresa, :dinero)"
    );
    foreach ($compras as $c) {
        $stmt->execute([
            ':fecha'   => $c['fecha']           ?? date('Y-m-d'),
            ':kilos'   => $c['kilos']            ?? $c['cantidad'] ?? 0,
            ':clas'    => $c['clasificacion']    ?? $c['material'] ?? '',
            ':empresa' => $c['nombre_empresa']   ?? $c['proveedor'] ?? '',
            ':dinero'  => $c['dinero_recibido']  ?? $c['total'] ?? 0,
        ]);
    }
}

function compra_crear(string $clasificacion, string $nombre_empresa,
                      float $kilos, float $dinero_recibido,
                      string $fecha = ''): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "INSERT INTO gestion_compras (fecha, kilos, clasificacion, nombre_empresa, dinero_recibido)
         VALUES (:fecha, :kilos, :clas, :empresa, :dinero)"
    )->execute([
        ':fecha'   => $fecha ?: date('Y-m-d'),
        ':kilos'   => $kilos,
        ':clas'    => $clasificacion,
        ':empresa' => $nombre_empresa,
        ':dinero'  => $dinero_recibido,
    ]);
}

function compra_eliminar(int $id): void {
    get_pdo()->prepare("DELETE FROM gestion_compras WHERE id_gestion_venta=:id")->execute([':id' => $id]);
}

/* ══════════════════════════════════════════════════════════════════════
   EVENTOS  (agenda)
   Columnas: id_evento (AI PK), fecha, descripcion, hora, id_usuario
   ══════════════════════════════════════════════════════════════════════ */

function eventos_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM agenda ORDER BY fecha ASC, hora ASC")->fetchAll();
    return array_map(function($r) {
        return [
            'id'          => $r['id_evento'],
            'id_evento'   => $r['id_evento'],
            'titulo'      => $r['descripcion'],   // alias para compatibilidad
            'descripcion' => $r['descripcion'],
            'fecha'       => $r['fecha'],
            'hora'        => $r['hora'],
            'id_usuario'  => $r['id_usuario'],
        ];
    }, $rows);
}

function eventos_guardar(array $eventos): void {
    $pdo = get_pdo();
    $pdo->exec("DELETE FROM agenda");
    $stmt = $pdo->prepare(
        "INSERT INTO agenda (fecha, descripcion, hora, id_usuario)
         VALUES (:fecha, :desc, :hora, :uid)"
    );
    foreach ($eventos as $e) {
        $stmt->execute([
            ':fecha' => $e['fecha'],
            ':desc'  => $e['descripcion'] ?? $e['titulo'] ?? '',
            ':hora'  => $e['hora']        ?? '00:00:00',
            ':uid'   => $e['id_usuario']  ?? $_SESSION['id_usuario'] ?? null,
        ]);
    }
}

function evento_crear(string $titulo, string $descripcion, string $fecha, string $hora): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "INSERT INTO agenda (fecha, descripcion, hora, id_usuario)
         VALUES (:fecha, :desc, :hora, :uid)"
    )->execute([
        ':fecha' => $fecha,
        ':desc'  => $descripcion ?: $titulo,
        ':hora'  => $hora,
        ':uid'   => $_SESSION['id_usuario'] ?? null,
    ]);
}

function evento_eliminar(int $id): void {
    get_pdo()->prepare("DELETE FROM agenda WHERE id_evento=:id")->execute([':id' => $id]);
}

/* ══════════════════════════════════════════════════════════════════════
   AUDITORÍA
   ══════════════════════════════════════════════════════════════════════ */

function registrar_auditoria(PDO $pdo, string $modulo, string $accion, string $descripcion = ''): void {
    $pdo->prepare(
        "INSERT INTO auditoria (usuario_nombre, modulo, accion, descripcion, ip)
         VALUES (:usuario, :modulo, :accion, :desc, :ip)"
    )->execute([
        ':usuario' => $_SESSION['nombre'] ?? 'sistema',
        ':modulo'  => $modulo,
        ':accion'  => $accion,
        ':desc'    => $descripcion,
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '—',
    ]);
}