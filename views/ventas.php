<?php
require_once __DIR__ . '/data.php';

/* ── Conexión BD ── */
$env = require __DIR__ . '/../env.php';
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
    $env['DB_USER'], $env['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_POST['action'] ?? '';

/* ── Estructura de Detalle_ventas (material es opcional) ── */
function columnasDetalle(PDO $pdo): array {
    $c = [];
    foreach ($pdo->query("SHOW COLUMNS FROM Detalle_ventas")->fetchAll(PDO::FETCH_ASSOC) as $r) $c[$r['Field']] = $r['Type'];
    return $c;
}
$colsDet = columnasDetalle($pdo);
if (!isset($colsDet['material']) || !preg_match('/decimal|float|double/i', $colsDet['cantidad'] ?? '')) {
    try {
        if (!isset($colsDet['material'])) $pdo->exec("ALTER TABLE Detalle_ventas ADD COLUMN material VARCHAR(100) NULL AFTER id_ventas");
        if (!preg_match('/decimal|float|double/i', $colsDet['cantidad'] ?? '')) $pdo->exec("ALTER TABLE Detalle_ventas MODIFY cantidad DECIMAL(10,2) NOT NULL");
    } catch (PDOException $e) { /* sin permisos ALTER: se usa la estructura actual */ }
    $colsDet = columnasDetalle($pdo);
}
$detTieneMaterial = isset($colsDet['material']);
$cantEntera = !preg_match('/decimal|float|double/i', $colsDet['cantidad'] ?? '');
$existencias = existencias_leer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear_venta') {
    $fecha      = trim($_POST['fecha'] ?? '');
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $idsMat     = $_POST['materiales'] ?? [];
    $precios    = $_POST['precios']    ?? [];
    $cantidades = $_POST['cantidades'] ?? [];

    $catalogo = [];
    foreach (materiales_leer() as $m) $catalogo[$m['id_material']] = $m;

    $total = 0; $items = []; $cats = [];
    foreach ($idsMat as $i => $idm) {
        $p = floatval($precios[$i] ?? 0);
        $c = floatval($cantidades[$i] ?? 0);
        if ($cantEntera) $c = round($c);
        if (!isset($catalogo[$idm]) || $p <= 0 || $c <= 0) continue;
        $items[] = ['id' => $idm, 'material' => $catalogo[$idm]['nombre'], 'precio' => $p, 'cantidad' => $c];
        $cats[$catalogo[$idm]['categoria']] = true;
        $total += $p * $c;
    }
    $clasificacion = count($cats) === 1 ? (string)array_key_first($cats) : 'Mixto';

    $faltante = '';
    if (count($items) > 0) {
        $pedido = [];
        foreach ($items as $it) $pedido[$it['id']] = ($pedido[$it['id']] ?? 0) + $it['cantidad'];
        foreach ($pedido as $idm => $kg) {
            $disp = $existencias[$idm]['existencia'] ?? 0;
            if ($kg > $disp + 0.0001) { $faltante = "Existencia insuficiente de {$existencias[$idm]['nombre']}: disponible " . number_format(max(0, $disp), 2) . " kg."; break; }
        }
    }

    $msg_ok = $msg_err = '';
    if ($faltante) {
        $msg_err = $faltante;
    } elseif ($fecha && $id_usuario > 0 && count($items) > 0) {
        try {
            $pdo->beginTransaction();
            $ult = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(id_ventas,5) AS UNSIGNED)),0) FROM ventas WHERE id_ventas REGEXP '^VTA-[0-9]+$'")->fetchColumn();
            $id_ventas = sprintf('VTA-%03d', $ult + 1);
            $pdo->prepare("INSERT INTO ventas (id_ventas,fecha,total,id_usuario,clasificacion,estado) VALUES (?,?,?,?,?,'Activa')")
                ->execute([$id_ventas,$fecha,$total,$id_usuario,$clasificacion]);
            if ($detTieneMaterial) {
                $stmtD = $pdo->prepare("INSERT INTO Detalle_ventas (material,precio,cantidad,id_ventas) VALUES (?,?,?,?)");
                foreach ($items as $it) $stmtD->execute([$it['material'],$it['precio'],$it['cantidad'],$id_ventas]);
            } else {
                $stmtD = $pdo->prepare("INSERT INTO Detalle_ventas (precio,cantidad,id_ventas) VALUES (?,?,?)");
                foreach ($items as $it) $stmtD->execute([$it['precio'],$it['cantidad'],$id_ventas]);
            }
            $pdo->commit();
            $msg_ok = "Venta {$id_ventas} registrada correctamente.";
            auditar('ventas', 'crear', "{$id_ventas} por $" . number_format($total, 2));
        } catch (PDOException $e) { $pdo->rollBack(); $msg_err = "Error: ".$e->getMessage(); }
    } else { $msg_err = "Elige un usuario y agrega al menos un material con precio y kilos."; }
    header("Location: index.php?menu=ventas&msg=".urlencode($msg_ok)."&err=".urlencode($msg_err)); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancelar_venta') {
    $id = trim($_POST['id_ventas'] ?? '');
    if ($id) { $pdo->prepare("UPDATE ventas SET estado='Cancelada' WHERE id_ventas=?")->execute([$id]); auditar('ventas', 'cancelar', $id);
        try { $pdo->prepare("UPDATE comprobantes SET estado='Cancelada' WHERE tipo_origen='Venta' AND id_origen=? AND estado='Emitida'")->execute([$id]); } catch (PDOException $e) {} }
    header("Location: index.php?menu=ventas&msg=".urlencode("Venta {$id} cancelada.")."&err="); exit;
}

