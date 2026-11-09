<?php
require_once __DIR__ . '/data.php';
exigir_dueno();

$pdo = get_pdo();
$pagina_activa = 'verificar_bd';
$titulo_pagina = 'Verificar BD';

/* ── Esquema esperado: columna => clase de tipo (int, dec, str, date, txt) ── */
$esperado = [
    'usuarios'        => ['id_usuario'=>'int','nombre'=>'str','apellido_paterno'=>'str','apellido_materno'=>'str','correo'=>'str','contrasena'=>'str','activo'=>'int','rol'=>'str'],
    'materiales'      => ['id_material'=>'str','nombre'=>'str','categoria'=>'str','unidad'=>'str','precio_kg'=>'dec','stock_max'=>'dec','stock_min'=>'dec','id_usuario'=>'int'],
    'gestion_compras' => ['id_gestion_venta'=>'int','fecha'=>'date','kilos'=>'dec','clasificacion'=>'str','nombre_empresa'=>'str','dinero_recibido'=>'dec'],
    'ventas'          => ['id_ventas'=>'str','fecha'=>'date','total'=>'dec','id_usuario'=>'int','clasificacion'=>'str','estado'=>'str'],
    'Detalle_ventas'  => ['id_ventas'=>'str','material'=>'str','precio'=>'dec','cantidad'=>'dec'],
    'comprobantes'    => ['id'=>'int','folio'=>'str','tipo_origen'=>'str','id_origen'=>'str','fecha_emision'=>'date','cliente_nombre'=>'str','cliente_rfc'=>'str','cliente_direccion'=>'str','cliente_contacto'=>'str','forma_pago'=>'str','iva_modo'=>'str','notas'=>'txt','detalle'=>'txt','subtotal'=>'dec','iva'=>'dec','total'=>'dec','estado'=>'str','id_usuario'=>'int'],
    'agenda'          => ['id_evento'=>'int','fecha'=>'date','descripcion'=>'str','hora'=>'str','id_usuario'=>'int'],
    'respaldos'       => ['id'=>'int','tipo_operacion'=>'str','nombre_archivo'=>'str','formato'=>'str','nombre_bd'=>'str','tamanio_bytes'=>'int','usuario_id'=>'int','observaciones'=>'txt','fechayhora'=>'date'],
    'auditoria'       => ['id'=>'int','fecha'=>'date','usuario_nombre'=>'str','modulo'=>'str','accion'=>'str','descripcion'=>'txt','ip'=>'str'],
];
$autoinc = ['usuarios'=>'id_usuario','gestion_compras'=>'id_gestion_venta','comprobantes'=>'id','agenda'=>'id_evento','respaldos'=>'id','auditoria'=>'id'];
$pk      = ['usuarios'=>'id_usuario','materiales'=>'id_material','gestion_compras'=>'id_gestion_venta','ventas'=>'id_ventas','comprobantes'=>'id','agenda'=>'id_evento','respaldos'=>'id','auditoria'=>'id'];
$unicos  = ['usuarios'=>'correo','comprobantes'=>'folio'];
$largos  = ['usuarios.contrasena'=>60, 'materiales.id_material'=>13, 'ventas.id_ventas'=>7, 'materiales.nombre'=>40, 'gestion_compras.clasificacion'=>40];
$claseDe = [
    'int'  => ['tinyint','smallint','mediumint','int','bigint'],
    'dec'  => ['decimal','float','double'],
    'str'  => ['varchar','char','text','tinytext','mediumtext','longtext','enum','time'],
    'date' => ['date','datetime','timestamp'],
    'txt'  => ['text','tinytext','mediumtext','longtext','json','varchar'],
];

$estructura = [];
$add = function (string $nivel, string $tabla, string $msg) use (&$estructura) { $estructura[] = [$nivel, $tabla, $msg]; };

