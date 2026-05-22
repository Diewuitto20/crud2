<?php
require_once __DIR__ . '/data.php';

/* ── Acciones ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre = $_POST['nombre_sel'] === 'Otro'
            ? trim($_POST['nombre_otro'] ?? '')
            : trim($_POST['nombre_sel'] ?? '');
        $categoria = $_POST['categoria'] === 'Otro'
            ? trim($_POST['categoria_otro'] ?? '')
            : trim($_POST['categoria'] ?? '');
        material_crear(
            $nombre,
            $categoria,
            trim($_POST['unidad']    ?? 'kg'),
            (float) ($_POST['precio_kg'] ?? 0),
            (float) ($_POST['stock']     ?? 0),
            (float) ($_POST['stock_min'] ?? 0)
        );
    } elseif ($action === 'editar') {
        $nombre = $_POST['nombre_sel'] === 'Otro'
            ? trim($_POST['nombre_otro'] ?? '')
            : trim($_POST['nombre_sel'] ?? '');
        $categoria = $_POST['categoria'] === 'Otro'
            ? trim($_POST['categoria_otro'] ?? '')
            : trim($_POST['categoria'] ?? '');
        material_editar(
            trim($_POST['id']        ?? ''),
            $nombre,
            $categoria,
            trim($_POST['unidad']    ?? 'kg'),
            (float) ($_POST['precio_kg'] ?? 0),
            (float) ($_POST['stock']     ?? 0),
            (float) ($_POST['stock_min'] ?? 0)
        );
    } elseif ($action === 'eliminar') {
        material_eliminar($_POST['id'] ?? '');
    }

    header('Location: index.php?menu=material&opc=tabla');
    exit;
}

/* ── Datos ── */
$materiales = materiales_leer();

$por_categoria = [];
foreach ($materiales as $m) {
    $cat = $m['categoria'] ?: 'Sin categoría';
    $por_categoria[$cat] = ($por_categoria[$cat] ?? 0) + 1;
}

$pagina_activa = 'material';
$titulo_pagina = 'Material';
require_once __DIR__ . '/layout_header.php';

function badge_categoria(string $cat): string {
    $map = [
        'Plástico PET' => '#eff6ff;color:#1d4ed8',
        'HDPE'         => '#f0fdf4;color:#166534',
        'Cartón'       => '#fff7ed;color:#c2410c',
        'Aluminio'     => '#f8fafc;color:#475569',
        'Vidrio'       => '#eef2ff;color:#4338ca',
    ];
    $style = $map[$cat] ?? '#f3f4f6;color:#6b7280';
    [$bg, $color] = explode(';', $style);
    return '<span style="background:'.$bg.';'.$color.
           ';padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;display:inline-block">'.
           htmlspecialchars($cat, ENT_QUOTES, 'UTF-8').'</span>';
}

/* Materiales predefinidos por grupo */
$materiales_predefinidos = [
    'Metales'        => ['Aluminio', 'Cobre', 'Fierro / Acero', 'Bronce'],
    'Plásticos'      => ['PET (botellas)', 'HDPE (envases)', 'PVC'],
    'Papel y Cartón' => ['Cartón corrugado', 'Papel periódico', 'Papel bond', 'Archivo / Documentos'],
    'Electrónicos'   => ['Electrónicos (e-waste)', 'Baterías'],
];

/* Lista plana para JS */
$lista_materiales_plana = [];
foreach ($materiales_predefinidos as $items) {
    foreach ($items as $item) {
        $lista_materiales_plana[] = $item;
    }
}

/* Categorías predefinidas */
$categorias_predefinidas = ['Plástico PET', 'HDPE', 'Cartón', 'Aluminio', 'Vidrio', 'Otro'];
?>

<style>
.mat-form-card {
    background: var(--white);
    border-radius: var(--radius-card);
    border: 1px solid var(--border);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}
.mat-form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 16px;
}
.mat-cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.mat-cat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-card);
    padding: 16px 18px;
    box-shadow: var(--shadow-sm);
}
.mat-cat-name { font-size:13px; color:var(--text-gray); margin-bottom:4px; }
.mat-cat-num  { font-size:28px; font-weight:700; color:var(--text-dark); line-height:1.1; }
.mat-cat-sub  { font-size:12px; color:var(--text-gray); margin-top:2px; }

