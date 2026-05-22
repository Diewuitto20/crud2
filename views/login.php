<?php

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

$error_message   = '';
$success_message = '';
$current_view    = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── LOGIN ── */
  if ($action === 'login') {
    $correo   = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($correo) || empty($password)) {
        $error_message = 'Por favor completa todos los campos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'El correo electrónico no es válido.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id_usuario, nombre, apellido_paterno, correo, contrasena, activo
             FROM usuarios WHERE correo = :correo LIMIT 1"
        );
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        if (!$usuario || $password !== $usuario['contrasena']) {
            $error_message = 'Correo o contraseña incorrectos.';
        } elseif ($usuario['activo'] == 0) {
            $error_message = 'Tu cuenta está desactivada. Contacta al administrador.';
        } else {
            $_SESSION['id_usuario']  = $usuario['id_usuario'];
            $_SESSION['nombre']      = $usuario['nombre'];
            $_SESSION['apellido']    = $usuario['apellido_paterno'];
            $_SESSION['correo']      = $usuario['correo'];
            $_SESSION['autenticado'] = true;

            header('Location: index.php?menu=dashboard');
            exit;
        }
    }

    /* ── REGISTRO ── */
    } elseif ($action === 'register') {
        $nombre   = trim($_POST['nombre']     ?? '');
        $ap_pat   = trim($_POST['ap_paterno'] ?? '');
        $ap_mat   = trim($_POST['ap_materno'] ?? '');
        $correo   = trim($_POST['email']      ?? '');
        $password = trim($_POST['password']   ?? '');

        $solo_letras = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/u';

        if (empty($nombre) || empty($ap_pat) || empty($correo) || empty($password)) {
            $error_message = 'Por favor completa todos los campos obligatorios.';
            $current_view  = 'register';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'El correo electrónico no es válido.';
            $current_view  = 'register';
        } elseif (strlen($password) < 4) {
            $error_message = 'La contraseña debe tener al menos 4 caracteres.';
            $current_view  = 'register';
        } elseif (!preg_match($solo_letras, $nombre)) {
            $error_message = 'El nombre solo puede contener letras.';
            $current_view  = 'register';
        } elseif (!preg_match($solo_letras, $ap_pat)) {
            $error_message = 'El apellido paterno solo puede contener letras.';
            $current_view  = 'register';
        } elseif (!empty($ap_mat) && !preg_match($solo_letras, $ap_mat)) {
            $error_message = 'El apellido materno solo puede contener letras.';
            $current_view  = 'register';
        } else {
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = :correo LIMIT 1");
            $stmt->execute([':correo' => $correo]);

            if ($stmt->fetch()) {
                $error_message = 'Este correo ya está registrado.';
                $current_view  = 'register';
            } else {
                $pdo->prepare(
                    "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, correo, contrasena)
                     VALUES (:nombre, :ap_pat, :ap_mat, :correo, :contrasena)"
                )->execute([
                    ':nombre'     => $nombre,
                    ':ap_pat'     => $ap_pat,
                    ':ap_mat'     => $ap_mat,
                    ':correo'     => $correo,
                    ':contrasena' => $password,
                ]);

                $success_message = '¡Cuenta creada exitosamente! Ya puedes iniciar sesión.';
                $current_view    = 'login';
            }
        }

    /* ── RECUPERAR ── */
    } elseif ($action === 'recover') {
        $correo  = trim($_POST['email'] ?? '');
        $captcha = isset($_POST['captcha']);

        if (empty($correo)) {
            $error_message = 'Por favor ingresa tu correo electrónico.';
            $current_view  = 'recover';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'El correo electrónico no es válido.';
            $current_view  = 'recover';
        } elseif (!$captcha) {
            $error_message = 'Por favor confirma que no eres un robot.';
            $current_view  = 'recover';
        } else {
            $success_message = 'Si el correo existe, recibirás las instrucciones en breve.';
            $current_view    = 'login';
        }
    }
}

/**
 * Devuelve el valor de $_POST[$key] solo si el action del POST coincide
 * con $action_required. Así evitamos que datos de login contaminen el
 * formulario de registro y viceversa.
 */
