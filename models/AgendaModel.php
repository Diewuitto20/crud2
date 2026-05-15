<?php
/* =====================================================================
   models/AgendaModel.php  –  Queries de la tabla agenda (eventos)
   ===================================================================== */
require_once dirname(__DIR__) . '/config/database.php';

class AgendaModel {

    public static function todos(): array {
        return get_db()
            ->query("SELECT * FROM agenda ORDER BY fecha ASC, hora ASC")
            ->fetchAll();
    }

    public static function crear(string $descripcion, string $fecha,
                                  string $hora, ?int $id_usuario = null): void {
        get_db()->prepare(
            "INSERT INTO agenda (fecha, descripcion, hora, id_usuario)
             VALUES (:fecha, :desc, :hora, :uid)"
        )->execute([
            ':fecha' => $fecha,
            ':desc'  => $descripcion,
            ':hora'  => $hora,
            ':uid'   => $id_usuario ?? $_SESSION['id_usuario'] ?? null,
        ]);
    }

    public static function eliminar(int $id): void {
        get_db()->prepare("DELETE FROM agenda WHERE id_evento=:id")->execute([':id' => $id]);
    }

    public static function total(): int {
        return (int) get_db()->query("SELECT COUNT(*) FROM agenda")->fetchColumn();
    }

    public static function proximos(int $limite = 5): array {
        $stmt = get_db()->prepare(
            "SELECT * FROM agenda WHERE fecha >= CURDATE() ORDER BY fecha ASC, hora ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}