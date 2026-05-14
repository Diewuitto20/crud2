<?php
require_once __DIR__ . '/data.php';
// views/ventas.php - corregido: ruta de data.php y env.php

/* ── Conexión BD ── */
$env = require __DIR__ . '/../env.php';
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
    $env['DB_USER'], $env['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear_venta') {
    $id_ventas     = trim($_POST['id_ventas']    ?? '');
    $fecha         = trim($_POST['fecha']         ?? '');
    $clasificacion = trim($_POST['clasificacion'] ?? '');
    $id_usuario    = intval($_POST['id_usuario']  ?? 0);
    $precios       = $_POST['precios']            ?? [];
    $cantidades    = $_POST['cantidades']         ?? [];

    $total = 0; $items = [];
    for ($i = 0; $i < count($precios); $i++) {
        $p = floatval($precios[$i]); $c = intval($cantidades[$i]);
        if ($p > 0 && $c > 0) { $items[] = ['precio'=>$p,'cantidad'=>$c]; $total += $p*$c; }
    }

    $msg_ok = $msg_err = '';
    if ($id_ventas && $fecha && $id_usuario > 0 && count($items) > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO ventas (id_ventas,fecha,total,id_usuario,clasificacion,estado) VALUES (?,?,?,?,?,'Activa')")
                ->execute([$id_ventas,$fecha,$total,$id_usuario,$clasificacion]);
            $stmtD = $pdo->prepare("INSERT INTO Detalle_ventas (precio,cantidad,id_ventas) VALUES (?,?,?)");
            foreach ($items as $item) $stmtD->execute([$item['precio'],$item['cantidad'],$id_ventas]);
            $pdo->commit();
            $msg_ok = "Venta {$id_ventas} registrada correctamente.";
        } catch (PDOException $e) { $pdo->rollBack(); $msg_err = "Error: ".$e->getMessage(); }
    } else { $msg_err = "Completa todos los campos y agrega al menos un artículo válido."; }
    header("Location: index.php?menu=ventas&msg=".urlencode($msg_ok)."&err=".urlencode($msg_err)); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancelar_venta') {
    $id = trim($_POST['id_ventas'] ?? '');
    if ($id) $pdo->prepare("UPDATE ventas SET estado='Cancelada' WHERE id_ventas=?")->execute([$id]);
    header("Location: index.php?menu=ventas&msg=".urlencode("Venta {$id} cancelada.")."&err="); exit;
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

$ver_id = $_GET['ver'] ?? '';
$ver_venta = null; $ver_detalle = [];
if ($ver_id) {
    $s = $pdo->prepare("SELECT v.*,CONCAT(u.nombre,' ',u.apellido_paterno) AS nombre_usuario FROM ventas v LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario WHERE v.id_ventas=?");
    $s->execute([$ver_id]); $ver_venta = $s->fetch(PDO::FETCH_ASSOC);
    $s2 = $pdo->prepare("SELECT precio,cantidad,(precio*cantidad) AS subtotal FROM Detalle_ventas WHERE id_ventas=?");
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
                    <button class="btn-icon danger" title="Cancelar" onclick="confirmarCancelar('<?= e($v['id_ventas']) ?>')"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

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
                    <div class="form-row" style="flex:1"><label>ID de venta *</label><input type="text" name="id_ventas" placeholder="Ej: VTA-001" required></div>
                    <div class="form-row" style="flex:1"><label>Fecha *</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required></div>
                </div>
                <div style="display:flex;gap:14px">
                    <div class="form-row" style="flex:1">
                        <label>Clasificación</label>
                        <select name="clasificacion">
                            <option value="">-- Seleccionar --</option>
                            <option>Plástico</option><option>Metal</option><option>Papel</option>
                            <option>Vidrio</option><option>Electrónico</option><option>Mixto</option>
                        </select>
                    </div>
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
                    <label style="font-size:13px;color:var(--text-gray);font-weight:500;display:block;margin-bottom:8px">Artículos *</label>
                    <div id="itemsContainer">
                        <div class="item-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
                            <input type="number" name="precios[]" placeholder="Precio $" min="0.01" step="0.01" style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;outline:none" oninput="calcTotal()" required>
                            <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;outline:none" oninput="calcTotal()" required>
                            <button type="button" class="btn-icon danger" onclick="removeItem(this)"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                    <button type="button" onclick="addItem()" style="width:100%;background:var(--green-light);border:1.5px dashed var(--green-mid);color:var(--green-dark);border-radius:10px;padding:9px;font-size:13px;font-weight:500;cursor:pointer;margin-top:2px">
                        <i class="fa-solid fa-plus"></i> Agregar artículo
                    </button>
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
                <thead><tr><th>#</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($ver_detalle as $i => $d): ?>
                <tr><td><?= $i+1 ?></td><td>$<?= number_format($d['precio'],2) ?></td><td><?= $d['cantidad'] ?></td><td>$<?= number_format($d['subtotal'],2) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--green-light)">
                        <td colspan="3" style="text-align:right;font-weight:700;padding:12px 16px;color:var(--green-dark)">TOTAL</td>
                        <td style="font-weight:700;color:var(--green-dark);padding:12px 16px">$<?= number_format($ver_venta['total'],2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="modal-foot"><a href="index.php?menu=ventas" class="btn-cancel">Cerrar</a></div>
    </div>
</div>
<?php endif; ?>

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
                <button type="submit" class="btn-accept" style="background:#dc2626"><i class="fa-solid fa-trash"></i> Sí, cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id)  { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target===o) o.classList.remove('open'); }));
function confirmarCancelar(id) { document.getElementById('cancelarIdLabel').textContent=id; document.getElementById('cancelarIdInput').value=id; abrirModal('modalCancelar'); }
function addItem() {
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', `
        <div class="item-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
            <input type="number" name="precios[]" placeholder="Precio $" min="0.01" step="0.01" style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;outline:none" oninput="calcTotal()">
            <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;outline:none" oninput="calcTotal()">
            <button type="button" class="btn-icon danger" onclick="removeItem(this)"><i class="fa-solid fa-trash-can"></i></button>
        </div>`);
}
function removeItem(btn) { if (document.querySelectorAll('.item-row').length===1) return; btn.closest('.item-row').remove(); calcTotal(); }
function calcTotal() {
    const p=document.querySelectorAll('[name="precios[]"]'), c=document.querySelectorAll('[name="cantidades[]"]');
    let t=0; p.forEach((x,i)=>t+=(parseFloat(x.value)||0)*(parseInt(c[i]?.value)||0));
    document.getElementById('totalDisplay').textContent='$'+t.toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});
}
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>