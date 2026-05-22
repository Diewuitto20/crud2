<?php

require_once dirname(__DIR__) . '/models/LogsModel.php';

class LogsController {

    public static function manejar(): void {
       if (empty($_SESSION['id_usuario'])) {
            header('Location: index.php?menu=login');
            exit;
        }
        include dirname(__DIR__) . '/views/logs.php';
    }
}