function old(string $key, string $action_required = '', string $default = ''): string {
    if ($action_required !== '' && ($_POST['action'] ?? '') !== $action_required) {
        return $default;
    }
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autenticación – Recicladora Diaz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-page:     #f4f6f8;
            --bg-box:      #e8eaed;
            --white:       #ffffff;
            --text-dark:   #2d2d2d;
            --text-gray:   #6b7280;
            --green-dark:  #1a5632;
            --green-mid:   #2e7d52;
            --btn-blue:    #93c5fd;
            --btn-blue-h:  #60a5fa;
            --red:         #dc2626;
            --green-ok:    #16a34a;
            --radius-pill: 30px;
            --radius-box:  20px;
            --shadow-sm:   0 2px 8px rgba(0,0,0,.08);
            --shadow-md:   0 6px 20px rgba(0,0,0,.12);
            --transition:  .2s ease;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Roboto',sans-serif; background:var(--bg-page); min-height:100vh; display:flex; justify-content:center; align-items:center; }
        .auth-card { background:var(--bg-box); width:min(820px,95vw); border-radius:var(--radius-box); display:flex; overflow:hidden; box-shadow:var(--shadow-md); }
        .auth-left { flex:0 0 280px; background:linear-gradient(160deg,#1a5632 0%,#2e7d52 100%); display:flex; flex-direction:column; justify-content:center; align-items:center; padding:40px 24px; gap:20px; transition:all .3s ease; }
        .logo-box { width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,.15); border:3px solid rgba(255,255,255,.4); display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; text-align:center; letter-spacing:.5px; line-height:1.4; }
        .logo-box i { font-size:32px; margin-bottom:6px; }
        .auth-subtitle { color:rgba(255,255,255,.85); font-size:15px; font-weight:400; letter-spacing:.3px; }
        .auth-right { flex:1; padding:44px 40px; display:flex; flex-direction:column; justify-content:center; background:var(--white); }
        .view { display:none; flex-direction:column; align-items:center; gap:0; }
        .view.active { display:flex; }
        .view-title { font-size:22px; font-weight:600; color:var(--text-dark); margin-bottom:24px; text-align:center; }
        .alert { width:100%; max-width:340px; padding:10px 16px; border-radius:10px; font-size:13.5px; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .alert-error   { background:#fef2f2; color:var(--red);      border:1px solid #fca5a5; }
        .alert-success { background:#f0fdf4; color:var(--green-ok); border:1px solid #86efac; }
        .input-wrap { display:flex; align-items:center; background:#f9fafb; border:1.5px solid #e5e7eb; border-radius:var(--radius-pill); padding:10px 18px; width:100%; max-width:340px; margin-bottom:14px; transition:border-color var(--transition),box-shadow var(--transition); }
        .input-wrap:focus-within { border-color:var(--green-mid); box-shadow:0 0 0 3px rgba(46,125,82,.12); background:var(--white); }
        .input-wrap i { color:var(--text-gray); font-size:16px; margin-right:12px; width:20px; text-align:center; flex-shrink:0; }
        .input-wrap input { border:none; outline:none; font-size:14.5px; width:100%; background:transparent; color:var(--text-dark); }
        .input-wrap input::placeholder { color:#9ca3af; }
        .link-small { font-size:13px; color:var(--green-mid); text-decoration:none; margin-bottom:20px; display:block; text-align:center; background:none; border:none; cursor:pointer; }
        .link-small:hover { text-decoration:underline; }
        .btn-group { display:flex; gap:12px; justify-content:center; width:100%; margin-top:6px; }
        .btn { flex:1; max-width:150px; padding:11px 20px; border-radius:var(--radius-pill); font-size:14.5px; font-weight:500; cursor:pointer; border:none; transition:background var(--transition),transform var(--transition),box-shadow var(--transition); box-shadow:var(--shadow-sm); letter-spacing:.2px; }
        .btn:active { transform:scale(.97); }
        .btn-primary   { background:var(--green-dark); color:#fff; }
        .btn-primary:hover { background:var(--green-mid); box-shadow:var(--shadow-md); }
        .btn-secondary { background:var(--white); color:var(--text-dark); border:1.5px solid #d1d5db; }
        .btn-secondary:hover { background:#f3f4f6; }
        .btn-blue  { background:var(--btn-blue); color:var(--text-dark); }
        .btn-blue:hover { background:var(--btn-blue-h); }
        .btn-ghost { background:transparent; color:var(--green-mid); box-shadow:none; border:1.5px solid #d1d5db; }
        .btn-ghost:hover { background:#f0fdf4; }
        .captcha-box { display:flex; align-items:center; justify-content:space-between; background:#f9fafb; border:1.5px solid #e5e7eb; border-radius:var(--radius-pill); padding:10px 18px; width:100%; max-width:280px; margin-bottom:20px; gap:10px; }
        .captcha-box input[type="checkbox"] { width:18px; height:18px; accent-color:var(--green-dark); cursor:pointer; }
        .captcha-box span { font-size:13.5px; color:var(--text-dark); flex:1; }
        .captcha-box i { font-size:18px; color:var(--text-gray); }
        .recover-text { text-align:center; font-size:13.5px; color:var(--text-gray); max-width:300px; margin-bottom:20px; line-height:1.6; }
        .field-error {
            display: none; color: #e53e3e; font-size: 12px; margin-top: 2px;
            align-items: center; gap: 5px; animation: fadeIn .15s ease;
            padding-left: 8px;
        }
        .field-error.visible { display: flex; }
        .field-error i { font-size: 11px; flex-shrink: 0; }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(-3px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @media (max-width:600px) {
            .auth-card { flex-direction:column; }
            .auth-left { flex:none; padding:28px; flex-direction:row; gap:16px; }
            .auth-right { padding:32px 24px; }
            .logo-box { width:72px; height:72px; font-size:11px; }
            .logo-box i { font-size:22px; }
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-left" id="auth-left">
        <div class="logo-box">
            <i class="fa-solid fa-recycle"></i>
            Recicladora<br>Diaz
        </div>
        <div class="auth-subtitle">Autenticación</div>
    </div>
    <div class="auth-right">

        <!-- LOGIN -->
        <div id="view-login" class="view <?= $current_view === 'login' ? 'active' : '' ?>">
            <?php if ($success_message && $current_view === 'login'): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if ($error_message && $current_view === 'login'): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <form method="POST" action="" novalidate autocomplete="off" style="display:contents;">
                <input type="hidden" name="action" value="login">
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico" required
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña" required
                           autocomplete="new-password">
                </div>
                <button type="button" class="link-small" onclick="switchView('recover')">¿Olvidaste tu contraseña?</button>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Iniciar sesión</button>
                    <button type="button" class="btn btn-secondary" onclick="switchView('register')">Crear cuenta</button>
                </div>
            </form>
        </div>

        <!-- REGISTRO -->
        <div id="view-register" class="view <?= $current_view === 'register' ? 'active' : '' ?>">
            <div class="view-title">Crear cuenta</div>
            <?php if ($error_message && $current_view === 'register'): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <form method="POST" action="" novalidate autocomplete="off" style="display:contents;">
                <input type="hidden" name="action" value="register">

                <div style="width:100%;max-width:340px;margin-bottom:10px">
                    <div class="input-wrap" style="margin-bottom:4px">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="nombre" placeholder="Nombre *"
                               value="<?= old('nombre', 'register') ?>" required
                               autocomplete="off"
                               oninput="validarLetras(this,'err-reg-nombre')">
                    </div>
                    <span class="field-error" id="err-reg-nombre">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div style="width:100%;max-width:340px;margin-bottom:10px">
                    <div class="input-wrap" style="margin-bottom:4px">
                        <i class="fa-solid fa-user-tag"></i>
                        <input type="text" name="ap_paterno" placeholder="Apellido paterno *"
                               value="<?= old('ap_paterno', 'register') ?>" required
                               autocomplete="off"
                               oninput="validarLetras(this,'err-reg-ap')">
                    </div>
                    <span class="field-error" id="err-reg-ap">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div style="width:100%;max-width:340px;margin-bottom:10px">
                    <div class="input-wrap" style="margin-bottom:4px">
                        <i class="fa-solid fa-user-tag"></i>
                        <input type="text" name="ap_materno" placeholder="Apellido materno"
                               value="<?= old('ap_materno', 'register') ?>"
                               autocomplete="off"
                               oninput="validarLetras(this,'err-reg-am')">
                    </div>
                    <span class="field-error" id="err-reg-am">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Solo se permiten letras. No se aceptan números ni caracteres especiales.
                    </span>
                </div>

                <div class="input-wrap">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico *"
                           value="<?= old('email', 'register') ?>" required
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>

                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña *" required
                           autocomplete="new-password">
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-blue" onclick="switchView('login')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Continuar</button>
                </div>
            </form>
        </div>

        <!-- RECUPERAR -->
        <div id="view-recover" class="view <?= $current_view === 'recover' ? 'active' : '' ?>">
            <div class="view-title">Recuperar contraseña</div>
            <?php if ($error_message && $current_view === 'recover'): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <p class="recover-text">Ingresa tu correo y te enviaremos un código de confirmación para restablecer tu contraseña.</p>
            <form method="POST" action="" novalidate autocomplete="off" style="display:contents;">
                <input type="hidden" name="action" value="recover">
                <div class="input-wrap">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico" required
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>
                <div class="captcha-box">
                    <input type="checkbox" name="captcha" id="captcha">
                    <span>No soy un robot</span>
                    <i class="fa-solid fa-arrows-rotate"></i>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Enviar código</button>
                    <button type="button" class="btn btn-ghost" onclick="switchView('login')">Cerrar</button>
                </div>
            </form>
        </div>

    </div>
</div>
<script>
    function validarLetras(input, errorId) {
        const INVALIDO = /[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g;
        const SOLO     = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/;
        const errorEl  = document.getElementById(errorId);
        if (INVALIDO.test(input.value)) {
            input.value = input.value.replace(INVALIDO, '');
            errorEl.classList.add('visible');
            clearTimeout(input._t);
            input._t = setTimeout(() => {
                if (SOLO.test(input.value) || input.value === '')
                    errorEl.classList.remove('visible');
            }, 3000);
        } else {
            errorEl.classList.remove('visible');
        }
    }

    function switchView(view) {
        document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
        document.getElementById('view-' + view).classList.add('active');
        document.getElementById('auth-left').style.display = (view === 'register') ? 'none' : '';

        /* Limpiar formulario de registro al abrirlo desde login */
        if (view === 'register') {
            const form = document.querySelector('#view-register form');
            if (form) {
                form.reset();
                document.querySelectorAll('.field-error').forEach(el => el.classList.remove('visible'));
            }
        }
    }
    (function() {
        const active = document.querySelector('.view.active');
        if (active && active.id === 'view-register')
            document.getElementById('auth-left').style.display = 'none';
    })();
</script>
</body>
</html>