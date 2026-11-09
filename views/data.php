<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['autenticado'])) { header('Location: index.php?menu=login'); exit; }
require_once __DIR__ . '/permisos.php';
if (in_array(strtolower(basename($_SERVER['SCRIPT_NAME'] ?? '')), ['ventas.php', 'tickets.php', 'metas.php', 'respaldos.php', 'logs.php', 'usuarios.php'], true)) exigir_dueno();


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


function materiales_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM materiales ORDER BY nombre ASC")->fetchAll();
    return array_map(function($r) {
        return [
            'id'          => $r['id_material'],
            'id_material' => $r['id_material'],
            'nombre'      => $r['nombre'],
            'categoria'   => $r['categoria'],
            'unidad'      => $r['unidad'],
            'precio_kg'   => $r['precio_kg'],
            'stock_max'   => $r['stock_max'],
            'stock_min'   => $r['stock_min'],
            'id_usuario'  => $r['id_usuario'],
        ];
    }, $rows);
}

function materiales_guardar(array $materiales): void {
    /* Usado en restauración de respaldo: reemplaza todos los registros */
    $pdo = get_pdo();
    $pdo->exec("DELETE FROM materiales");
    $stmt = $pdo->prepare(
        "INSERT INTO materiales (id_material, nombre, stock_max, stock_min, precio_kg, id_usuario, categoria, unidad)
         VALUES (:id, :nombre, :stock_max, :stock_min, :precio_kg, :uid, :categoria, :unidad)"
    );
    foreach ($materiales as $m) {
        $stmt->execute([
            ':id'        => $m['id_material'] ?? $m['id'] ?? uniqid(),
            ':nombre'    => $m['nombre'],
            ':stock_max' => $m['stock_max'] ?? 0,
            ':stock_min' => $m['stock_min'] ?? 0,
            ':precio_kg' => $m['precio_kg'] ?? 0,
            ':uid'       => $m['id_usuario'] ?? null,
            ':categoria' => $m['categoria']  ?? '',
            ':unidad'    => $m['unidad']     ?? 'kg',
        ]);
    }
}

function material_crear(string $nombre, string $categoria, string $unidad,
                        float $precio_kg, float $stock_max, float $stock_min,
                        ?int $id_usuario = null): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "INSERT INTO materiales (id_material, nombre, categoria, unidad, precio_kg, stock_max, stock_min, id_usuario)
         VALUES (:id, :nombre, :categoria, :unidad, :precio_kg, :stock_max, :stock_min, :uid)"
    )->execute([
        ':id'        => uniqid(),
        ':nombre'    => $nombre,
        ':categoria' => $categoria,
        ':unidad'    => $unidad,
        ':precio_kg' => $precio_kg,
        ':stock_max' => $stock_max,
        ':stock_min' => $stock_min,
        ':uid'       => $id_usuario ?? $_SESSION['id_usuario'] ?? null,
    ]);
}

function material_editar(string $id, string $nombre, string $categoria, string $unidad,
                         float $precio_kg, float $stock_max, float $stock_min): void {
    $pdo = get_pdo();
    $pdo->prepare(
        "UPDATE materiales
         SET nombre=:nombre, categoria=:categoria, unidad=:unidad,
             precio_kg=:precio_kg, stock_max=:stock_max, stock_min=:stock_min
         WHERE id_material=:id"
    )->execute([
        ':nombre'    => $nombre,
        ':categoria' => $categoria,
        ':unidad'    => $unidad,
        ':precio_kg' => $precio_kg,
        ':stock_max' => $stock_max,
        ':stock_min' => $stock_min,
        ':id'        => $id,
    ]);
}

function material_eliminar(string $id): void {
    get_pdo()->prepare("DELETE FROM materiales WHERE id_material=:id")->execute([':id' => $id]);
}



function compras_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM gestion_compras ORDER BY fecha DESC")->fetchAll();
    return array_map(function($r) {
        return [
            'id'              => $r['id_gestion_venta'],
            'id_gestion_venta'=> $r['id_gestion_venta'],
            'fecha'           => $r['fecha'],
            'material'        => $r['clasificacion'],
            'clasificacion'   => $r['clasificacion'],
            'proveedor'       => $r['nombre_empresa'],
            'nombre_empresa'  => $r['nombre_empresa'],
            'cantidad'        => $r['kilos'],
            'kilos'           => $r['kilos'],
            'precio_kg'       => $r['dinero_recibido'] > 0 && $r['kilos'] > 0
                                  ? round($r['dinero_recibido'] / $r['kilos'], 2)
                                  : 0,
            'dinero_recibido' => $r['dinero_recibido'],
            'total'           => $r['dinero_recibido'],
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
            ':fecha'   => $c['fecha']          ?? date('Y-m-d'),
            ':kilos'   => $c['kilos']           ?? $c['cantidad'] ?? 0,
            ':clas'    => $c['clasificacion']   ?? $c['material'] ?? '',
            ':empresa' => $c['nombre_empresa']  ?? $c['proveedor'] ?? '',
            ':dinero'  => $c['dinero_recibido'] ?? $c['total'] ?? 0,
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




/* Existencia por material = compras - ventas activas (en kg), indexada por id_material */
function existencias_leer(): array {
    $pdo = get_pdo();
    $res = []; $idx = [];
    foreach (materiales_leer() as $m) {
        $res[$m['id_material']] = ['nombre' => $m['nombre'], 'compras' => 0.0, 'ventas' => 0.0, 'existencia' => 0.0];
        $idx[mb_strtolower(trim($m['nombre']))] = $m['id_material'];
    }
    $sumar = function (string $campo, string $nombre, float $kg) use (&$res, $idx) {
        $k = mb_strtolower(trim($nombre));
        if (isset($idx[$k])) $res[$idx[$k]][$campo] += $kg;
    };
    foreach ($pdo->query("SELECT clasificacion, SUM(kilos) AS k FROM gestion_compras GROUP BY clasificacion") as $r)
        $sumar('compras', (string)$r['clasificacion'], (float)$r['k']);
    try {
        foreach ($pdo->query("SELECT d.material, SUM(d.cantidad) AS k FROM Detalle_ventas d JOIN ventas v ON v.id_ventas = d.id_ventas
                              WHERE v.estado = 'Activa' AND d.material IS NOT NULL GROUP BY d.material") as $r)
            $sumar('ventas', (string)$r['material'], (float)$r['k']);
    } catch (PDOException $e) { /* Detalle_ventas.material aún no existe */ }
    foreach ($res as $id => $r) $res[$id]['existencia'] = $r['compras'] - $r['ventas'];
    return $res;
}

function eventos_leer(): array {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM agenda ORDER BY fecha ASC, hora ASC")->fetchAll();
    return array_map(function($r) {
        return [
            'id'          => $r['id_evento'],
            'id_evento'   => $r['id_evento'],
            'titulo'      => $r['descripcion'],
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


function auditar(string $modulo, string $accion, string $descripcion = ''): void {
    try { registrar_auditoria(get_pdo(), $modulo, $accion, $descripcion); } catch (Throwable $e) { /* la auditoría nunca debe romper la operación */ }
}

function registrar_auditoria(PDO $pdo, string $modulo, string $accion, string $descripcion = ''): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_nombre VARCHAR(100) NOT NULL DEFAULT 'sistema',
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    descripcion TEXT NULL,
    ip VARCHAR(45) NULL,
    INDEX idx_fecha (fecha), INDEX idx_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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