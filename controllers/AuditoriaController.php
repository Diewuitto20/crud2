<?php
/* =====================================================================
   controllers/AuditoriaController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/AuditoriaModel.php';

class AuditoriaController {

    public static function manejar(): void {
        /* Solo lectura — no hay acciones POST en auditoría */
        include dirname(__DIR__) . '/views/auditoria.php';
    }
}