@media (max-width:700px) { .mat-form-grid { grid-template-columns:1fr; } }

.field-error {
    display: none; color: #e53e3e; font-size: 12px; margin-top: 4px;
    align-items: center; gap: 5px; animation: fadeIn .15s ease;
}
.field-error.visible { display: flex; }
.field-error i { font-size: 11px; flex-shrink: 0; }
@keyframes fadeIn {
    from { opacity:0; transform:translateY(-3px); }
    to   { opacity:1; transform:translateY(0); }
}
input.input-error, select.input-error {
    border-color: #e53e3e !important;
    box-shadow: 0 0 0 2px rgba(229,62,62,0.15) !important;
}

.confirm-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,0.45);
    backdrop-filter:blur(4px); z-index:9999;
    display:flex; align-items:center; justify-content:center;
    opacity:0; pointer-events:none; transition:opacity .2s ease;
}
.confirm-overlay.active { opacity:1; pointer-events:all; }
.confirm-box {
    background:#fff; border-radius:16px; padding:32px 28px 24px;
    max-width:380px; width:90%;
    box-shadow:0 20px 60px rgba(0,0,0,0.18);
    transform:scale(.93) translateY(8px);
    transition:transform .22s cubic-bezier(.34,1.56,.64,1);
    text-align:center;
}
.confirm-overlay.active .confirm-box { transform:scale(1) translateY(0); }
.confirm-icon { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; margin:0 auto 16px; }
.confirm-icon.danger  { background:#fff0f0; color:#e53e3e; }
.confirm-icon.warning { background:#fffbea; color:#d97706; }
.confirm-icon.info    { background:#eff6ff; color:#3b82f6; }
.confirm-title { font-size:17px; font-weight:700; color:#1a1a2e; margin-bottom:8px; }
.confirm-msg   { font-size:14px; color:#6b7280; line-height:1.5; margin-bottom:24px; }
.confirm-btns  { display:flex; gap:10px; justify-content:center; }
.confirm-btns button { flex:1; padding:10px 0; border-radius:10px; border:none; font-size:14px; font-weight:600; cursor:pointer; transition:filter .15s,transform .1s; }
.confirm-btns button:active { transform:scale(.97); }
.confirm-btn-cancel { background:#f3f4f6; color:#374151; }
.confirm-btn-cancel:hover { filter:brightness(.94); }
.confirm-btn-ok { background:#e53e3e; color:#fff; }
.confirm-btn-ok:hover { filter:brightness(1.08); }
.confirm-btn-ok.warning-btn { background:#d97706; }
.confirm-btn-ok.info-btn    { background:#3b82f6; }

#wrap_nombre_otro,
#wrap_categoria_otro {
    display: none;
    margin-top: 8px;
}
</style>

<div class="section-header">
    <div>
        <h2 class="section-title">Registro de Materiales</h2>
        <p style="font-size:13px;color:var(--text-gray);margin-top:2px">Catálogo de materiales reciclables</p>
    </div>
    <button class="btn-primary" id="btnNuevoMaterial">
        <i class="fa-solid fa-plus"></i> Nuevo Material
    </button>
</div>

<div class="mat-form-card" id="matFormCard" style="display:none">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:18px" id="matFormTitulo">Nuevo Material</h3>
    <form method="POST" action="index.php?menu=material&opc=tabla" id="formMaterial" autocomplete="off">
        <input type="hidden" name="action" id="mat_action" value="crear">
        <input type="hidden" name="id"     id="mat_id"     value="">
        <div class="mat-form-grid">

            <!-- NOMBRE DEL MATERIAL (desplegable) -->
            <div class="form-row">
                <label>Nombre del Material</label>
                <select name="nombre_sel" id="mat_nombre_sel"
                        onchange="toggleOtroNombre(this.value)" required>
                    <option value="">Seleccionar material...</option>
                    <?php foreach ($materiales_predefinidos as $grupo => $items): ?>
                    <optgroup label="<?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($items as $item): ?>
                        <option value="<?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                    <option value="Otro">Otro...</option>
                </select>
                <span class="field-error" id="err-mat-nombre-sel">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Selecciona un nombre de material.
                </span>
                <div id="wrap_nombre_otro">
                    <input type="text" name="nombre_otro" id="mat_nombre_otro"
                           placeholder="Escribe el nombre del material..."
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-mat-nombre')">
                </div>
                <span class="field-error" id="err-mat-nombre">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Solo se permiten letras.
                </span>
            </div>

            <!-- CATEGORÍA -->
            <div class="form-row">
                <label>Categoría</label>
                <select name="categoria" id="mat_categoria" onchange="toggleOtroCategoria(this.value)" required>
                    <option value="">Seleccionar categoría...</option>
                    <?php foreach ($categorias_predefinidas as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-error" id="err-mat-categoria-sel">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Selecciona una categoría.
                </span>
                <div id="wrap_categoria_otro">
                    <input type="text" name="categoria_otro" id="mat_categoria_otro"
                           placeholder="Escribe la categoría..."
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-mat-categoria')">
                </div>
                <span class="field-error" id="err-mat-categoria">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Solo se permiten letras.
                </span>
            </div>

            <!-- UNIDAD -->
            <div class="form-row">
                <label>Unidad de Medida</label>
                <select name="unidad" id="mat_unidad">
                    <option value="kg">Kilogramos (kg)</option>
                </select>
            </div>

            <!-- PRECIO -->
            <div class="form-row">
                <label>Precio por kg</label>
                <input type="number" name="precio_kg" id="mat_precio"
                       placeholder="0.00" step="0.01" min="0" required>
                <span class="field-error" id="err-mat-precio">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Ingresa un precio válido.
                </span>
            </div>

            <!-- STOCK MÁXIMO -->
            <div class="form-row">
                <label>Stock máximo</label>
                <input type="number" name="stock" id="mat_stock"
                       placeholder="0" step="0.1" min="0" required>
                <span class="field-error" id="err-mat-stock">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Ingresa un stock máximo válido.
                </span>
            </div>

            <!-- STOCK MÍNIMO -->
            <div class="form-row">
                <label>Stock Mínimo</label>
                <input type="number" name="stock_min" id="mat_stock_min"
                       placeholder="0" step="0.1" min="0" required>
                <span class="field-error" id="err-mat-stock-min">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Ingresa un stock mínimo válido.
                </span>
            </div>

        </div>
        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="button" class="btn-accept" id="btnGuardarMaterial">Guardar</button>
            <button type="button" class="btn-cancel" onclick="ocultarForm()">Cancelar</button>
        </div>
    </form>
</div>

<!-- TARJETAS POR CATEGORÍA -->
<?php if (!empty($por_categoria)): ?>
<div class="mat-cat-grid">
    <?php foreach ($por_categoria as $cat => $qty): ?>
    <div class="mat-cat-card">
        <p class="mat-cat-name"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="mat-cat-num"><?= $qty ?></p>
        <p class="mat-cat-sub">material<?= $qty !== 1 ? 'es' : '' ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- TABLA -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>MATERIAL</th>
                <th>CATEGORÍA</th>
                <th>PRECIO</th>
                <th>STOCK MÁXIMO</th>
                <th>STOCK MÍNIMO</th>
                <th style="width:90px">ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($materiales)): ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:32px;color:var(--text-gray)">
                    No hay materiales registrados
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($materiales as $m): ?>
            <tr>
                <td><strong><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><?= badge_categoria($m['categoria']) ?></td>
                <td>$<?= number_format($m['precio_kg'], 2) ?>/kg</td>
                <td><?= number_format($m['stock'], 0) ?> <?= htmlspecialchars($m['unidad'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format($m['stock_min'], 0) ?> <?= htmlspecialchars($m['unidad'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <button class="btn-icon" title="Editar"
                        onclick="editarMaterial(
                            '<?= htmlspecialchars($m['id'],        ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($m['nombre'],    ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($m['categoria'], ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($m['unidad'],    ENT_QUOTES, 'UTF-8') ?>',
                            '<?= $m['precio_kg'] ?>',
                            '<?= $m['stock'] ?>',
                            '<?= $m['stock_min'] ?>'
                        )">
                        <i class="fa-solid fa-pen-to-square" style="color:#2563eb"></i>
                    </button>
                    <button class="btn-icon danger" title="Eliminar"
                        onclick="confirmarEliminar(
                            '<?= htmlspecialchars($m['id'],     ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>'
                        )">
                        <i class="fa-regular fa-trash-can" style="color:#dc2626"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- CONFIRMACIÓN -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon" id="confirmIconWrap">
            <i class="fa-solid" id="confirmIcon"></i>
        </div>
        <div class="confirm-title" id="confirmTitle"></div>
        <div class="confirm-msg"   id="confirmMsg"></div>
        <div class="confirm-btns">
            <button class="confirm-btn-cancel" onclick="cerrarConfirm()">Cancelar</button>
            <button class="confirm-btn-ok"     id="confirmBtnOk">Aceptar</button>
        </div>
    </div>
</div>

<form method="POST" action="index.php?menu=material&opc=tabla" id="formEliminar" style="display:none">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="id"     id="eliminar_id">
</form>

<!-- MODAL DUPLICADO -->
<?php if (!empty($_SESSION['duplicado'])): ?>
<?php $dup = $_SESSION['duplicado']; ?>
<div class="confirm-overlay active" id="modalDuplicado">
    <div class="confirm-box">
        <div class="confirm-icon warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="confirm-title">Material ya registrado</div>
        <div class="confirm-msg">
            Ya existe <strong><?= htmlspecialchars($dup['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
            en el catálogo.<br><br>
            ¿Deseas actualizar su precio ($<?= number_format($dup['precio_kg'], 2) ?>/kg)
            y stock (máx: <?= $dup['stock'] ?> kg, mín: <?= $dup['stock_min'] ?> kg)
            con los nuevos valores?
        </div>
        <div class="confirm-btns">
            <form method="POST" action="index.php?menu=material&opc=tabla" style="flex:1;display:flex">
                <input type="hidden" name="action" value="cancelar_duplicado">
                <button type="submit" class="confirm-btn-cancel" style="width:100%">Cancelar</button>
            </form>
            <form method="POST" action="index.php?menu=material&opc=tabla" style="flex:1;display:flex">
                <input type="hidden" name="action" value="actualizar_existente">
                <button type="submit" class="confirm-btn-ok warning-btn" style="width:100%">Sí, actualizar</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
/* ── Listas para validación ── */
const MATERIALES_PREDEFINIDOS = <?= json_encode($lista_materiales_plana) ?>;
const CATEGORIAS_PREDEFINIDAS = <?= json_encode($categorias_predefinidas) ?>;

/* ── Mapeo nombre → categoría ── */
const NOMBRE_A_CATEGORIA = {
    'Aluminio':               'Aluminio',
    'Cobre':                  'Aluminio',
    'Fierro / Acero':         'Aluminio',
    'Bronce':                 'Aluminio',
    'PET (botellas)':         'Plástico PET',
    'HDPE (envases)':         'HDPE',
    'PVC':                    'Plástico PET',
    'Cartón corrugado':       'Cartón',
    'Papel periódico':        'Cartón',
    'Papel bond':             'Cartón',
    'Archivo / Documentos':   'Cartón',
    'Electrónicos (e-waste)': 'Otro',
    'Baterías':               'Otro',
};

/* ── Toggle: Nombre del material ── */
/* limpiar=true cuando el usuario cambia el select manualmente */
function toggleOtroNombre(val, limpiar = true) {
    const wrap  = document.getElementById('wrap_nombre_otro');
    const input = document.getElementById('mat_nombre_otro');
    if (val === 'Otro') {
        wrap.style.display = 'block';
        input.required = true;
        if (limpiar) { input.value = ''; input.focus(); }
    } else {
        wrap.style.display = 'none';
        input.required = false;
        if (limpiar) input.value = '';
    }

    /* Autocompletar categoría solo cuando el usuario elige (limpiar=true) */
    if (limpiar && val && val !== 'Otro' && NOMBRE_A_CATEGORIA[val]) {
        const selCat = document.getElementById('mat_categoria');
        selCat.value = NOMBRE_A_CATEGORIA[val];
        selCat.classList.remove('input-error');
        toggleOtroCategoria(selCat.value);
    }
}

/* ── Toggle: Categoría ── */
function toggleOtroCategoria(val) {
    const wrap  = document.getElementById('wrap_categoria_otro');
    const input = document.getElementById('mat_categoria_otro');
    if (val === 'Otro') {
        wrap.style.display = 'block';
        input.required = true;
        input.focus();
    } else {
        wrap.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

/* ── Validación letras ── */
const SOLO_LETRAS   = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\/\(\)]+$/;
const CHAR_INVALIDO = /[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\/\(\)]/g;

function validarSoloLetras(input, errorId) {
    const errorEl    = document.getElementById(errorId);
    const tieneError = CHAR_INVALIDO.test(input.value);
    if (tieneError) {
        input.value = input.value.replace(CHAR_INVALIDO, '');
        input.classList.add('input-error');
        errorEl.classList.add('visible');
        clearTimeout(input._errorTimer);
        input._errorTimer = setTimeout(() => {
            if (SOLO_LETRAS.test(input.value) || input.value === '') {
                input.classList.remove('input-error');
                errorEl.classList.remove('visible');
            }
        }, 3000);
    } else {
        input.classList.remove('input-error');
        errorEl.classList.remove('visible');
    }
}

/* ── Validación del formulario ── */
function mostrarError(elId, msgId) {
    document.getElementById(elId)?.classList.add('input-error');
    const msg = document.getElementById(msgId);
    if (msg) msg.classList.add('visible');
}
function quitarError(elId, msgId) {
    document.getElementById(elId)?.classList.remove('input-error');
    const msg = document.getElementById(msgId);
    if (msg) msg.classList.remove('visible');
}

function validarFormMaterial() {
    let valido = true;

    /* Nombre */
    const selNombre  = document.getElementById('mat_nombre_sel');
    const nombreOtro = document.getElementById('mat_nombre_otro').value.trim();

    if (!selNombre.value) {
        mostrarError('mat_nombre_sel', 'err-mat-nombre-sel');
        valido = false;
    } else {
        quitarError('mat_nombre_sel', 'err-mat-nombre-sel');
        if (selNombre.value === 'Otro') {
            if (!nombreOtro || !SOLO_LETRAS.test(nombreOtro)) {
                mostrarError('mat_nombre_otro', 'err-mat-nombre');
                valido = false;
            } else {
                quitarError('mat_nombre_otro', 'err-mat-nombre');
            }
        }
    }

    /* Categoría */
    const selCat  = document.getElementById('mat_categoria');
    const catOtro = document.getElementById('mat_categoria_otro').value.trim();

    if (!selCat.value) {
        mostrarError('mat_categoria', 'err-mat-categoria-sel');
        valido = false;
    } else {
        quitarError('mat_categoria', 'err-mat-categoria-sel');
        if (selCat.value === 'Otro') {
            if (!catOtro || !SOLO_LETRAS.test(catOtro)) {
                mostrarError('mat_categoria_otro', 'err-mat-categoria');
                valido = false;
            } else {
                quitarError('mat_categoria_otro', 'err-mat-categoria');
            }
        }
    }

    /* Precio */
    const precioEl = document.getElementById('mat_precio');
    const precio   = parseFloat(precioEl.value);
    if (precioEl.value === '' || isNaN(precio) || precio < 0) {
        mostrarError('mat_precio', 'err-mat-precio');
        valido = false;
    } else {
        quitarError('mat_precio', 'err-mat-precio');
    }

    /* Stock máximo y mínimo */
    const stockMax = document.getElementById('mat_stock');
    const stockMin = document.getElementById('mat_stock_min');
    const vMax = parseFloat(stockMax.value);
    const vMin = parseFloat(stockMin.value);

    if (stockMax.value === '' || isNaN(vMax) || vMax < 0) {
        mostrarError('mat_stock', 'err-mat-stock');
        valido = false;
    } else {
        quitarError('mat_stock', 'err-mat-stock');
    }

    if (stockMin.value === '' || isNaN(vMin) || vMin < 0) {
        mostrarError('mat_stock_min', 'err-mat-stock-min');
        valido = false;
    } else {
        quitarError('mat_stock_min', 'err-mat-stock-min');
    }

    /* Stock mínimo debe ser menor al máximo */
    if (!isNaN(vMax) && !isNaN(vMin) && stockMax.value !== '' && stockMin.value !== '' && vMin >= vMax) {
        mostrarError('mat_stock', 'err-mat-stock');
        mostrarError('mat_stock_min', 'err-mat-stock-min');
        document.getElementById('err-mat-stock-min').textContent = '⚠ El stock mínimo debe ser menor al stock máximo.';
        valido = false;
    }

    return valido;
}

/* ── Modal de confirmación ── */
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
    const cb = _confirmCallback;
    cerrarConfirm();
    if (cb) cb();
});

/* ── Eliminar ── */
function confirmarEliminar(id, nombre) {
    mostrarConfirm({
        tipo: 'danger', icono: 'fa-trash-can',
        titulo: 'Eliminar material',
        mensaje: `¿Está seguro de eliminar "${nombre}"? Esta acción no se puede deshacer.`,
        txtOk: 'Sí, eliminar',
        onOk: () => {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('formEliminar').submit();
        }
    });
}

/* ── Guardar ── */
document.getElementById('btnGuardarMaterial').addEventListener('click', function () {
    if (!validarFormMaterial()) return;

    const accion   = document.getElementById('mat_action').value;
    const selNombre = document.getElementById('mat_nombre_sel');
    const nombre   = selNombre.value === 'Otro'
        ? document.getElementById('mat_nombre_otro').value.trim()
        : selNombre.value;

    mostrarConfirm({
        tipo: 'info', icono: 'fa-floppy-disk',
        titulo: accion === 'editar' ? 'Guardar cambios' : 'Registrar material',
        mensaje: accion === 'editar'
            ? `¿Está seguro de guardar los cambios en "${nombre}"?`
            : `¿Está seguro de registrar "${nombre}" como nuevo material?`,
        txtOk: accion === 'editar' ? 'Sí, guardar' : 'Sí, registrar',
        onOk: () => {
            const form = document.getElementById('formMaterial');
            form.onsubmit = null;
            form.submit();
        }
    });
});

/* ── Nuevo Material ── */
document.getElementById('btnNuevoMaterial').addEventListener('click', function () {
    document.getElementById('matFormTitulo').textContent = 'Nuevo Material';
    document.getElementById('mat_action').value = 'crear';
    document.getElementById('mat_id').value     = '';
    document.getElementById('formMaterial').reset();
    document.getElementById('wrap_nombre_otro').style.display   = 'none';
    document.getElementById('wrap_categoria_otro').style.display = 'none';
    limpiarErrores();
    mostrarForm();
});

function mostrarForm() {
    const card = document.getElementById('matFormCard');
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function ocultarForm() {
    document.getElementById('matFormCard').style.display = 'none';
    limpiarErrores();
}

function limpiarErrores() {
    ['mat_nombre_sel','mat_nombre_otro','mat_categoria','mat_categoria_otro',
     'mat_precio','mat_stock','mat_stock_min'].forEach(id => {
        document.getElementById(id)?.classList.remove('input-error');
    });
    ['err-mat-nombre-sel','err-mat-nombre','err-mat-categoria-sel','err-mat-categoria',
     'err-mat-precio','err-mat-stock','err-mat-stock-min'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('visible'); }
    });
    /* Restaurar texto original del error de stock mínimo */
    const errStockMin = document.getElementById('err-mat-stock-min');
    if (errStockMin) errStockMin.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Ingresa un stock mínimo válido.';
}

/* ── Editar Material ── */
function editarMaterial(id, nombre, categoria, unidad, precio, stock, stock_min) {
    document.getElementById('matFormTitulo').textContent  = 'Editar Material';
    document.getElementById('mat_action').value  = 'editar';
    document.getElementById('mat_id').value      = id;
    document.getElementById('mat_unidad').value  = unidad;
    document.getElementById('mat_precio').value  = precio;
    document.getElementById('mat_stock').value   = stock;
    document.getElementById('mat_stock_min').value = stock_min;

    /* Nombre */
    const selNombre = document.getElementById('mat_nombre_sel');
    if (MATERIALES_PREDEFINIDOS.includes(nombre)) {
        selNombre.value = nombre;
        toggleOtroNombre(nombre, false); /* false = no limpiar, solo mostrar/ocultar */
    } else {
        selNombre.value = 'Otro';
        toggleOtroNombre('Otro', false);
        document.getElementById('mat_nombre_otro').value = nombre;
    }

    /* Categoría */
    const selCat = document.getElementById('mat_categoria');
    if (CATEGORIAS_PREDEFINIDAS.includes(categoria)) {
        selCat.value = categoria;
        toggleOtroCategoria(categoria);  /* aquí sí puede limpiar, categoria_otro no tiene valor previo */
    } else {
        selCat.value = 'Otro';
        toggleOtroCategoria('Otro');
        document.getElementById('mat_categoria_otro').value = categoria;
    }

    limpiarErrores();
    mostrarForm();
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>