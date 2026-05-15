<?php
/* =====================================================================
   models/RespaldoModel.php  –  Queries de la tabla respaldos
   ===================================================================== */
require_once dirname(__DIR__) . '/config/database.php';

class RespaldoModel {

    public static function todos(): array {
        return get_db()->query(
            "SELECT r.id, r.tipo_operacion, r.nombre_archivo, r.formato,
                    r.nombre_bd, r.tamanio_bytes, r.fechayhora, r.observaciones,
                    u.nombre AS creado_por
             FROM respaldos r
             LEFT JOIN usuarios u ON u.id_usuario = r.usuario_id
             ORDER BY r.fechayhora DESC"
        )->fetchAll();
    }

    public static function registrar(string $tipo, string $archivo, string $formato,
                                      string $bd, int $bytes, ?int $uid,
                                      string $observaciones): void {
        get_db()->prepare(
            "INSERT INTO respaldos (tipo_operacion, nombre_archivo, formato, nombre_bd, tamanio_bytes, usuario_id, observaciones)
             VALUES (:tipo, :archivo, :formato, :bd, :bytes, :uid, :obs)"
        )->execute([
            ':tipo'    => $tipo,
            ':archivo' => $archivo,
            ':formato' => $formato,
            ':bd'      => $bd,
            ':bytes'   => $bytes,
            ':uid'     => $uid,
            ':obs'     => $observaciones,
        ]);
    }

    public static function eliminar(int $id): void {
        get_db()->prepare("DELETE FROM respaldos WHERE id=:id")->execute([':id' => $id]);
    }

    public static function ultimo(): ?array {
        $row = get_db()->query(
            "SELECT * FROM respaldos ORDER BY fechayhora DESC LIMIT 1"
        )->fetch();
        return $row ?: null;
    }
}