<?php
require_once __DIR__ . '/data.php';

$titulo_pagina = 'Salida de Material';
$pagina_activa = 'salidas';

define('SALIDAS_FILE', __DIR__ . '/salidas.json');

function salidas_leer(): array {
    if (!file_exists(SALIDAS_FILE)) return [];
    $data = json_decode(file_get_contents(SALIDAS_FILE), true);
    return is_array($data) ? $data : [];
}
function salidas_guardar(array $s): void {
    file_put_contents(SALIDAS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $all      = salidas_leer();
        $cantidad = floatval($_POST['cantidad']       ?? 0);
        $precio   = floatval($_POST['precio_unitario'] ?? 0);
        $uni      = $_POST['unidad'] ?? 'kg';
        $kgSalida = $uni === 'ton' ? $cantidad * 1000 : ($uni === 'kg' ? $cantidad : 0);
        foreach (existencias_leer() as $ex) {
            if (mb_strtolower($ex['nombre']) === mb_strtolower(trim($_POST['material'] ?? '')) && $kgSalida > $ex['existencia'] + 0.0001) {
                $err = "Existencia insuficiente de {$ex['nombre']}: disponible " . number_format(max(0, $ex['existencia']), 2) . " kg.";
                header('Location: index.php?menu=salidas&opc=tabla&err=' . urlencode($err)); exit;
            }
        }
        $all[]    = [
            'id'                  => uniqid(),
            'fecha'               => date('Y-m-d H:i:s'),
            'recicladora_nombre'  => trim($_POST['recicladora_nombre']   ?? ''),
            'recicladora_contacto'=> trim($_POST['recicladora_contacto'] ?? ''),
            'material'            => trim($_POST['material']             ?? ''),
            'cantidad'            => $cantidad,
            'unidad'              => trim($_POST['unidad']               ?? 'kg'),
            'precio_unitario'     => $precio,
            'total'               => $cantidad * $precio,
            'notas'               => trim($_POST['notas']                ?? ''),
        ];
        usort($all, fn($a,$b) => strcmp($b['fecha'], $a['fecha']));
        salidas_guardar($all);
        auditar('salidas', 'crear', number_format($cantidad, 2) . " {$uni} de " . trim($_POST['material'] ?? ''));
        header('Location: index.php?menu=salidas&opc=tabla'); exit;
    }
    if ($action === 'eliminar') {
        $id  = $_POST['id'] ?? '';
        $all = array_values(array_filter(salidas_leer(), fn($s) => $s['id'] !== $id));
        salidas_guardar($all);
        auditar('salidas', 'eliminar', $id);
        header('Location: index.php?menu=salidas&opc=tabla'); exit;
    }
}

$all_salidas    = salidas_leer();
$exis_sal       = existencias_leer();
$total_salidas  = count($all_salidas);
$total_ingresos = array_sum(array_column($all_salidas, 'total'));
$total_kg       = array_sum(array_column($all_salidas, 'cantidad'));
$mes_actual     = date('Y-m');
$este_mes       = array_sum(array_column(
    array_filter($all_salidas, fn($s) => substr($s['fecha'],0,7) === $mes_actual),
    'total'
));

$buscar  = trim($_GET['buscar'] ?? '');
$salidas = $all_salidas;
if ($buscar !== '') {
    $salidas = array_filter($salidas, fn($s) =>
        stripos($s['recicladora_nombre'], $buscar) !== false ||
        stripos($s['material'], $buscar) !== false
    );
}

include 'layout_header.php';
?>
<?php if (!empty($_GET['err'])): ?>
<div style="background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13.5px;font-weight:500"><?= e($_GET['err']) ?></div>
<?php endif; ?>

<div class="section-header">
    <div class="section-title"><i class="fa-solid fa-truck-ramp-box" style="color:var(--green-mid);margin-right:8px;"></i>Salida de material</div>
    <button class="btn-primary" onclick="openModal('modal-nueva-salida')"><i class="fa-solid fa-plus"></i> Registrar salida</button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total salidas</div><div class="stat-value"><?= $total_salidas ?><i class="fa-solid fa-truck stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Cantidad vendida</div><div class="stat-value"><?= number_format($total_kg,1) ?><i class="fa-solid fa-weight-hanging stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Ingresos totales</div><div class="stat-value" style="font-size:20px;">$<?= number_format($total_ingresos,2) ?><i class="fa-solid fa-dollar-sign stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Ingresos este mes</div><div class="stat-value" style="font-size:20px;">$<?= number_format($este_mes,2) ?><i class="fa-solid fa-calendar stat-icon"></i></div></div>
