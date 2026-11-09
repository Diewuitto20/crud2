<?php

require_once dirname(__DIR__) . '/models/UsuarioModel.php';
require_once dirname(__DIR__) . '/models/LogsModel.php';

class UsuarioController {

    public static function manejar(): void {
        require_once dirname(__DIR__) . '/views/permisos.php';
        exigir_dueno();
        include dirname(__DIR__) . '/views/usuarios.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=usuarios&opc=tabla');
        exit;
    }
}