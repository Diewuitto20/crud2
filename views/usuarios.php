<?php
require_once __DIR__ . '/data.php';

/* ── Conexión ── */
$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

/* ── Validación: solo letras ── */
function soloLetras(string $valor): bool {
    return (bool) preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/u', trim($valor));
}

/* ── Acciones ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre = trim($_POST['nombre']     ?? '');
        $ap     = trim($_POST['ap_paterno'] ?? '');
        $am     = trim($_POST['ap_materno'] ?? '');
        $correo = trim($_POST['correo']     ?? '');
        $pass   = $_POST['password']        ?? '';

        if (!soloLetras($nombre)) die('Error: el nombre solo puede contener letras.');
        if (!soloLetras($ap))     die('Error: el apellido paterno solo puede contener letras.');
        if ($am !== '' && !soloLetras($am)) die('Error: el apellido materno solo puede contener letras.');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) die('Error: correo electrónico inválido.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = :correo");
        $check->execute([':correo' => $correo]);
        if ($check->fetchColumn() > 0) die('Error: ya existe un usuario con ese correo.');

        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, correo, contrasena, activo)
                               VALUES (:nombre, :ap, :am, :correo, :pass, 1)");
        $stmt->execute([':nombre' => $nombre, ':ap' => $ap, ':am' => $am, ':correo' => $correo,
                        ':pass' => $pass]);

    } elseif ($action === 'editar') {
        $id     = (int) ($_POST['id']        ?? 0);
        $nombre = trim($_POST['nombre']      ?? '');
        $ap     = trim($_POST['ap_paterno']  ?? '');
        $am     = trim($_POST['ap_materno']  ?? '');
        $correo = trim($_POST['correo']      ?? '');
        $pass   = $_POST['password']         ?? '';

        if (!soloLetras($nombre)) die('Error: el nombre solo puede contener letras.');
        if (!soloLetras($ap))     die('Error: el apellido paterno solo puede contener letras.');
        if ($am !== '' && !soloLetras($am)) die('Error: el apellido materno solo puede contener letras.');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) die('Error: correo electrónico inválido.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = :correo AND id_usuario != :id");
        $check->execute([':correo' => $correo, ':id' => $id]);
        if ($check->fetchColumn() > 0) die('Error: ya existe otro usuario con ese correo.');

        if (!empty($pass)) {
            $pdo->prepare("UPDATE usuarios SET nombre=:nombre, apellido_paterno=:ap,
                           apellido_materno=:am, correo=:correo, contrasena=:pass WHERE id_usuario=:id")
                ->execute([':nombre'=>$nombre,':ap'=>$ap,':am'=>$am,':correo'=>$correo,
                           ':pass'=>$pass,':id'=>$id]);
        } else {
            $pdo->prepare("UPDATE usuarios SET nombre=:nombre, apellido_paterno=:ap,
                           apellido_materno=:am, correo=:correo WHERE id_usuario=:id")
                ->execute([':nombre'=>$nombre,':ap'=>$ap,':am'=>$am,':correo'=>$correo,':id'=>$id]);
        }

    } elseif ($action === 'toggle_activo') {
        $id     = (int) ($_POST['id']     ?? 0);
        $activo = (int) ($_POST['activo'] ?? 1);
        $nuevo  = $activo ? 0 : 1;
        $pdo->prepare("UPDATE usuarios SET activo=:activo WHERE id_usuario=:id")
            ->execute([':activo' => $nuevo, ':id' => $id]);

    } elseif ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=:id")->execute([':id' => $id]);
    }

    header('Location: index.php?menu=usuarios&opc=tabla');
    exit;
}

/* ── Obtener usuarios ── */
$usuarios  = $pdo->query("SELECT id_usuario, nombre, apellido_paterno, apellido_materno, correo, activo FROM usuarios ORDER BY id_usuario")->fetchAll();
$total     = count($usuarios);
$activos   = count(array_filter($usuarios, fn($u) => $u['activo']));
$inactivos = $total - $activos;

$pagina_activa = 'usuarios';
$titulo_pagina = 'Usuarios';
require_once __DIR__ . '/layout_header.php';
?>

