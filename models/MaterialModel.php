<?php
/* =====================================================================
   models/MaterialModel.php  –  Queries de la tabla materiales
   ===================================================================== */
require_once dirname(__DIR__) . '/config/database.php';

class MaterialModel {

    public static function todos(): array {
        return get_db()->query("SELECT * FROM materiales ORDER BY nombre ASC")->fetchAll();
    }

    public static function buscarPorId(string $id): ?array {
        $stmt = get_db()->prepare("SELECT * FROM materiales WHERE id_material=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function buscarPorNombre(string $nombre): ?array {
        $stmt = get_db()->prepare(
            "SELECT * FROM materiales WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1"
        );
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function crear(string $nombre, string $categoria, string $unidad,
                                  float $precio_kg, float $stock_max, float $stock_min,
                                  ?int $id_usuario = null): void {
        get_db()->prepare(
            "INSERT INTO materiales (id_material, nombre, categoria, unidad, precio_kg, stock_max, stock_min, id_usuario)
             VALUES (:id, :nombre, :categoria, :unidad, :precio_kg, :stock_max, :stock_min, :uid)"
        )->execute([
            ':id'        => uniqid(),
            ':nombre'    => $nombre,
            ':categoria' => $categoria,
            ':unidad'    => $unidad,
            ':precio_kg' => $precio_kg,
            ':stock_max' => $stock_max,
            ':stock_min' => $stock_min,
            ':uid'       => $id_usuario,
        ]);
    }

    public static function editar(string $id, string $nombre, string $categoria,
                                   string $unidad, float $precio_kg,
                                   float $stock_max, float $stock_min): void {
        get_db()->prepare(
            "UPDATE materiales SET nombre=:nombre, categoria=:categoria, unidad=:unidad,
             precio_kg=:precio_kg, stock_max=:stock_max, stock_min=:stock_min WHERE id_material=:id"
        )->execute([
            ':nombre'    => $nombre,
            ':categoria' => $categoria,
            ':unidad'    => $unidad,
            ':precio_kg' => $precio_kg,
            ':stock_max' => $stock_max,
            ':stock_min' => $stock_min,
            ':id'        => $id,
        ]);
    }

    public static function eliminar(string $id): void {
        get_db()->prepare("DELETE FROM materiales WHERE id_material=:id")->execute([':id' => $id]);
    }

    public static function total(): int {
        return (int) get_db()->query("SELECT COUNT(*) FROM materiales")->fetchColumn();
    }

    public static function alertasStock(): array {
        return get_db()
            ->query("SELECT * FROM materiales WHERE stock_max <= stock_min ORDER BY nombre ASC")
            ->fetchAll();
    }
}