<?php
require_once __DIR__ . '/data.php';

$titulo_pagina = 'Comprobantes';
$pagina_activa = 'tickets';

$pdo = get_pdo();
const IVA_TASA = 0.16;
const FORMAS_PAGO = ['Efectivo', 'Transferencia', 'Cheque', 'Tarjeta'];
const MODOS_IVA = ['ninguno' => 'Sin IVA', 'incluido' => 'IVA 16% incluido en el total', 'adicional' => 'IVA 16% adicional al total'];

/* ── Estructura de la tabla (se crea sola si hay permiso) ── */
$errTabla = '';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS comprobantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folio VARCHAR(20) NOT NULL UNIQUE,
        tipo_origen VARCHAR(10) NOT NULL,
        id_origen VARCHAR(50) NOT NULL,
        fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cliente_nombre VARCHAR(150) NOT NULL,
        cliente_rfc VARCHAR(20) NULL,
        cliente_direccion VARCHAR(255) NULL,
        cliente_contacto VARCHAR(120) NULL,
        forma_pago VARCHAR(20) NOT NULL,
        iva_modo VARCHAR(10) NOT NULL DEFAULT 'ninguno',
        notas VARCHAR(255) NULL,
        detalle TEXT NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        iva DECIMAL(12,2) NOT NULL DEFAULT 0,
        total DECIMAL(12,2) NOT NULL,
        estado VARCHAR(12) NOT NULL DEFAULT 'Emitida',
        id_usuario INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    $errTabla = 'No se pudo crear la tabla comprobantes (permiso CREATE). Ejecute migracion_comprobantes.sql en phpMyAdmin.';
}

/* ¿Detalle_ventas guarda el material? */
$detTieneMaterial = false;
foreach ($pdo->query("SHOW COLUMNS FROM Detalle_ventas")->fetchAll() as $c) {
    if ($c['Field'] === 'material') $detTieneMaterial = true;
}

