<?php

require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/CompraModel.php';
require_once dirname(__DIR__) . '/models/AgendaModel.php';
require_once dirname(__DIR__) . '/models/UsuarioModel.php';

class DashboardController {

    public static function manejar(): void {
       
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