try {
    $cols = [];
    foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE d, CHARACTER_MAXIMUM_LENGTH l, COLUMN_KEY k, EXTRA x
                          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()")->fetchAll() as $r)
        $cols[strtolower($r['t'])][strtolower($r['c'])] = $r;

    $tablas = [];
    foreach ($pdo->query("SELECT TABLE_NAME t, ENGINE e, TABLE_COLLATION c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll() as $r)
        $tablas[strtolower($r['t'])] = $r;

    $uniq = [];
    foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0")->fetchAll() as $r)
        $uniq[strtolower($r['t'] . '.' . $r['c'])] = true;

    $fks = $pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();

    foreach ($esperado as $tabla => $campos) {
        $lt = strtolower($tabla);
        if (!isset($tablas[$lt])) { $add('error', $tabla, 'La tabla no existe.'); continue; }
        $ok = true;
        if (strcasecmp((string)$tablas[$lt]['e'], 'InnoDB') !== 0) { $add('warn', $tabla, 'Motor ' . $tablas[$lt]['e'] . ': no admite transacciones ni llaves foráneas (se recomienda InnoDB).'); $ok = false; }
        foreach ($campos as $campo => $clase) {
            $c = $cols[$lt][strtolower($campo)] ?? null;
            if (!$c) { $add('error', "$tabla.$campo", 'Falta la columna.'); $ok = false; continue; }
            $dt = strtolower($c['d']);
            if (!in_array($dt, $claseDe[$clase], true)) {
                $esInt = in_array($dt, $claseDe['int'], true);
                if ($clase === 'dec' && $esInt) $add('warn', "$tabla.$campo", "Es $dt: pierde decimales (debería ser DECIMAL).");
                else $add('warn', "$tabla.$campo", "Tipo $dt; se esperaba " . implode('/', array_slice($claseDe[$clase], 0, 3)) . '.');
                $ok = false;
            }
            $min = $largos["$tabla.$campo"] ?? null;
            if ($min && $c['l'] !== null && (int)$c['l'] < $min) { $add('warn', "$tabla.$campo", "Longitud {$c['l']}; se recomienda al menos $min."); $ok = false; }
        }
        if (isset($pk[$tabla]) && (($cols[$lt][$pk[$tabla]]['k'] ?? '') !== 'PRI')) { $add('error', $tabla, "La columna {$pk[$tabla]} no es llave primaria."); $ok = false; }
        if ($tabla === 'Detalle_ventas') {
            $tienePk = false; foreach ($cols[$lt] as $c) if ($c['k'] === 'PRI') $tienePk = true;
            if (!$tienePk) { $add('warn', $tabla, 'Sin llave primaria.'); $ok = false; }
        }
        if (isset($autoinc[$tabla]) && stripos((string)($cols[$lt][$autoinc[$tabla]]['x'] ?? ''), 'auto_increment') === false) { $add('error', "$tabla.{$autoinc[$tabla]}", 'No es AUTO_INCREMENT: los inserts fallarían.'); $ok = false; }
        if (isset($unicos[$tabla]) && empty($uniq[strtolower($tabla . '.' . $unicos[$tabla])])) { $add('warn', "$tabla.{$unicos[$tabla]}", 'Sin índice UNIQUE: se pueden duplicar valores.'); $ok = false; }
        if ($ok) $add('ok', $tabla, 'Tabla y columnas correctas.');
    }
    $cola = array_unique(array_map(fn($t) => $t['c'], $tablas));
    if (count($cola) > 1) $add('warn', 'Collation', 'Las tablas usan collations distintas (' . implode(', ', $cola) . '): los JOIN por texto pueden fallar o ir lentos.');
    $mapaTabla = array_flip(array_map('strtolower', array_keys($esperado)));
    foreach ($tablas as $t => $_) if (!isset($mapaTabla[$t])) $add('info', $t, 'Tabla no usada por la aplicación.');
} catch (Throwable $e) { $add('error', 'Esquema', 'No se pudo leer information_schema: ' . $e->getMessage()); $fks = []; }

/* ── Integridad de datos: [título, nivel, SQL de conteo, SQL de ejemplos, nota] ── */
$checks = [
 ['Compras con material fuera del catálogo', 'warn', "SELECT COUNT(*) FROM gestion_compras g WHERE NOT EXISTS (SELECT 1 FROM materiales m WHERE LOWER(TRIM(m.nombre))=LOWER(TRIM(g.clasificacion)))",
   "SELECT DISTINCT clasificacion FROM gestion_compras g WHERE NOT EXISTS (SELECT 1 FROM materiales m WHERE LOWER(TRIM(m.nombre))=LOWER(TRIM(g.clasificacion))) LIMIT 5", 'No suman a la existencia.'],
 ['Detalle de venta con material fuera del catálogo', 'warn', "SELECT COUNT(*) FROM Detalle_ventas d WHERE d.material IS NOT NULL AND NOT EXISTS (SELECT 1 FROM materiales m WHERE LOWER(TRIM(m.nombre))=LOWER(TRIM(d.material)))",
   "SELECT DISTINCT material FROM Detalle_ventas d WHERE d.material IS NOT NULL AND NOT EXISTS (SELECT 1 FROM materiales m WHERE LOWER(TRIM(m.nombre))=LOWER(TRIM(d.material))) LIMIT 5", 'No restan de la existencia.'],
 ['Detalle de venta sin venta', 'error', "SELECT COUNT(*) FROM Detalle_ventas d LEFT JOIN ventas v ON v.id_ventas=d.id_ventas WHERE v.id_ventas IS NULL", "SELECT DISTINCT d.id_ventas FROM Detalle_ventas d LEFT JOIN ventas v ON v.id_ventas=d.id_ventas WHERE v.id_ventas IS NULL LIMIT 5", 'Registros huérfanos.'],
 ['Ventas sin detalle', 'warn', "SELECT COUNT(*) FROM ventas v WHERE NOT EXISTS (SELECT 1 FROM Detalle_ventas d WHERE d.id_ventas=v.id_ventas)", "SELECT id_ventas FROM ventas v WHERE NOT EXISTS (SELECT 1 FROM Detalle_ventas d WHERE d.id_ventas=v.id_ventas) LIMIT 5", 'No descuentan existencia.'],
 ['Total de venta distinto de la suma de su detalle', 'error', "SELECT COUNT(*) FROM ventas v JOIN (SELECT id_ventas, SUM(precio*cantidad) s FROM Detalle_ventas GROUP BY id_ventas) d ON d.id_ventas=v.id_ventas WHERE ABS(v.total-d.s)>0.01",
   "SELECT v.id_ventas, v.total, d.s FROM ventas v JOIN (SELECT id_ventas, SUM(precio*cantidad) s FROM Detalle_ventas GROUP BY id_ventas) d ON d.id_ventas=v.id_ventas WHERE ABS(v.total-d.s)>0.01 LIMIT 5", 'Afecta ganancias y comprobantes.'],
 ['Ventas con usuario inexistente', 'error', "SELECT COUNT(*) FROM ventas v LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario WHERE u.id_usuario IS NULL", "SELECT v.id_ventas, v.id_usuario FROM ventas v LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario WHERE u.id_usuario IS NULL LIMIT 5", ''],
 ['Ventas con estado inválido', 'error', "SELECT COUNT(*) FROM ventas WHERE estado IS NULL OR estado NOT IN ('Activa','Cancelada')", "SELECT id_ventas, estado FROM ventas WHERE estado IS NULL OR estado NOT IN ('Activa','Cancelada') LIMIT 5", ''],
 ['Ventas con total menor o igual a cero', 'warn', "SELECT COUNT(*) FROM ventas WHERE total <= 0", "SELECT id_ventas, total FROM ventas WHERE total <= 0 LIMIT 5", ''],
 ['Compras con kilos o monto menor o igual a cero', 'error', "SELECT COUNT(*) FROM gestion_compras WHERE kilos <= 0 OR dinero_recibido <= 0", "SELECT id_gestion_venta, clasificacion, kilos, dinero_recibido FROM gestion_compras WHERE kilos <= 0 OR dinero_recibido <= 0 LIMIT 5", ''],
 ['Materiales con precio o límites inválidos', 'error', "SELECT COUNT(*) FROM materiales WHERE precio_kg <= 0 OR stock_max <= 0 OR stock_min < 0 OR stock_min > stock_max", "SELECT nombre, precio_kg, stock_min, stock_max FROM materiales WHERE precio_kg <= 0 OR stock_max <= 0 OR stock_min < 0 OR stock_min > stock_max LIMIT 5", ''],
 ['Materiales con nombre duplicado', 'error', "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(nombre)) n FROM materiales GROUP BY n HAVING COUNT(*)>1) t", "SELECT LOWER(TRIM(nombre)) FROM materiales GROUP BY LOWER(TRIM(nombre)) HAVING COUNT(*)>1 LIMIT 5", 'La existencia se calcula por nombre.'],
 ['Materiales con usuario inexistente', 'warn', "SELECT COUNT(*) FROM materiales m LEFT JOIN usuarios u ON u.id_usuario=m.id_usuario WHERE m.id_usuario IS NOT NULL AND u.id_usuario IS NULL", "SELECT m.nombre, m.id_usuario FROM materiales m LEFT JOIN usuarios u ON u.id_usuario=m.id_usuario WHERE m.id_usuario IS NOT NULL AND u.id_usuario IS NULL LIMIT 5", ''],
 ['Correos de usuario duplicados', 'error', "SELECT COUNT(*) FROM (SELECT LOWER(correo) c FROM usuarios GROUP BY c HAVING COUNT(*)>1) t", "SELECT LOWER(correo) FROM usuarios GROUP BY LOWER(correo) HAVING COUNT(*)>1 LIMIT 5", ''],
 ['Usuarios con "activo" distinto de 0 o 1', 'warn', "SELECT COUNT(*) FROM usuarios WHERE activo IS NULL OR activo NOT IN (0,1)", "SELECT id_usuario, activo FROM usuarios WHERE activo IS NULL OR activo NOT IN (0,1) LIMIT 5", ''],
 ['Usuarios con rol inválido', 'error', "SELECT COUNT(*) FROM usuarios WHERE rol NOT IN ('dueno','empleado')", "SELECT id_usuario, rol FROM usuarios WHERE rol NOT IN ('dueno','empleado') LIMIT 5", ''],
 ['Contraseñas aún en texto plano', 'info', "SELECT COUNT(*) FROM usuarios WHERE contrasena NOT LIKE '\$2y\$%'", "SELECT correo FROM usuarios WHERE contrasena NOT LIKE '\$2y\$%' LIMIT 5", 'Se convierten a hash cuando cada usuario inicia sesión.'],
 ['Comprobantes con folio duplicado', 'error', "SELECT COUNT(*) FROM (SELECT folio FROM comprobantes GROUP BY folio HAVING COUNT(*)>1) t", "SELECT folio FROM comprobantes GROUP BY folio HAVING COUNT(*)>1 LIMIT 5", ''],
 ['Comprobantes de ventas inexistentes', 'error', "SELECT COUNT(*) FROM comprobantes c WHERE c.tipo_origen='Venta' AND NOT EXISTS (SELECT 1 FROM ventas v WHERE v.id_ventas=c.id_origen)", "SELECT folio, id_origen FROM comprobantes c WHERE c.tipo_origen='Venta' AND NOT EXISTS (SELECT 1 FROM ventas v WHERE v.id_ventas=c.id_origen) LIMIT 5", ''],
 ['Comprobantes emitidos de ventas canceladas', 'error', "SELECT COUNT(*) FROM comprobantes c JOIN ventas v ON v.id_ventas=c.id_origen WHERE c.tipo_origen='Venta' AND c.estado='Emitida' AND v.estado='Cancelada'", "SELECT c.folio, v.id_ventas FROM comprobantes c JOIN ventas v ON v.id_ventas=c.id_origen WHERE c.tipo_origen='Venta' AND c.estado='Emitida' AND v.estado='Cancelada' LIMIT 5", ''],
 ['Eventos de agenda con usuario inexistente', 'warn', "SELECT COUNT(*) FROM agenda a LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.id_usuario IS NOT NULL AND u.id_usuario IS NULL", "SELECT a.id_evento, a.id_usuario FROM agenda a LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.id_usuario IS NOT NULL AND u.id_usuario IS NULL LIMIT 5", ''],
 ['Respaldos con usuario inexistente', 'warn', "SELECT COUNT(*) FROM respaldos r LEFT JOIN usuarios u ON u.id_usuario=r.usuario_id WHERE r.usuario_id IS NOT NULL AND u.id_usuario IS NULL", "SELECT r.id, r.usuario_id FROM respaldos r LEFT JOIN usuarios u ON u.id_usuario=r.usuario_id WHERE r.usuario_id IS NOT NULL AND u.id_usuario IS NULL LIMIT 5", ''],
 ['Compras o ventas con fecha futura', 'warn', "SELECT (SELECT COUNT(*) FROM gestion_compras WHERE fecha > NOW() + INTERVAL 1 DAY) + (SELECT COUNT(*) FROM ventas WHERE fecha > NOW() + INTERVAL 1 DAY)", null, 'Distorsionan los reportes del periodo.'],
];

$datos = [];
foreach ($checks as [$titulo, $nivel, $sqlCuenta, $sqlMuestra, $nota]) {
    try {
        $n = (int)$pdo->query($sqlCuenta)->fetchColumn();
        $muestra = '';
        if ($n > 0 && $sqlMuestra) {
            $muestra = implode(' · ', array_map(fn($r) => implode(' | ', array_map('strval', $r)), $pdo->query($sqlMuestra)->fetchAll(PDO::FETCH_NUM)));
        }
        $datos[] = [$n === 0 ? 'ok' : $nivel, $titulo, $n, $muestra, $nota];
    } catch (Throwable $e) {
        $datos[] = ['warn', $titulo, '—', 'No se pudo ejecutar: ' . $e->getMessage(), ''];
    }
}
try {
    $d = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='dueno'")->fetchColumn();
    $datos[] = [$d === 1 ? 'ok' : ($d === 0 ? 'error' : 'warn'), 'Cantidad de dueños', $d, '', 'Debe haber exactamente uno.'];
} catch (Throwable $e) {}
try {
    $neg = [];
    foreach (existencias_leer() as $x) if ($x['existencia'] < -0.001) $neg[] = $x['nombre'] . ' (' . number_format($x['existencia'], 2) . ' kg)';
    $datos[] = [$neg ? 'error' : 'ok', 'Existencia negativa por material', count($neg), implode(' · ', $neg), 'Se vendió más de lo comprado.'];
} catch (Throwable $e) {}

$conteo = ['error' => 0, 'warn' => 0, 'ok' => 0, 'info' => 0];
foreach ($estructura as $f) $conteo[$f[0]]++;
foreach ($datos as $f) $conteo[$f[0]]++;

function pastilla(string $nivel): string {
    $m = ['ok' => ['#dcfce7', '#15803d', 'OK'], 'warn' => ['#fef3c7', '#b45309', 'Advertencia'], 'error' => ['#fee2e2', '#b91c1c', 'Error'], 'info' => ['#e0f2fe', '#0369a1', 'Info']][$nivel];
    return "<span style=\"background:{$m[0]};color:{$m[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600\">{$m[2]}</span>";
}

require_once __DIR__ . '/layout_header.php';
?>
<div class="section-header">
    <div>
        <h2 class="section-title">Verificar base de datos</h2>
        <p style="font-size:13px;color:var(--text-gray);margin-top:2px">Compara las tablas reales con lo que usa cada módulo y revisa la consistencia de los datos</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);max-width:640px;margin-bottom:20px">
    <div class="stat-card"><div class="stat-label">Errores</div><div class="stat-value" style="color:#b91c1c"><?= $conteo['error'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Advertencias</div><div class="stat-value" style="color:#b45309"><?= $conteo['warn'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Correctos</div><div class="stat-value" style="color:#15803d"><?= $conteo['ok'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Informativos</div><div class="stat-value"><?= $conteo['info'] ?></div></div>
</div>

<h3 style="margin:8px 0 10px">1. Estructura de tablas</h3>
<div class="table-card">
    <table class="data-table">
        <thead><tr><th style="width:130px">Estado</th><th style="width:240px">Tabla / columna</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($estructura as [$n, $t, $m]): ?>
            <tr><td><?= pastilla($n) ?></td><td><strong><?= e($t) ?></strong></td><td style="font-size:13px"><?= e($m) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h3 style="margin:24px 0 10px">2. Llaves foráneas existentes</h3>
<div class="table-card">
    <table class="data-table">
        <thead><tr><th>Tabla</th><th>Columna</th><th>Referencia</th></tr></thead>
        <tbody>
        <?php if (!$fks): ?>
            <tr><td colspan="3" style="color:var(--text-gray)">No hay llaves foráneas definidas. La integridad (ventas↔detalle, ventas↔usuarios) depende solo del código.</td></tr>
        <?php else: foreach ($fks as $f): ?>
            <tr><td><?= e($f['t']) ?></td><td><?= e($f['c']) ?></td><td><?= e($f['rt'] . '.' . $f['rc']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<h3 style="margin:24px 0 10px">3. Integridad de datos</h3>
<div class="table-card">
    <table class="data-table">
        <thead><tr><th style="width:130px">Estado</th><th>Revisión</th><th style="width:80px">Casos</th><th>Ejemplos / nota</th></tr></thead>
        <tbody>
        <?php foreach ($datos as [$n, $t, $c, $mu, $nota]): ?>
            <tr>
                <td><?= pastilla($n) ?></td>
                <td><?= e($t) ?></td>
                <td><?= e((string)$c) ?></td>
                <td style="font-size:12px;color:var(--text-gray)"><?= e(trim($mu . ($nota && $n !== 'ok' ? ($mu ? ' — ' : '') . $nota : ''))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/layout_footer.php'; ?>
