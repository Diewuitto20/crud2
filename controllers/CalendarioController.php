<?php
/* =====================================================================
   controllers/CalendarioController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/AgendaModel.php';
require_once dirname(__DIR__) . '/models/AuditoriaModel.php';

class CalendarioController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($accion === 'crear_evento') {
                AgendaModel::crear(
                    trim($_POST['descripcion'] ?? $_POST['titulo'] ?? ''),
                    trim($_POST['fecha']       ?? ''),
                    trim($_POST['hora']        ?? '00:00'),
                    $_SESSION['id_usuario']    ?? null
                );
                AuditoriaModel::registrar('calendario', 'crear', 'Evento creado');
                self::redirigir();
            }

            if ($accion === 'eliminar_evento') {
                AgendaModel::eliminar((int)($_POST['id'] ?? 0));
                AuditoriaModel::registrar('calendario', 'eliminar', 'Evento eliminado');
                self::redirigir();
            }
        }

        include dirname(__DIR__) . '/views/calendario.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=calendario');
        exit;
    }
}