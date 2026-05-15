<?php
/* =====================================================================
   controllers/CompraController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/CompraModel.php';
require_once dirname(__DIR__) . '/models/AuditoriaModel.php';

class CompraController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($accion === 'crear') {
                CompraModel::crear(
                    trim($_POST['clasificacion']  ?? ''),
                    trim($_POST['nombre_empresa'] ?? ''),
                    (float)($_POST['kilos']           ?? 0),
                    (float)($_POST['dinero_recibido'] ?? 0),
                    trim($_POST['fecha'] ?? '')
                );
                AuditoriaModel::registrar('compras', 'crear', 'Compra registrada');
                self::redirigir();
            }

            if ($accion === 'eliminar') {
                CompraModel::eliminar((int)($_POST['id'] ?? 0));
                AuditoriaModel::registrar('compras', 'eliminar', 'Compra eliminada');
                self::redirigir();
            }
        }

        include dirname(__DIR__) . '/views/Gestioncompras.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=gestion_compras&opc=tabla');
        exit;
    }
}