/* ── ELIMINAR VENTA (solo canceladas) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'eliminar_venta') {
    $id = trim($_POST['id_ventas'] ?? '');
    $msg_ok = $msg_err = '';
    if ($id) {
        try {
            $pdo->beginTransaction();
            // Primero eliminar el detalle (FK), luego la venta
            $pdo->prepare("DELETE FROM Detalle_ventas WHERE id_ventas=?")->execute([$id]);
            $pdo->prepare("DELETE FROM ventas WHERE id_ventas=? AND estado='Cancelada'")->execute([$id]);
            $pdo->commit();
            $msg_ok = "Venta {$id} eliminada permanentemente.";
            auditar('ventas', 'eliminar', $id);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg_err = "Error al eliminar: " . $e->getMessage();
        }
    } else {
        $msg_err = "ID de venta no válido.";
    }
    header("Location: index.php?menu=ventas&msg=".urlencode($msg_ok)."&err=".urlencode($msg_err)); exit;
}

$ventas = $pdo->query("
    SELECT v.id_ventas,v.fecha,v.total,v.clasificacion,v.estado,v.id_usuario,
           CONCAT(u.nombre,' ',u.apellido_paterno) AS nombre_usuario
    FROM ventas v LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario
    ORDER BY v.fecha DESC, v.id_ventas DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_ventas = count($ventas);
$activas      = count(array_filter($ventas, fn($v) => $v['estado']==='Activa'));
$canceladas   = count(array_filter($ventas, fn($v) => $v['estado']==='Cancelada'));
$ingresos     = array_sum(array_column(array_filter($ventas, fn($v) => $v['estado']==='Activa'), 'total'));
$usuarios     = $pdo->query("SELECT id_usuario,nombre,apellido_paterno FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$materiales_venta = materiales_leer();

$ver_id = $_GET['ver'] ?? '';
$ver_venta = null; $ver_detalle = [];
if ($ver_id) {
    $s = $pdo->prepare("SELECT v.*,CONCAT(u.nombre,' ',u.apellido_paterno) AS nombre_usuario FROM ventas v LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario WHERE v.id_ventas=?");
    $s->execute([$ver_id]); $ver_venta = $s->fetch(PDO::FETCH_ASSOC);
    $s2 = $pdo->prepare("SELECT ".($detTieneMaterial ? "material," : "")."precio,cantidad,(precio*cantidad) AS subtotal FROM Detalle_ventas WHERE id_ventas=?");
    $s2->execute([$ver_id]); $ver_detalle = $s2->fetchAll(PDO::FETCH_ASSOC);
}

$flash_ok  = urldecode($_GET['msg'] ?? '');
$flash_err = urldecode($_GET['err'] ?? '');
$titulo_pagina = 'Ventas';
$pagina_activa = 'ventas';
?>
<?php include __DIR__ . '/layout_header.php'; ?>

<?php if ($flash_ok): ?>
<div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:.9rem;">
    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash_ok) ?>
</div>
<?php endif; ?>
<?php if ($flash_err): ?>
<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:.9rem;">
    <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($flash_err) ?>
</div>
<?php endif; ?>

<div class="section-header">
    <h1 class="section-title">Ventas</h1>
    <button class="btn-primary" onclick="abrirModal('modalNueva')"><i class="fa-solid fa-plus"></i> Nueva venta</button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total ventas</div><div class="stat-value"><?= $total_ventas ?><i class="fa-solid fa-boxes-stacked stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Activas</div><div class="stat-value"><?= $activas ?><i class="fa-solid fa-circle-check stat-icon" style="color:#16a34a"></i></div></div>
    <div class="stat-card"><div class="stat-label">Canceladas</div><div class="stat-value"><?= $canceladas ?><i class="fa-solid fa-circle-xmark stat-icon" style="color:#dc2626"></i></div></div>
    <div class="stat-card"><div class="stat-label">Ingresos (activas)</div><div class="stat-value" style="font-size:20px;">$<?= number_format($ingresos,2) ?><i class="fa-solid fa-dollar-sign stat-icon"></i></div></div>
</div>

<div class="table-card">
    <table class="data-table">
        <thead><tr><th>#</th><th>Fecha</th><th>Total</th><th>Clasificación</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($ventas)): ?>
            <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text-gray);">No hay ventas registradas aún.</td></tr>
        <?php else: foreach ($ventas as $v): ?>
            <tr>
                <td style="font-weight:600;color:var(--green-dark)"><?= e($v['id_ventas']) ?></td>
                <td><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                <td>$<?= number_format($v['total'],2) ?></td>
                <td><?= e($v['clasificacion'] ?? '—') ?></td>
                <td><?= e($v['nombre_usuario'] ?? '#'.$v['id_usuario']) ?></td>
                <td><span class="badge <?= $v['estado']==='Activa'?'badge-green':'badge-gray' ?>"><?= e($v['estado']) ?></span></td>
                <td>
                    <a href="index.php?menu=ventas&ver=<?= urlencode($v['id_ventas']) ?>" class="btn-icon" title="Ver detalle"><i class="fa-solid fa-eye"></i></a>
                    <?php if ($v['estado']==='Activa'): ?>
                        <a href="index.php?menu=tickets&opc=tabla&nuevo=<?= urlencode($v['id_ventas']) ?>" class="btn-icon" title="Generar comprobante"><i class="fa-solid fa-file-invoice"></i></a>
                        <button class="btn-icon danger" title="Cancelar venta" onclick="confirmarCancelar('<?= e($v['id_ventas']) ?>')">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    <?php endif; ?>
                    <?php if ($v['estado']==='Cancelada'): ?>
                        <button class="btn-icon danger" title="Eliminar permanentemente" onclick="confirmarEliminar('<?= e($v['id_ventas']) ?>')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL NUEVA VENTA -->
<div class="modal-overlay" id="modalNueva">
    <div class="modal-box" style="width:min(560px,95vw)">
        <div class="modal-head">
            <h3><i class="fa-solid fa-file-invoice-dollar" style="color:var(--green-mid);margin-right:8px"></i>Registrar nueva venta</h3>
            <button class="modal-close" onclick="cerrarModal('modalNueva')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="index.php?menu=ventas">
            <input type="hidden" name="action" value="crear_venta">
            <div class="modal-body">
                <div style="display:flex;gap:14px">
                    <div class="form-row" style="flex:1"><label>Fecha *</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-row" style="flex:1">
                        <label>Usuario *</label>
                        <select name="id_usuario" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id_usuario'] ?>"><?= e($u['nombre'].' '.$u['apellido_paterno']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-size:13px;color:var(--text-gray);font-weight:500;display:block;margin-bottom:8px">Materiales vendidos *</label>
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 36px;gap:10px;font-size:11.5px;color:var(--text-gray);font-weight:600;margin-bottom:4px">
                        <span>Material</span><span>Precio por kg</span><span>Kilos</span><span>Subtotal</span><span></span>
                    </div>
                    <div id="itemsContainer"></div>
                    <template id="tplItem">
                        <div class="item-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 36px;gap:10px;align-items:center;margin-bottom:8px">
                            <select name="materiales[]" onchange="selMat(this)" required style="border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;outline:none;width:100%;box-sizing:border-box;background:#fff">
                                <option value="">-- Elegir --</option>
                                <?php foreach ($materiales_venta as $m): ?>
                                <?php $disp = $existencias[$m['id_material']]['existencia'] ?? 0; ?>
                                <option value="<?= e($m['id_material']) ?>" data-precio="<?= (float)$m['precio_kg'] ?>" data-stock="<?= round($disp, 2) ?>"<?= $disp <= 0 ? ' disabled' : '' ?>><?= e($m['nombre']) ?> — <?= number_format(max(0, $disp), 2) ?> kg disp.</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="precios[]" placeholder="$ / kg" min="0.01" step="0.01" style="border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;outline:none;width:100%;box-sizing:border-box;background:#fff" oninput="calcTotal()" required>
                            <input type="number" name="cantidades[]" placeholder="kg" min="0.01" step="0.01" style="border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;outline:none;width:100%;box-sizing:border-box;background:#fff" oninput="calcTotal()" required>
                            <div class="item-sub" style="font-weight:600;color:var(--green-dark);font-size:14px">$0.00</div>
                            <button type="button" class="btn-icon danger" onclick="removeItem(this)"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </template>
                    <button type="button" onclick="addItem()" style="width:100%;background:var(--green-light);border:1.5px dashed var(--green-mid);color:var(--green-dark);border-radius:10px;padding:9px;font-size:13px;font-weight:500;cursor:pointer;margin-top:2px">
                        <i class="fa-solid fa-plus"></i> Agregar otro material
                    </button>
                    <div style="font-size:11.5px;color:var(--text-gray);margin-top:8px">El folio de la venta y su clasificación se asignan automáticamente.<?php if (!$detTieneMaterial): ?> <strong style="color:#dc2626">No se pudo preparar Detalle_ventas (permiso ALTER); ejecute migracion_detalle_ventas.sql.</strong><?php endif; ?></div>
                </div>
                <div style="background:var(--green-light);border-radius:10px;padding:14px 18px;border:1px solid #86efac">
                    <div style="font-size:12px;color:var(--green-dark);font-weight:500;margin-bottom:4px">Total calculado</div>
                    <div id="totalDisplay" style="font-size:1.5rem;font-weight:700;color:var(--green-dark)">$0.00</div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalNueva')">Cancelar</button>
                <button type="submit" class="btn-accept"><i class="fa-solid fa-floppy-disk"></i> Guardar venta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VER DETALLE -->
<?php if ($ver_venta): ?>
<div class="modal-overlay open" id="modalDetalle">
    <div class="modal-box" style="width:min(540px,95vw)">
        <div class="modal-head">
            <h3><i class="fa-solid fa-eye" style="color:var(--green-mid);margin-right:8px"></i>Detalle de venta</h3>
            <a href="index.php?menu=ventas" class="modal-close"><i class="fa-solid fa-xmark"></i></a>
        </div>
        <div class="modal-body">
            <div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:18px">
                <div><div style="font-size:11px;color:var(--text-gray)">ID</div><strong><?= e($ver_venta['id_ventas']) ?></strong></div>
                <div><div style="font-size:11px;color:var(--text-gray)">Fecha</div><strong><?= date('d/m/Y', strtotime($ver_venta['fecha'])) ?></strong></div>
                <div><div style="font-size:11px;color:var(--text-gray)">Clasificación</div><strong><?= e($ver_venta['clasificacion'] ?? '—') ?></strong></div>
                <div><div style="font-size:11px;color:var(--text-gray)">Usuario</div><strong><?= e($ver_venta['nombre_usuario'] ?? '—') ?></strong></div>
                <div><div style="font-size:11px;color:var(--text-gray)">Estado</div>
                    <span class="badge <?= $ver_venta['estado']==='Activa'?'badge-green':'badge-gray' ?>"><?= e($ver_venta['estado']) ?></span>
                </div>
            </div>
            <table class="data-table" style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
                <thead><tr><th>#</th><?php if ($detTieneMaterial): ?><th>Material</th><?php endif; ?><th>Precio/kg</th><th>Kg</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($ver_detalle as $i => $d): ?>
                <tr><td><?= $i+1 ?></td><?php if ($detTieneMaterial): ?><td><?= e($d['material'] ?? '—') ?></td><?php endif; ?><td>$<?= number_format($d['precio'],2) ?></td><td><?= $d['cantidad'] ?></td><td>$<?= number_format($d['subtotal'],2) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--green-light)">
                        <td colspan="<?= $detTieneMaterial ? 4 : 3 ?>" style="text-align:right;font-weight:700;padding:12px 16px;color:var(--green-dark)">TOTAL</td>
                        <td style="font-weight:700;color:var(--green-dark);padding:12px 16px">$<?= number_format($ver_venta['total'],2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="modal-foot"><a href="index.php?menu=ventas" class="btn-cancel">Cerrar</a></div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL CANCELAR VENTA -->
<div class="modal-overlay" id="modalCancelar">
    <div class="modal-box" style="width:min(420px,95vw)">
        <div class="modal-head">
            <h3><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;margin-right:8px"></i>Cancelar venta</h3>
            <button class="modal-close" onclick="cerrarModal('modalCancelar')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-gray);line-height:1.7">¿Seguro que deseas cancelar la venta <strong id="cancelarIdLabel"></strong>?<br>Quedará marcada como <em>Cancelada</em>.</p>
        </div>
        <form method="POST" action="index.php?menu=ventas">
            <input type="hidden" name="action" value="cancelar_venta">
            <input type="hidden" name="id_ventas" id="cancelarIdInput">
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalCancelar')">No, volver</button>
                <button type="submit" class="btn-accept" style="background:#f59e0b"><i class="fa-solid fa-ban"></i> Sí, cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ELIMINAR VENTA -->
<div class="modal-overlay" id="modalEliminar">
    <div class="modal-box" style="width:min(420px,95vw)">
        <div class="modal-head">
            <h3><i class="fa-solid fa-trash" style="color:#dc2626;margin-right:8px"></i>Eliminar venta</h3>
            <button class="modal-close" onclick="cerrarModal('modalEliminar')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-gray);line-height:1.7">
                ¿Seguro que deseas eliminar permanentemente la venta <strong id="eliminarIdLabel"></strong>?<br>
                <span style="color:#dc2626;font-weight:500">Esta acción no se puede deshacer.</span>
            </p>
        </div>
        <form method="POST" action="index.php?menu=ventas">
            <input type="hidden" name="action" value="eliminar_venta">
            <input type="hidden" name="id_ventas" id="eliminarIdInput">
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalEliminar')">No, volver</button>
                <button type="submit" class="btn-accept" style="background:#dc2626"><i class="fa-solid fa-trash"></i> Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id)  { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target===o) o.classList.remove('open'); }));

function confirmarCancelar(id) {
    document.getElementById('cancelarIdLabel').textContent = id;
    document.getElementById('cancelarIdInput').value = id;
    abrirModal('modalCancelar');
}

function confirmarEliminar(id) {
    document.getElementById('eliminarIdLabel').textContent = id;
    document.getElementById('eliminarIdInput').value = id;
    abrirModal('modalEliminar');
}

function addItem() {
    document.getElementById('itemsContainer').appendChild(document.getElementById('tplItem').content.cloneNode(true));
}
function selMat(sel) {
    const row = sel.closest('.item-row');
    const precio = sel.selectedOptions[0]?.dataset.precio;
    if (precio) row.querySelector('[name="precios[]"]').value = precio;
    const stock = sel.selectedOptions[0]?.dataset.stock, kg = row.querySelector('[name="cantidades[]"]');
    if (stock !== undefined) { kg.max = stock; kg.placeholder = 'máx ' + stock; } else { kg.removeAttribute('max'); kg.placeholder = 'kg'; }
    calcTotal();
}
function removeItem(btn) {
    if (document.querySelectorAll('.item-row').length === 1) return;
    btn.closest('.item-row').remove(); calcTotal();
}
function calcTotal() {
    let t = 0;
    document.querySelectorAll('.item-row').forEach(r => {
        const sub = (parseFloat(r.querySelector('[name="precios[]"]').value) || 0) * (parseFloat(r.querySelector('[name="cantidades[]"]').value) || 0);
        r.querySelector('.item-sub').textContent = '$' + sub.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
        t += sub;
    });
    document.getElementById('totalDisplay').textContent = '$' + t.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
}
addItem();
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>