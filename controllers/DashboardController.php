<?php
/* =====================================================================
   controllers/DashboardController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/CompraModel.php';
require_once dirname(__DIR__) . '/models/AgendaModel.php';
require_once dirname(__DIR__) . '/models/UsuarioModel.php';

class DashboardController {

    public static function manejar(): void {
        /* Prepara datos para la vista del dashboard */
        $datos = [
            'total_materiales' => MaterialModel::total(),
            'total_compras'    => CompraModel::total(),
            'total_eventos'    => AgendaModel::total(),
            'total_usuarios'   => UsuarioModel::total(),
            'alertas_stock'    => MaterialModel::alertasStock(),
            'compras_hoy'      => CompraModel::totalDineroHoy(),
            'proximos_eventos' => AgendaModel::proximos(5),
        ];

        extract($datos);
        include dirname(__DIR__) . '/views/dashboard.php';
    }
}