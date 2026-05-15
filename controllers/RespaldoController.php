<?php
/* =====================================================================
   controllers/RespaldoController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/RespaldoModel.php';
require_once dirname(__DIR__) . '/models/AuditoriaModel.php';

class RespaldoController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($accion === 'eliminar_respaldo') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) { RespaldoModel::eliminar($id); }
                AuditoriaModel::registrar('respaldos', 'eliminar', 'Registro de respaldo eliminado');
                header('Location: index.php?menu=respaldos&opc=tabla');
                exit;
            }
        }

        /* Las acciones de exportar/importar SQL las maneja Respaldos.php directamente
           porque requieren enviar archivos binarios (ZIP/PDF) */
        include dirname(__DIR__) . '/views/Respaldos.php';
    }
}