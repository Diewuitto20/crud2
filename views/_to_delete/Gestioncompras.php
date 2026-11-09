<?php


require_once __DIR__ . '/data.php';

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

/* ── Mensajes de estado ── */
$msg_ok  = '';
$msg_err = '';

/* ── CREAR ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    if ($accion === 'crear_compra') {
        $fecha   = trim($_POST['fecha']           ?? '');
        $kilos   = trim($_POST['kilos']           ?? '');
        $clasif  = trim($_POST['clasificacion']   ?? '');
        $empresa = trim($_POST['nombre_empresa']  ?? '');
        $dinero  = trim($_POST['dinero_recibido'] ?? '');

        if (!$fecha || !$kilos || !$clasif || !$empresa || !$dinero) {
            $msg_err = 'Completa todos los campos obligatorios.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO gestion_compras (fecha, kilos, clasificacion, nombre_empresa, dinero_recibido)
                 VALUES (:fecha, :kilos, :clasif, :empresa, :dinero)"
            );
            $stmt->execute([
                ':fecha'   => $fecha,
                ':kilos'   => $kilos,
                ':clasif'  => $clasif,
                ':empresa' => $empresa,
                ':dinero'  => $dinero,
            ]);
            $msg_ok = 'Compra registrada correctamente.';
        }
    }

    /* ── ELIMINAR ── */
    if ($accion === 'eliminar_compra') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM gestion_compras WHERE id_gestion_venta = :id")->execute([':id' => $id]);
            $msg_ok = 'Registro eliminado.';
        }
    }
}

/* ── Leer registros ── */
$compras = $pdo->query(
    "SELECT id_gestion_venta, fecha, kilos, clasificacion, nombre_empresa, dinero_recibido
     FROM gestion_compras ORDER BY fecha DESC, id_gestion_venta DESC"
)->fetchAll();

/* ── Totales ── */
$total_registros = count($compras);
$total_kilos     = array_sum(array_column($compras, 'kilos'));
$total_dinero    = array_sum(array_column($compras, 'dinero_recibido'));

$pagina_activa = 'gestion_compras';
$titulo_pagina = 'Gestión de Compras';
require_once __DIR__ . '/layout_header.php';
?>

