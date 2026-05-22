<?php
/* =====================================================================
   controllers/UsuarioController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/UsuarioModel.php';
require_once dirname(__DIR__) . '/models/LogsModel.php';

class UsuarioController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($accion === 'crear') {
                UsuarioModel::crear(
                    trim($_POST['nombre']     ?? ''),
                    trim($_POST['ap_paterno'] ?? ''),
                    trim($_POST['ap_materno'] ?? ''),
                    trim($_POST['correo']     ?? ''),
                    trim($_POST['password']   ?? '')
                );
                LogsModel::registrar('usuarios', 'crear', 'Nuevo usuario creado');
                self::redirigir();
            }

            if ($accion === 'editar') {
                UsuarioModel::editar(
                    (int)($_POST['id']        ?? 0),
                    trim($_POST['nombre']     ?? ''),
                    trim($_POST['ap_paterno'] ?? ''),
                    trim($_POST['ap_materno'] ?? ''),
                    trim($_POST['correo']     ?? '')
                );
                LogsModel::registrar('usuarios', 'editar', 'Usuario editado');
                self::redirigir();
            }

            if ($accion === 'eliminar') {
                UsuarioModel::eliminar((int)($_POST['id'] ?? 0));
                LogsModel::registrar('usuarios', 'eliminar', 'Usuario eliminado');
                self::redirigir();
            }

            if ($accion === 'toggle') {
                $activo = (int)($_POST['activo'] ?? 0);
                UsuarioModel::toggleActivo((int)($_POST['id'] ?? 0), $activo);
                LogsModel::registrar('usuarios', $activo ? 'activar' : 'desactivar', 'Estado de usuario cambiado');
                self::redirigir();
            }
        }

        /* Cargar vista */
        include dirname(__DIR__) . '/views/usuarios.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=usuarios&opc=tabla');
        exit;
    }
}