<style>
.field-error {
    display: none; color: #e53e3e; font-size: 12px; margin-top: 4px;
    align-items: center; gap: 5px; animation: fadeIn .15s ease;
}
.field-error.visible { display: flex; }
.field-error i { font-size: 11px; flex-shrink: 0; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-3px); }
    to   { opacity: 1; transform: translateY(0); }
}
input.input-error {
    border-color: #e53e3e !important;
    box-shadow: 0 0 0 2px rgba(229,62,62,0.15) !important;
}
.confirm-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    backdrop-filter: blur(4px); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity .2s ease;
}
.confirm-overlay.active { opacity: 1; pointer-events: all; }
.confirm-box {
    background: #fff; border-radius: 16px; padding: 32px 28px 24px;
    max-width: 380px; width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    transform: scale(.93) translateY(8px);
    transition: transform .22s cubic-bezier(.34,1.56,.64,1);
    text-align: center;
}
.confirm-overlay.active .confirm-box { transform: scale(1) translateY(0); }
.confirm-icon {
    width: 56px; height: 56px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; margin: 0 auto 16px;
}
.confirm-icon.danger  { background: #fff0f0; color: #e53e3e; }
.confirm-icon.warning { background: #fffbea; color: #d97706; }
.confirm-icon.info    { background: #eff6ff; color: #3b82f6; }
.confirm-title { font-size: 17px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
.confirm-msg   { font-size: 14px; color: #6b7280; line-height: 1.5; margin-bottom: 24px; }
.confirm-btns  { display: flex; gap: 10px; justify-content: center; }
.confirm-btns button {
    flex: 1; padding: 10px 0; border-radius: 10px; border: none;
    font-size: 14px; font-weight: 600; cursor: pointer;
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

<div class="section-header">
    <h2 class="section-title">Usuarios</h2>
    <button class="btn-primary" onclick="openModal('modalUsuario')">
        <i class="fa-solid fa-plus"></i> Nuevo usuario
    </button>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); max-width:480px; margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Total</div>
        <div class="stat-value"><?= $total ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-label">Activos</div>
        <div class="stat-value"><?= $activos ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-label">Inactivos</div>
        <div class="stat-value"><?= $inactivos ?></div>
    </div>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                <th>Nombre</th>
                <th>Apellidos</th>
                <th>Correo electrónico</th>
                <th>Estado</th>
                <th style="width:110px; text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
            <tr>
                <td colspan="6" style="text-align:center;color:var(--text-gray);padding:24px">
                    No hay usuarios registrados.
                </td>
            </tr>
            <?php else: ?>
           <?php $loop = 1; foreach ($usuarios as $u): ?>
            <tr>
                <td><?= $loop++ ?></td>
                <td><strong><?= e($u['nombre']) ?></strong></td>
                <td><?= e($u['apellido_paterno']) ?> <?= e($u['apellido_materno'] ?? '') ?></td>
                <td><a href="mailto:<?= e($u['correo']) ?>" class="email-link"><?= e($u['correo']) ?></a></td>
                <td>
                    <?php if ($u['activo']): ?>
                        <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size:8px;margin-right:5px"></i>Activo</span>
                    <?php else: ?>
                        <span class="badge badge-gray"><i class="fa-solid fa-circle" style="font-size:8px;margin-right:5px"></i>Inactivo</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap; text-align:center">
                    <button class="btn-icon" title="Editar"
                        onclick="abrirEditar(<?= $u['id_usuario'] ?>,'<?= e($u['nombre']) ?>','<?= e($u['apellido_paterno']) ?>','<?= e($u['apellido_materno'] ?? '') ?>','<?= e($u['correo']) ?>')">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="btn-icon <?= $u['activo'] ? 'danger active-toggle' : '' ?>"
                            title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"
                            onclick="confirmarToggle(<?= $u['id_usuario'] ?>,<?= (int)$u['activo'] ?>,'<?= e($u['nombre']) ?>')">
                        <i class="fa-solid <?= $u['activo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </button>
                    <button class="btn-icon danger" title="Eliminar"
                            onclick="confirmarEliminar(<?= $u['id_usuario'] ?>,'<?= e($u['nombre']) ?>')">
                        <i class="fa-regular fa-trash-can"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── NUEVO USUARIO ── -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Nuevo usuario</h3>
            <button class="modal-close" onclick="closeModal('modalUsuario')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=usuarios&opc=tabla"
              onsubmit="return validarFormulario(this)"
              autocomplete="off">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">

                <div class="form-row">
                    <label>Nombre</label>
                    <input type="text" name="nombre" placeholder="Nombre" required
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-crear-nombre')">
                    <span class="field-error" id="err-crear-nombre">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Apellido paterno</label>
                    <input type="text" name="ap_paterno" placeholder="Apellido paterno" required
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-crear-ap')">
                    <span class="field-error" id="err-crear-ap">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Apellido materno</label>
                    <input type="text" name="ap_materno" placeholder="Apellido materno"
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-crear-am')">
                    <span class="field-error" id="err-crear-am">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Correo electrónico</label>
                    <input type="text" name="correo" placeholder="correo@ejemplo.com"
                           required autocomplete="off"
                           inputmode="email"
                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                           title="Ingresa un correo electrónico válido">
                </div>

                <div class="form-row">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="Contraseña"
                           required autocomplete="new-password">
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalUsuario')">Cancelar</button>
                <button type="submit" class="btn-accept">Crear usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDITAR USUARIO ── -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Editar usuario</h3>
            <button class="modal-close" onclick="closeModal('modalEditar')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=usuarios&opc=tabla"
              id="formEditar"
              onsubmit="return validarFormulario(this)"
              autocomplete="off">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">

                <div class="form-row">
                    <label>Nombre</label>
                    <input type="text" name="nombre" id="edit_nombre" placeholder="Nombre" required
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-edit-nombre')">
                    <span class="field-error" id="err-edit-nombre">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Apellido paterno</label>
                    <input type="text" name="ap_paterno" id="edit_ap" placeholder="Apellido paterno" required
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-edit-ap')">
                    <span class="field-error" id="err-edit-ap">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Apellido materno</label>
                    <input type="text" name="ap_materno" id="edit_am" placeholder="Apellido materno"
                           autocomplete="off"
                           oninput="validarSoloLetras(this, 'err-edit-am')">
                    <span class="field-error" id="err-edit-am">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="form-row">
                    <label>Correo electrónico</label>
                    <input type="text" name="correo" id="edit_correo" placeholder="correo@ejemplo.com"
                           required autocomplete="off"
                           inputmode="email"
                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                           title="Ingresa un correo electrónico válido">
                </div>

                <div class="form-row">
                    <label>Nueva contraseña <span style="color:var(--text-gray);font-weight:400">(dejar en blanco para no cambiar)</span></label>
                    <input type="password" name="password" placeholder="Nueva contraseña"
                           autocomplete="new-password">
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditar')">Cancelar</button>
                <button type="button" class="btn-accept"
                    onclick="mostrarConfirm({
                        tipo: 'info',
                        icono: 'fa-floppy-disk',
                        titulo: 'Guardar cambios',
                        mensaje: '¿Está seguro de guardar los cambios realizados?',
                        txtOk: 'Sí, guardar',
                        onOk: () => document.getElementById('formEditar').submit()
                    })">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── CONFIRMACIÓN ── -->
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

<!-- Formularios ocultos -->
<form method="POST" action="index.php?menu=usuarios&opc=tabla" id="formToggle" style="display:none">
    <input type="hidden" name="action" value="toggle_activo">
    <input type="hidden" name="id"     id="toggle_id">
    <input type="hidden" name="activo" id="toggle_activo">
</form>
<form method="POST" action="index.php?menu=usuarios&opc=tabla" id="formEliminar" style="display:none">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="id"     id="eliminar_id">
</form>

<script>
const SOLO_LETRAS   = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/;
const CHAR_INVALIDO = /[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g;

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

function validarFormulario(form) {
    const campos = [
        { name: 'nombre',     opcional: false },
        { name: 'ap_paterno', opcional: false },
        { name: 'ap_materno', opcional: true  },
    ];
    let valido = true;
    for (const c of campos) {
        const input = form.querySelector(`[name="${c.name}"]`);
        if (!input) continue;
        const valor = input.value.trim();
        if (c.opcional && valor === '') continue;
        if (!SOLO_LETRAS.test(valor)) {
            const match = input.getAttribute('oninput')?.match(/'([^']+)'/);
            input.classList.add('input-error');
            if (match) document.getElementById(match[1]).classList.add('visible');
            if (valido) input.focus();
            valido = false;
        }
    }
    return valido;
}

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

function confirmarEliminar(id, nombre) {
    mostrarConfirm({
        tipo: 'danger', icono: 'fa-trash-can',
        titulo: 'Eliminar usuario',
        mensaje: `¿Está seguro de eliminar a ${nombre}? Esta acción no se puede deshacer.`,
        txtOk: 'Sí, eliminar',
        onOk: () => {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('formEliminar').submit();
        }
    });
}

function confirmarToggle(id, activo, nombre) {
    const desactivar = activo === 1;
    mostrarConfirm({
        tipo:    desactivar ? 'warning' : 'info',
        icono:   desactivar ? 'fa-toggle-off' : 'fa-toggle-on',
        titulo:  desactivar ? 'Desactivar usuario' : 'Activar usuario',
        mensaje: `¿Está seguro de ${desactivar ? 'desactivar' : 'activar'} a ${nombre}?`,
        txtOk:   desactivar ? 'Sí, desactivar' : 'Sí, activar',
        onOk: () => {
            document.getElementById('toggle_id').value     = id;
            document.getElementById('toggle_activo').value = activo;
            document.getElementById('formToggle').submit();
        }
    });
}

function abrirEditar(id, nombre, ap, am, correo) {
    document.getElementById('edit_id').value     = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_ap').value     = ap;
    document.getElementById('edit_am').value     = am;
    document.getElementById('edit_correo').value = correo;

    ['err-edit-nombre','err-edit-ap','err-edit-am'].forEach(eid =>
        document.getElementById(eid).classList.remove('visible'));
    ['edit_nombre','edit_ap','edit_am'].forEach(eid =>
        document.getElementById(eid).classList.remove('input-error'));

    openModal('modalEditar');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>