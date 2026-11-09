<?php
require_once dirname(__DIR__) . '/config/database.php';

/* Garantiza la columna rol y que exista un dueño (el usuario más antiguo si aún no hay ninguno) */
function asegurar_rol(): void {
    static $hecho = false;
    if ($hecho) return;
    $hecho = true;
    try {
        $pdo = get_db();
        try { $pdo->query("SELECT rol FROM usuarios LIMIT 1"); }
        catch (PDOException $e) { $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol VARCHAR(10) NOT NULL DEFAULT 'empleado'"); }
        if ((int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'dueno'")->fetchColumn() === 0) {
            $pdo->exec("UPDATE usuarios SET rol = 'dueno' WHERE id_usuario = (SELECT m FROM (SELECT MIN(id_usuario) AS m FROM usuarios) t)");
        }
        $len = (int)$pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'contrasena'")->fetchColumn();
        if ($len > 0 && $len < 100) $pdo->exec("ALTER TABLE usuarios MODIFY contrasena VARCHAR(255) NOT NULL");
    } catch (Throwable $e) { error_log('asegurar_rol: ' . $e->getMessage()); }
}

function hash_clave(string $clave): string { return password_hash($clave, PASSWORD_DEFAULT); }

/* Acepta contraseñas ya hasheadas y las antiguas en texto plano (se migran al iniciar sesión) */
function clave_valida(string $ingresada, string $guardada): bool {
    if (preg_match('/^\$2[aby]\$|^\$argon2/', $guardada)) return password_verify($ingresada, $guardada);
    return hash_equals($guardada, $ingresada);
}

/* Registro público solo mientras no exista ningún usuario (el primero será el dueño) */
function registro_abierto(): bool {
    try { return (int)get_db()->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() === 0; }
    catch (Throwable $e) { return false; }
}

/* Datos vigentes del usuario en sesión, leídos de la base en cada petición */
function usuario_sesion(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    asegurar_rol();
    if (empty($_SESSION['id_usuario'])) return $u = null;
    try {
        $s = get_db()->prepare("SELECT id_usuario, rol, activo FROM usuarios WHERE id_usuario = ?");
        $s->execute([(int)$_SESSION['id_usuario']]);
        return $u = ($s->fetch() ?: null);
    } catch (Throwable $e) { return $u = null; }
}

function es_dueno(): bool {
    $u = usuario_sesion();
    return $u !== null && $u['rol'] === 'dueno' && (int)$u['activo'] === 1;
}

function exigir_dueno(): void {
    if (!es_dueno()) { header('Location: index.php?menu=dashboard'); exit; }
}

/* Cierra la sesión si el usuario fue eliminado o desactivado mientras estaba dentro */
function validar_sesion(): void {
    if (empty($_SESSION['autenticado'])) return;
    $u = usuario_sesion();
    if ($u === null || (int)$u['activo'] !== 1) {
        session_unset(); session_destroy();
        header('Location: index.php?menu=login');
        exit;
    }
}