</div>

<form method="GET" action="index.php" style="margin-bottom:16px;display:flex;gap:10px;">
    <input type="hidden" name="menu" value="salidas"><input type="hidden" name="opc" value="tabla">
    <div class="search-bar" style="width:320px;"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="buscar" placeholder="Buscar recicladora o material…" value="<?= e($buscar) ?>"></div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-search"></i> Buscar</button>
    <?php if ($buscar): ?><a href="index.php?menu=salidas&opc=tabla" class="btn-cancel" style="padding:9px 18px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;">Limpiar</a><?php endif; ?>
</form>

<div class="table-card">
    <table class="data-table">
        <thead><tr><th>Fecha</th><th>Recicladora</th><th>Contacto</th><th>Material</th><th>Cantidad</th><th>Precio/u</th><th>Total</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($salidas)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-gray);padding:32px;">No hay salidas registradas.</td></tr>
        <?php else: foreach ($salidas as $s): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                <td style="font-weight:500;"><?= e($s['recicladora_nombre']) ?></td>
                <td><?= e($s['recicladora_contacto'] ?: '—') ?></td>
                <td><?= e($s['material']) ?></td>
                <td><strong><?= number_format($s['cantidad'],2) ?></strong> <?= e($s['unidad']) ?></td>
                <td>$<?= number_format($s['precio_unitario'],2) ?></td>
                <td style="font-weight:600;color:var(--green-dark);">$<?= number_format($s['total'],2) ?></td>
                <td>
                    <form method="POST" action="index.php?menu=salidas&opc=tabla" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?')">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                        <button type="submit" class="btn-icon danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal-nueva-salida">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa-solid fa-truck-ramp-box" style="color:var(--green-mid);margin-right:8px;"></i>Registrar salida de material</h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-salida')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="index.php?menu=salidas&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Empresa recicladora *</label><input type="text" name="recicladora_nombre" required placeholder="Ej. Recicla S.A. de C.V."></div>
                <div class="form-row"><label>Contacto (opcional)</label><input type="text" name="recicladora_contacto" placeholder="Teléfono o correo"></div>
                <div class="form-row">
                    <label>Material *</label>
                    <select name="material" id="sal_m" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach (materiales_leer() as $mt): $dx = $exis_sal[$mt['id_material']]['existencia'] ?? 0; ?>
                        <option value="<?= e($mt['nombre']) ?>" data-stock="<?= round($dx, 2) ?>"><?= e($mt['nombre']) ?> — <?= number_format(max(0, $dx), 2) ?> kg disp.</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row"><label>Cantidad *</label><input type="number" id="sal_c" name="cantidad" step="0.01" min="0.01" required placeholder="0.00" oninput="calcTotal()"></div>
                    <div class="form-row"><label>Unidad</label><select name="unidad"><option value="kg">kg</option><option value="ton">ton</option><option value="pza">pza</option></select></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row"><label>Precio por unidad ($) *</label><input type="number" id="sal_p" name="precio_unitario" step="0.01" min="0" required placeholder="0.00" oninput="calcTotal()"></div>
                    <div class="form-row"><label>Total estimado</label><input type="text" id="sal_t" readonly placeholder="$0.00" style="background:#f9fafb;color:var(--green-dark);font-weight:600;"></div>
                </div>
                <div class="form-row"><label>Notas</label><textarea name="notas" rows="2" placeholder="Observaciones…"></textarea></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-nueva-salida')">Cancelar</button>
                <button type="submit" class="btn-accept"><i class="fa-solid fa-check"></i> Registrar salida</button>
            </div>
        </form>
    </div>
</div>

<script>
function ajustarMax() {
    const o = document.getElementById('sal_m').selectedOptions[0], u = document.querySelector('[name="unidad"]').value, c = document.getElementById('sal_c');
    if (o && o.dataset.stock !== undefined && o.value && u !== 'pza') c.max = u === 'ton' ? (o.dataset.stock / 1000).toFixed(3) : o.dataset.stock;
    else c.removeAttribute('max');
}
document.getElementById('sal_m').addEventListener('change', ajustarMax);
document.querySelector('[name="unidad"]').addEventListener('change', ajustarMax);
function calcTotal() {
    const c = parseFloat(document.getElementById('sal_c').value) || 0;
    const p = parseFloat(document.getElementById('sal_p').value) || 0;
    document.getElementById('sal_t').value = '$' + (c * p).toFixed(2);
}
</script>

<?php include 'layout_footer.php'; ?>