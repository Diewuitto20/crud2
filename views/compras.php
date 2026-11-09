<?php
require_once __DIR__ . '/data.php';

/* ── Acciones ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $err = '';
    $porNombre = [];
    foreach (materiales_leer() as $mt) $porNombre[mb_strtolower($mt['nombre'])] = $mt;
    $exis = existencias_leer();

    if ($action === 'crear') {
        $mat    = trim($_POST['material'] ?? '');
        $kg     = (float) ($_POST['cantidad']  ?? 0);
        $precio = (float) ($_POST['precio_kg'] ?? 0);
        $mt     = $porNombre[mb_strtolower($mat)] ?? null;
        if (!$mt || $kg <= 0 || $precio <= 0) {
            $err = 'Elige un material e indica kilos y precio válidos.';
        } else {
            $libre = (float)$mt['stock_max'] - ($exis[$mt['id_material']]['existencia'] ?? 0);
            if ($kg > $libre + 0.0001) {
                $err = "Se excede el stock máximo de {$mt['nombre']} ({$mt['stock_max']} kg). Capacidad disponible: " . number_format(max(0, $libre), 2) . " kg.";
            } else {
                compra_crear($mt['nombre'], '', $kg, $kg * $precio);
                auditar('compras', 'crear', number_format($kg, 2) . " kg de {$mt['nombre']} a $" . number_format($precio, 2) . "/kg");
            }
        }
    } elseif ($action === 'eliminar' && !es_dueno()) {
        $err = 'Solo el dueño puede eliminar compras.';
    } elseif ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $c  = get_pdo()->prepare("SELECT clasificacion, kilos FROM gestion_compras WHERE id_gestion_venta = ?");
        $c->execute([$id]); $c = $c->fetch();
        if ($c) {
            $mt = $porNombre[mb_strtolower(trim($c['clasificacion']))] ?? null;
            if ($mt && ($exis[$mt['id_material']]['existencia'] ?? 0) - (float)$c['kilos'] < -0.0001) {
                $err = "No se puede eliminar: la existencia de {$mt['nombre']} quedaría en negativo (ese material ya se vendió o salió).";
            } else {
                compra_eliminar($id);
                auditar('compras', 'eliminar', number_format((float)$c['kilos'], 2) . " kg de {$c['clasificacion']}");
            }
        }
    }
    header('Location: index.php?menu=compras&opc=tabla' . ($err ? '&err=' . urlencode($err) : ''));
    exit;
}

/* ── Datos ── */
$compras         = compras_leer();
$catalogo        = materiales_leer();
$existencias     = existencias_leer();
$total_monto     = array_sum(array_column($compras, 'total'));
$total_transacc  = count($compras);

$pagina_activa = 'compras';
$titulo_pagina = 'Registro de compras';
require_once __DIR__ . '/layout_header.php';