/* ── Orígenes disponibles: ventas activas sin comprobante vigente ── */
function origenes_disponibles(PDO $pdo, bool $detMat): array {
    $usados = [];
    try {
        foreach ($pdo->query("SELECT tipo_origen, id_origen FROM comprobantes WHERE estado = 'Emitida'")->fetchAll() as $u)
            $usados[$u['tipo_origen'] . '|' . $u['id_origen']] = true;
    } catch (PDOException $e) {}
    $res = [];
    $detSql = $pdo->prepare("SELECT " . ($detMat ? "material," : "") . "precio, cantidad FROM Detalle_ventas WHERE id_ventas = ?");
    foreach ($pdo->query("SELECT id_ventas, fecha, total, clasificacion FROM ventas WHERE estado = 'Activa' ORDER BY fecha DESC, id_ventas DESC")->fetchAll() as $v) {
        $key = 'Venta|' . $v['id_ventas'];
        if (isset($usados[$key])) continue;
        $detSql->execute([$v['id_ventas']]);
        $lineas = array_map(fn($d) => [
            'material' => ($d['material'] ?? '') ?: ($v['clasificacion'] ?: 'Material'),
            'cantidad' => (float)$d['cantidad'], 'unidad' => 'kg', 'precio' => (float)$d['precio'],
        ], $detSql->fetchAll());
        $res[] = ['key' => $key, 'folio' => $v['id_ventas'], 'tipo' => 'Venta', 'fecha' => $v['fecha'],
                  'total' => (float)$v['total'], 'cliente' => '', 'lineas' => $lineas];
    }
    usort($res, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    return $res;
}

/* ── Acciones ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errTabla) {
    $action = $_POST['action'] ?? '';
    $err = ''; $destino = 'index.php?menu=tickets&opc=tabla';

    if ($action === 'crear') {
        $origen  = null;
        foreach (origenes_disponibles($pdo, $detTieneMaterial) as $o) if ($o['key'] === ($_POST['origen'] ?? '')) { $origen = $o; break; }
        $cliente = trim($_POST['cliente_nombre'] ?? '');
        $rfc     = strtoupper(trim($_POST['cliente_rfc'] ?? ''));
        $forma   = $_POST['forma_pago'] ?? '';
        $modo    = $_POST['iva_modo'] ?? 'ninguno';

        if (!$origen)                                  $err = 'Elige una venta disponible.';
        elseif ($cliente === '')                       $err = 'Indica el nombre o razón social del cliente.';
        elseif (!in_array($forma, FORMAS_PAGO, true))  $err = 'Elige la forma de pago.';
        elseif (!isset(MODOS_IVA[$modo]))              $err = 'Opción de IVA no válida.';
        elseif ($rfc !== '' && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) $err = 'El RFC no tiene un formato válido.';

        if (!$err) {
            $t = $origen['total'];
            if ($modo === 'incluido')       { $sub = round($t / (1 + IVA_TASA), 2); $iva = round($t - $sub, 2); $tot = $t; }
            elseif ($modo === 'adicional')  { $sub = $t; $iva = round($t * IVA_TASA, 2); $tot = round($t + $iva, 2); }
            else                            { $sub = $t; $iva = 0; $tot = $t; }
            try {
                $ult = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(folio,3) AS UNSIGNED)),0) FROM comprobantes WHERE folio REGEXP '^F-[0-9]+$'")->fetchColumn();
                $folio = sprintf('F-%04d', $ult + 1);
                $pdo->prepare("INSERT INTO comprobantes (folio,tipo_origen,id_origen,cliente_nombre,cliente_rfc,cliente_direccion,cliente_contacto,forma_pago,iva_modo,notas,detalle,subtotal,iva,total,id_usuario)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$folio, $origen['tipo'], explode('|', $origen['key'], 2)[1], $cliente, $rfc ?: null,
                               trim($_POST['cliente_direccion'] ?? '') ?: null, trim($_POST['cliente_contacto'] ?? '') ?: null,
                               $forma, $modo, trim($_POST['notas'] ?? '') ?: null,
                               json_encode($origen['lineas'], JSON_UNESCAPED_UNICODE), $sub, $iva, $tot, $_SESSION['id_usuario'] ?? null]);
                auditar('comprobantes', 'crear', "$folio de {$origen['folio']} por $" . number_format($tot, 2));
                $destino .= '&imprimir=' . urlencode($folio);
            } catch (PDOException $e) { $err = 'No se pudo guardar: ' . $e->getMessage(); }
        }
    } elseif ($action === 'cancelar') {
        $folio = trim($_POST['folio'] ?? '');
        $pdo->prepare("UPDATE comprobantes SET estado = 'Cancelada' WHERE folio = ?")->execute([$folio]);
        auditar('comprobantes', 'cancelar', $folio);
    }
    header('Location: ' . $destino . ($err ? '&err=' . urlencode($err) : ''));
    exit;
}

/* ── Datos ── */
$lista = []; $origenes = [];
if (!$errTabla) {
    $lista    = $pdo->query("SELECT * FROM comprobantes ORDER BY fecha_emision DESC, id DESC")->fetchAll();
    $origenes = origenes_disponibles($pdo, $detTieneMaterial);
}
$emitidos    = array_filter($lista, fn($c) => $c['estado'] === 'Emitida');
$total_emit  = count($emitidos);
$monto_emit  = array_sum(array_column($emitidos, 'total'));
$mes_emit    = array_sum(array_map(fn($c) => (float)$c['total'], array_filter($emitidos, fn($c) => substr($c['fecha_emision'], 0, 7) === date('Y-m'))));

/* Comprobante a imprimir (antes de filtrar la lista) */
$doc = null;
if (!empty($_GET['imprimir'])) {
    foreach ($lista as $c) if ($c['folio'] === $_GET['imprimir']) { $doc = $c; break; }
    if ($doc) {
        $doc['lineas'] = json_decode($doc['detalle'], true) ?: [];
        auditar('comprobantes', 'imprimir', $doc['folio']);
    }
}

$buscar = trim($_GET['buscar'] ?? '');
if ($buscar !== '') {
    $lista = array_filter($lista, function ($c) use ($buscar) {
        foreach (['folio', 'cliente_nombre', 'cliente_rfc', 'id_origen'] as $k) if (stripos((string)$c[$k], $buscar) !== false) return true;
        return false;
    });
}

include 'layout_header.php';
?>

<?php if ($doc): ?>
<style>
@media print { body>*:not(#pa){display:none!important} #pa{display:block!important;margin:0!important;border:none!important;max-width:none!important} #pa .pab{display:none!important} }
#pa{display:none;max-width:780px;margin:30px auto;font-family:'Roboto',sans-serif;border:1.5px solid #1a5632;border-radius:12px;padding:34px 40px;color:#1f2937;background:#fff}
#pa .hd{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1a5632;padding-bottom:14px;margin-bottom:18px}
#pa .emp{font-size:22px;font-weight:700;color:#1a5632} #pa .emp small{display:block;font-size:12px;font-weight:400;color:#6b7280}
#pa .fo{text-align:right} #pa .fo b{font-size:20px;color:#1a5632} #pa .fo span{display:block;font-size:12.5px;color:#6b7280}
#pa .cli{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;font-size:13.5px;margin-bottom:18px}
#pa .cli span{color:#6b7280}
#pa table{width:100%;border-collapse:collapse;font-size:13.5px;margin-bottom:14px}
#pa th{background:#e8f5ee;text-align:left;padding:8px 10px;border-bottom:1px solid #1a5632}
#pa td{padding:8px 10px;border-bottom:1px solid #e5e7eb} #pa .r{text-align:right}
#pa .tot{margin-left:auto;width:300px;font-size:14px} #pa .tot div{display:flex;justify-content:space-between;padding:4px 0}
#pa .tot .g{font-size:17px;font-weight:700;color:#1a5632;border-top:2px solid #1a5632;margin-top:4px;padding-top:8px}
#pa .cx{text-align:center;color:#dc2626;font-weight:700;border:2px solid #dc2626;border-radius:8px;padding:6px;margin-bottom:14px}
#pa .ft{font-size:11px;color:#9ca3af;text-align:center;margin-top:22px;border-top:1px dashed #d1d5db;padding-top:10px}
#pa .pab{text-align:center;margin-top:24px;display:flex;gap:10px;justify-content:center}
</style>
<div id="pa">
    <div class="hd">
        <div class="emp"><i class="fa-solid fa-recycle"></i> Recicladora Diaz<small>Comprobante tipo factura</small></div>
        <div class="fo"><b><?= e($doc['folio']) ?></b><span>Emitido: <?= date('d/m/Y H:i', strtotime($doc['fecha_emision'])) ?></span><span>Origen: <?= e($doc['tipo_origen']) ?> <?= e($doc['id_origen']) ?></span></div>
    </div>
    <?php if ($doc['estado'] === 'Cancelada'): ?><div class="cx">CANCELADO</div><?php endif; ?>
    <div class="cli">
        <div><span>Cliente:</span> <strong><?= e($doc['cliente_nombre']) ?></strong></div>
        <div><span>RFC:</span> <?= e($doc['cliente_rfc'] ?: '—') ?></div>
        <div><span>Dirección:</span> <?= e($doc['cliente_direccion'] ?: '—') ?></div>
        <div><span>Contacto:</span> <?= e($doc['cliente_contacto'] ?: '—') ?></div>
        <div><span>Forma de pago:</span> <?= e($doc['forma_pago']) ?></div>
    </div>
    <table>
        <thead><tr><th>Material</th><th class="r">Cantidad</th><th class="r">Precio unit.</th><th class="r">Importe</th></tr></thead>
        <tbody>
        <?php foreach ($doc['lineas'] as $l): ?>
            <tr><td><?= e($l['material']) ?></td><td class="r"><?= number_format($l['cantidad'], 2) ?> <?= e($l['unidad']) ?></td>
                <td class="r">$<?= number_format($l['precio'], 2) ?></td><td class="r">$<?= number_format($l['cantidad'] * $l['precio'], 2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tot">
        <div><span>Subtotal</span><span>$<?= number_format($doc['subtotal'], 2) ?></span></div>
        <?php if ((float)$doc['iva'] > 0): ?><div><span>IVA 16%</span><span>$<?= number_format($doc['iva'], 2) ?></span></div><?php endif; ?>
        <div class="g"><span>TOTAL</span><span>$<?= number_format($doc['total'], 2) ?></span></div>
    </div>
    <?php if ($doc['notas']): ?><p style="font-size:12.5px;color:#6b7280;margin-top:14px">Notas: <?= e($doc['notas']) ?></p><?php endif; ?>
    <div class="ft">Documento informativo de Recicladora Diaz; no sustituye una factura fiscal (CFDI).</div>
    <div class="pab">
        <button onclick="window.print()" class="btn-primary"><i class="fa-solid fa-print"></i> Imprimir</button>
        <a href="index.php?menu=tickets&opc=tabla" style="padding:9px 20px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
</div>
<script>window.onload=()=>window.print();</script>
<?php include 'layout_footer.php'; return; endif; ?>

<?php foreach ([$errTabla, $_GET['err'] ?? ''] as $msg): if ($msg): ?>
<div style="background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13.5px;font-weight:500"><?= e($msg) ?></div>
<?php endif; endforeach; ?>

<div class="section-header">
    <div>
        <div class="section-title"><i class="fa-solid fa-file-invoice" style="color:var(--green-mid);margin-right:8px;"></i>Comprobantes</div>
        <p style="font-size:13px;color:var(--text-gray);margin-top:2px">Elige una venta y agrega los datos del cliente para emitir el comprobante tipo factura</p>
    </div>
    <button class="btn-primary" onclick="openModal('modal-nuevo')"><i class="fa-solid fa-plus"></i> Nuevo comprobante</button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Comprobantes emitidos</div><div class="stat-value"><?= $total_emit ?><i class="fa-solid fa-file-invoice stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Monto comprobado</div><div class="stat-value">$<?= number_format($monto_emit, 2) ?><i class="fa-solid fa-dollar-sign stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Este mes</div><div class="stat-value">$<?= number_format($mes_emit, 2) ?><i class="fa-solid fa-calendar-day stat-icon"></i></div></div>
</div>

<form method="GET" action="index.php" style="margin-bottom:16px;display:flex;gap:10px;">
    <input type="hidden" name="menu" value="tickets"><input type="hidden" name="opc" value="tabla">
    <div class="search-bar" style="width:340px;"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="buscar" placeholder="Buscar folio, cliente, RFC o venta…" value="<?= e($buscar) ?>"></div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-search"></i> Buscar</button>
    <?php if ($buscar): ?><a href="index.php?menu=tickets&opc=tabla" class="btn-cancel" style="padding:9px 18px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;">Limpiar</a><?php endif; ?>
</form>

<div class="table-card">
    <table class="data-table">
        <thead><tr><th>Folio</th><th>Fecha</th><th>Origen</th><th>Cliente</th><th>RFC</th><th>Pago</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($lista)): ?>
            <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-gray)">Aún no hay comprobantes emitidos</td></tr>
        <?php else: foreach ($lista as $c): ?>
            <tr>
                <td><span class="badge badge-green"><?= e($c['folio']) ?></span></td>
                <td style="font-size:13px;color:var(--text-gray)"><?= date('d/m/Y', strtotime($c['fecha_emision'])) ?></td>
                <td><?= e($c['tipo_origen']) ?> <?= e($c['id_origen']) ?></td>
                <td style="font-weight:500"><?= e($c['cliente_nombre']) ?></td>
                <td><?= e($c['cliente_rfc'] ?: '—') ?></td>
                <td><?= e($c['forma_pago']) ?></td>
                <td><strong>$<?= number_format($c['total'], 2) ?></strong></td>
                <td><span class="badge <?= $c['estado'] === 'Cancelada' ? 'badge-gray' : 'badge-green' ?>"><?= e($c['estado']) ?></span></td>
                <td style="white-space:nowrap">
                    <a class="btn-icon" title="Imprimir" href="index.php?menu=tickets&opc=tabla&imprimir=<?= urlencode($c['folio']) ?>"><i class="fa-solid fa-print"></i></a>
                    <?php if ($c['estado'] === 'Emitida'): ?>
                    <form method="POST" action="index.php?menu=tickets&opc=tabla" style="display:inline" onsubmit="return confirm('¿Cancelar el comprobante <?= e($c['folio']) ?>?')">
                        <input type="hidden" name="action" value="cancelar"><input type="hidden" name="folio" value="<?= e($c['folio']) ?>">
                        <button class="btn-icon danger" title="Cancelar"><i class="fa-solid fa-ban"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal-nuevo">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa-solid fa-file-invoice" style="color:var(--green-mid);margin-right:8px;"></i>Nuevo comprobante</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="index.php?menu=tickets&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row">
                    <label>Venta *</label>
                    <select name="origen" id="cmp_origen" required onchange="elegirOrigen()">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($origenes as $o): ?>
                        <option value="<?= e($o['key']) ?>"><?= e($o['folio']) ?> · <?= date('d/m/Y', strtotime($o['fecha'])) ?> · $<?= number_format($o['total'], 2) ?> (<?= e($o['tipo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="cmp_resumen" style="display:none;background:var(--green-light);border:1px solid #86efac;border-radius:10px;padding:12px 16px;font-size:13px;"></div>
                <div class="form-row"><label>Cliente / razón social *</label><input type="text" name="cliente_nombre" id="cmp_cliente" required placeholder="Nombre o razón social"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row"><label>RFC (opcional)</label><input type="text" name="cliente_rfc" placeholder="XAXX010101000" maxlength="13" style="text-transform:uppercase"></div>
                    <div class="form-row"><label>Teléfono o correo</label><input type="text" name="cliente_contacto" placeholder="Opcional"></div>
                </div>
                <div class="form-row"><label>Dirección</label><input type="text" name="cliente_direccion" placeholder="Opcional"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row"><label>Forma de pago *</label>
                        <select name="forma_pago" required>
                            <?php foreach (FORMAS_PAGO as $fp): ?><option><?= e($fp) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>IVA</label>
                        <select name="iva_modo">
                            <?php foreach (MODOS_IVA as $k => $lbl): ?><option value="<?= $k ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row"><label>Notas</label><input type="text" name="notas" maxlength="255" placeholder="Opcional"></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-nuevo')">Cancelar</button>
                <button type="submit" class="btn-accept"><i class="fa-solid fa-file-invoice"></i> Emitir comprobante</button>
            </div>
        </form>
    </div>
</div>

<script>
const ORIGENES = <?= json_encode((object)array_column($origenes, null, 'key'), JSON_UNESCAPED_UNICODE) ?>;
const fmt = n => '$' + n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function elegirOrigen() {
    const o = ORIGENES[document.getElementById('cmp_origen').value], box = document.getElementById('cmp_resumen');
    if (!o) { box.style.display = 'none'; return; }
    box.style.display = 'block';
    box.innerHTML = o.lineas.map(l => `<div style="display:flex;justify-content:space-between"><span>${l.material} — ${l.cantidad.toFixed(2)} ${l.unidad} × ${fmt(l.precio)}</span><b>${fmt(l.cantidad * l.precio)}</b></div>`).join('')
        + `<div style="display:flex;justify-content:space-between;border-top:1px solid #86efac;margin-top:6px;padding-top:6px"><b>Total</b><b>${fmt(o.total)}</b></div>`;
    const c = document.getElementById('cmp_cliente');
    if (o.cliente && !c.value) c.value = o.cliente;
}
const pre = new URLSearchParams(location.search).get('nuevo');
if (pre) {
    const sel = document.getElementById('cmp_origen');
    for (const op of sel.options) if (op.value === 'Venta|' + pre) { sel.value = op.value; break; }
    elegirOrigen(); openModal('modal-nuevo');
}
</script>

<?php include 'layout_footer.php'; ?>
