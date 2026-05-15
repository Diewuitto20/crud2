<?php
/* =====================================================================
   models/CompraModel.php  –  Queries de gestion_compras
   ===================================================================== */
require_once dirname(__DIR__) . '/config/database.php';

class CompraModel {

    public static function todas(): array {
        return get_db()
            ->query("SELECT * FROM gestion_compras ORDER BY fecha DESC")
            ->fetchAll();
    }

    public static function crear(string $clasificacion, string $nombre_empresa,
                                  float $kilos, float $dinero_recibido,
                                  string $fecha = ''): void {
        get_db()->prepare(
            "INSERT INTO gestion_compras (fecha, kilos, clasificacion, nombre_empresa, dinero_recibido)
             VALUES (:fecha, :kilos, :clas, :empresa, :dinero)"
        )->execute([
            ':fecha'   => $fecha ?: date('Y-m-d'),
            ':kilos'   => $kilos,
            ':clas'    => $clasificacion,
            ':empresa' => $nombre_empresa,
            ':dinero'  => $dinero_recibido,
        ]);
    }

    public static function eliminar(int $id): void {
        get_db()->prepare("DELETE FROM gestion_compras WHERE id_gestion_venta=:id")
                ->execute([':id' => $id]);
    }

    public static function total(): int {
        return (int) get_db()->query("SELECT COUNT(*) FROM gestion_compras")->fetchColumn();
    }

    public static function totalDineroHoy(): float {
        $row = get_db()->query(
            "SELECT COALESCE(SUM(dinero_recibido),0) AS total FROM gestion_compras WHERE DATE(fecha)=CURDATE()"
        )->fetch();
        return (float) $row['total'];
    }
}