/*  etiqueta de color por material */
function badge_material(string $m): string {
    $map = [
        'Plástico PET' => '#ede9fe;color:#6d28d9',
        'HDPE'         => '#dbeafe;color:#1d4ed8',
        'Cartón'       => '#fef9c3;color:#854d0e',
        'Aluminio'     => '#f3f4f6;color:#374151',
        'Vidrio'       => '#d1fae5;color:#065f46',
        'Otro'         => '#f3f4f6;color:#6b7280',
    ];
    $style = $map[$m] ?? '#f3f4f6;color:#6b7280';
    [$bg, $color] = explode(';', $style);
    return '<span style="background:'.$bg.';'.$color.';padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;display:inline-block">'
           . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

        <?php if (!empty($_GET['err'])): ?>
        <div style="background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13.5px;font-weight:500"><?= e($_GET['err']) ?></div>
        <?php endif; ?>

        <div class="section-header">
            <div>
                <h2 class="section-title">Registro de Compras</h2>
                <p style="font-size:13px;color:var(--text-gray);margin-top:2px">Compras a proveedores de material</p>
            </div>
            <button class="btn-primary" onclick="openModal('modalCompra')">
                <i class="fa-solid fa-plus"></i> Nueva Compra
            </button>
        </div>

        <!-- RESUMEN -->
        <div class="compras-resumen">
            <div class="resumen-card resumen-card--purple">
                <p class="resumen-label">Total Compras</p>
                <p class="resumen-valor">$<?= number_format($total_monto, 2) ?></p>
            </div>
            <div class="resumen-card resumen-card--blue">
                <p class="resumen-label">Transacciones</p>
                <p class="resumen-valor"><?= $total_transacc ?></p>
            </div>
        </div>

        <!-- EXISTENCIA -->
        <p class="exist-titulo">Existencia actual (compras − ventas)</p>
        <div class="exist-grid">
            <?php foreach ($catalogo as $mt):
                $ex  = $existencias[$mt['id_material']]['existencia'] ?? 0;
                $col = $ex < (float)$mt['stock_min'] ? '#dc2626' : ($ex > (float)$mt['stock_max'] ? '#d97706' : '#16a34a'); ?>
            <div class="exist-card">
                <span class="exist-nombre"><?= e($mt['nombre']) ?></span>
                <strong style="color:<?= $col ?>"><?= number_format($ex, 2) ?> kg</strong>
                <small>mín <?= number_format($mt['stock_min']) ?> · máx <?= number_format($mt['stock_max']) ?></small>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- TABLA -->
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>FECHA</th>
                        <th>MATERIAL</th>
                        <th>CANTIDAD</th>
                        <th>PRECIO/KG</th>
                        <th>TOTAL</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:32px;color:var(--text-gray)">
                            No hay compras registradas
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($compras as $cp):
                        $fecha_fmt = date('d M Y, h:i a', strtotime($cp['fecha']));
                    ?>
                    <tr>
                        <td style="font-size:13px;color:var(--text-gray)"><?= e($fecha_fmt) ?></td>
                        <td><?= badge_material($cp['material']) ?></td>
                        <td><?= number_format($cp['cantidad'], 0) ?> kg</td>
                        <td>$<?= number_format($cp['precio_kg'], 2) ?></td>
                        <td><strong>$<?= number_format($cp['total'], 2) ?></strong></td>
                        <td>
                            <?php if (es_dueno()): ?>
                            <form method="POST" action="index.php?menu=compras&opc=tabla" style="margin:0;display:inline">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id" value="<?= e($cp['id']) ?>">
                                <button type="submit" class="btn-icon danger" title="Eliminar"
                                        onclick="return confirm('¿Eliminar esta compra?')">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<!-- ── NUEVA COMPRA ── -->
<div class="modal-overlay" id="modalCompra">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Registrar Compra</h3>
            <button class="modal-close" onclick="closeModal('modalCompra')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=compras&opc=tabla" id="formCompra">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-row" style="grid-column:1/-1">
                        <label>Material</label>
                        <select name="material" id="inp_material" required onchange="selMat(this)">
                            <option value="">Seleccionar material...</option>
                            <?php foreach ($catalogo as $mt): ?>
                            <?php $libre = (float)$mt['stock_max'] - ($existencias[$mt['id_material']]['existencia'] ?? 0); ?>
                            <option value="<?= e($mt['nombre']) ?>" data-precio="<?= (float)$mt['precio_kg'] ?>" data-libre="<?= round($libre, 2) ?>"<?= $libre <= 0 ? ' disabled' : '' ?>><?= e($mt['nombre']) ?> — cabe <?= number_format(max(0, $libre), 2) ?> kg</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Cantidad (kg)</label>
                        <input type="number" name="cantidad" id="inp_cantidad" placeholder="0"
                               min="0.1" step="0.1" required oninput="calcTotal()">
                    </div>
                    <div class="form-row">
                        <label>Precio por kg</label>
                        <input type="number" name="precio_kg" id="inp_precio" placeholder="0.00"
                               min="0.01" step="0.01" required oninput="calcTotal()">
                    </div>
                </div>
                <div class="compra-total-preview">
                    Total: <strong id="preview_total">$0.00</strong>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalCompra')">Cancelar</button>
                <button type="submit" class="btn-accept">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
.compras-resumen {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.exist-titulo { font-size:13px; font-weight:600; color:var(--text-gray); margin:4px 0 10px; }
.exist-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:12px; margin-bottom:20px; }
.exist-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:4px; box-shadow:var(--shadow-sm); }
.exist-nombre { font-size:13px; color:var(--text-gray); font-weight:500; }
.exist-card strong { font-size:20px; }
.exist-card small { font-size:11.5px; color:var(--text-gray); }
.resumen-card {
    border-radius: var(--radius-card);
    padding: 20px 24px;
    border: 1px solid var(--border);
}
.resumen-card--purple { background: #faf5ff; border-color: #e9d5ff; }
.resumen-card--blue   { background: #eff6ff; border-color: #bfdbfe; }
.resumen-label {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
}
.resumen-card--purple .resumen-label { color: #7c3aed; }
.resumen-card--blue   .resumen-label { color: #1d4ed8; }
.resumen-valor {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
}
.resumen-card--purple .resumen-valor { color: #6d28d9; }
.resumen-card--blue   .resumen-valor { color: #1d4ed8; }

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.compra-total-preview {
    margin-top: 8px;
    font-size: 15px;
    color: var(--text-dark);
    border-top: 1px solid var(--border);
    padding-top: 12px;
}
.compra-total-preview strong {
    color: var(--green-dark);
    font-size: 18px;
}

@media (max-width: 500px) {
    .form-grid-2 { grid-template-columns: 1fr; }
}
</style>

<script>
function selMat(sel) {
    const p = sel.selectedOptions[0]?.dataset.precio;
    if (p) document.getElementById('inp_precio').value = p;
    const libre = sel.selectedOptions[0]?.dataset.libre, c = document.getElementById('inp_cantidad');
    if (libre !== undefined) { c.max = libre; c.placeholder = 'máx ' + libre; } else { c.removeAttribute('max'); c.placeholder = '0'; }
    calcTotal();
}
function calcTotal() {
    const cant  = parseFloat(document.getElementById('inp_cantidad').value) || 0;
    const precio = parseFloat(document.getElementById('inp_precio').value)  || 0;
    document.getElementById('preview_total').textContent =
        '$' + (cant * precio).toFixed(2);
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>