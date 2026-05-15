<?php
/* =====================================================================
   controllers/MaterialController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/AuditoriaModel.php';

class MaterialController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($accion === 'crear') {
                MaterialModel::crear(
                    trim($_POST['nombre']    ?? ''),
                    trim($_POST['categoria'] ?? ''),
                    trim($_POST['unidad']    ?? 'kg'),
                    (float)($_POST['precio_kg'] ?? 0),
                    (float)($_POST['stock']     ?? 0),
                    (float)($_POST['stock_min'] ?? 0),
                    $_SESSION['id_usuario'] ?? null
                );
                AuditoriaModel::registrar('materiales', 'crear', 'Material creado');
                self::redirigir();
            }

            if ($accion === 'editar') {
                MaterialModel::editar(
                    trim($_POST['id']        ?? ''),
                    trim($_POST['nombre']    ?? ''),
                    trim($_POST['categoria'] ?? ''),
                    trim($_POST['unidad']    ?? 'kg'),
                    (float)($_POST['precio_kg'] ?? 0),
                    (float)($_POST['stock']     ?? 0),
                    (float)($_POST['stock_min'] ?? 0)
                );
                AuditoriaModel::registrar('materiales', 'editar', 'Material editado');
                self::redirigir();
            }

            if ($accion === 'eliminar') {
                MaterialModel::eliminar(trim($_POST['id'] ?? ''));
                AuditoriaModel::registrar('materiales', 'eliminar', 'Material eliminado');
                self::redirigir();
            }
        }

        include dirname(__DIR__) . '/views/materiales.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=material&opc=tabla');
        exit;
    }
}