<style>
/* ── confirmación personalizado ── */
.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
}
.confirm-overlay.active {
    opacity: 1;
    pointer-events: all;
}
.confirm-box {
    background: #fff;
    border-radius: 16px;
    padding: 32px 28px 24px;
    max-width: 380px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    transform: scale(.93) translateY(8px);
    transition: transform .22s cubic-bezier(.34,1.56,.64,1);
    text-align: center;
}
.confirm-overlay.active .confirm-box {
    transform: scale(1) translateY(0);
}
.confirm-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 16px;
}
.confirm-icon.danger  { background: #fff0f0; color: #e53e3e; }
.confirm-icon.warning { background: #fffbea; color: #d97706; }
.confirm-icon.info    { background: #eff6ff; color: #3b82f6; }
.confirm-title {
    font-size: 17px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 8px;
}
.confirm-msg {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 24px;
}
.confirm-btns {
    display: flex;
    gap: 10px;
    justify-content: center;
}
.confirm-btns button {
    flex: 1;
    padding: 10px 0;
    border-radius: 10px;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: filter .15s, transform .1s;
}
.confirm-btns button:active { transform: scale(.97); }
.confirm-btn-cancel { background: #f3f4f6; color: #374151; }
.confirm-btn-cancel:hover { filter: brightness(.94); }
.confirm-btn-ok { background: #e53e3e; color: #fff; }
.confirm-btn-ok:hover { filter: brightness(1.08); }
.confirm-btn-ok.warning-btn { background: #d97706; }
.confirm-btn-ok.info-btn    { background: #3b82f6; }
</style>

        <!-- Alertas -->
        <?php if ($msg_ok): ?>
        <div style="background:#f0fdf4;color:#15803d;border:1px solid #86efac;border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-circle-check"></i> <?= e($msg_ok) ?>
        </div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($msg_err) ?>
        </div>
        <?php endif; ?>

        <div class="section-header">
            <h2 class="section-title">Gestión de Compras</h2>
            <button class="btn-primary" onclick="openModal('modalCompra')">
                <i class="fa-solid fa-plus"></i> Nueva compra
            </button>
        </div>

        <!-- Tarjetas resumen -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);max-width:520px;margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
                <div class="stat-label">Registros</div>
                <div class="stat-value"><?= $total_registros ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-weight-hanging"></i></div>
                <div class="stat-label">Total kg</div>
                <div class="stat-value"><?= number_format($total_kilos, 1) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="stat-label">Total $</div>
                <div class="stat-value">$<?= number_format($total_dinero, 0) ?></div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Fecha</th>
                        <th>Empresa</th>
                        <th>Clasificación</th>
                        <th>Kilos</th>
                        <th>Dinero recibido</th>
                        <th style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--text-gray);padding:24px;">
                            No hay compras registradas.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($compras as $c): ?>
                    <tr>
                        <td><?= e((string)$c['id_gestion_venta']) ?></td>
                        <td><?= e($c['fecha']) ?></td>
                        <td><strong><?= e($c['nombre_empresa']) ?></strong></td>
                        <td><span class="badge badge-green"><?= e($c['clasificacion']) ?></span></td>
                        <td><?= number_format((float)$c['kilos'], 2) ?> kg</td>
                        <td>$<?= number_format((float)$c['dinero_recibido'], 2) ?></td>
                        <td>
                            <button class="btn-icon danger" title="Eliminar"
                                onclick="confirmarEliminar(<?= (int)$c['id_gestion_venta'] ?>, '<?= e($c['nombre_empresa']) ?>')">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<!-- Nueva compra -->
<div class="modal-overlay" id="modalCompra">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Nueva compra</h3>
            <button class="modal-close" onclick="closeModal('modalCompra')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=gestion_compras&opc=tabla">
            <input type="hidden" name="action" value="crear_compra">
            <div class="modal-body">
                <div class="form-row">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-row">
                    <label>Nombre de empresa *</label>
                    <input type="text" name="nombre_empresa" placeholder="Empresa proveedora" required>
                </div>
                <div class="form-row">
                    <label>Clasificación *</label>
                    <select name="clasificacion" required>
                        <option value="">— Selecciona —</option>
                        <option value="PET">PET</option>
                        <option value="HDPE">HDPE</option>
                        <option value="Carton">Cartón</option>
                        <option value="Aluminio">Aluminio</option>
                        <option value="Vidrio">Vidrio</option>
                        <option value="Cobre">Cobre</option>
                        <option value="Fierro">Fierro</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Kilos *</label>
                    <input type="number" name="kilos" placeholder="0.00" min="0.01" step="0.01" required>
                </div>
                <div class="form-row">
                    <label>Dinero recibido ($) *</label>
                    <input type="number" name="dinero_recibido" placeholder="0.00" min="0" step="0.01" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalCompra')">Cancelar</button>
                <button type="submit" class="btn-accept">Registrar compra</button>
            </div>
        </form>
    </div>
</div>

<!--  CONFIRMACIÓN PERSONALIZADO -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon danger" id="confirmIconWrap">
            <i class="fa-solid fa-trash-can" id="confirmIcon"></i>
        </div>
        <div class="confirm-title" id="confirmTitle">Eliminar registro</div>
        <div class="confirm-msg"   id="confirmMsg"></div>
        <div class="confirm-btns">
            <button class="confirm-btn-cancel" onclick="cerrarConfirm()">Cancelar</button>
            <button class="confirm-btn-ok"     id="confirmBtnOk">Sí, eliminar</button>
        </div>
    </div>
</div>

<!-- Formulario oculto para eliminar -->
<form method="POST" action="index.php?menu=gestion_compras&opc=tabla" id="formEliminar" style="display:none">
    <input type="hidden" name="action" value="eliminar_compra">
    <input type="hidden" name="id"     id="eliminar_id">
</form>

<script>
/* ── confirmación ── */
let _confirmCallback = null;

function mostrarConfirm({ tipo = 'danger', icono, titulo, mensaje, txtOk = 'Aceptar', onOk }) {
    const overlay  = document.getElementById('confirmOverlay');
    const iconWrap = document.getElementById('confirmIconWrap');
    const icon     = document.getElementById('confirmIcon');
    const btnOk    = document.getElementById('confirmBtnOk');

    iconWrap.className = `confirm-icon ${tipo}`;
    icon.className     = `fa-solid ${icono}`;
    btnOk.className    = `confirm-btn-ok ${tipo === 'warning' ? 'warning-btn' : tipo === 'info' ? 'info-btn' : ''}`;

    document.getElementById('confirmTitle').textContent = titulo;
    document.getElementById('confirmMsg').textContent   = mensaje;
    btnOk.textContent = txtOk;

    _confirmCallback = onOk;
    overlay.classList.add('active');
    overlay.onclick = e => { if (e.target === overlay) cerrarConfirm(); };
}

function cerrarConfirm() {
    document.getElementById('confirmOverlay').classList.remove('active');
    _confirmCallback = null;
}

document.getElementById('confirmBtnOk').addEventListener('click', () => {
    cerrarConfirm();
    if (_confirmCallback) _confirmCallback();
});

/* ── Confirmar eliminar ── */
function confirmarEliminar(id, empresa) {
    mostrarConfirm({
        tipo:    'danger',
        icono:   'fa-trash-can',
        titulo:  'Eliminar registro',
        mensaje: `¿Está seguro de eliminar el registro de "${empresa}"? Esta acción no se puede deshacer.`,
        txtOk:   'Sí, eliminar',
        onOk: () => {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('formEliminar').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>