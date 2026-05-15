<?php
/* =====================================================================
   models/UsuarioModel.php  –  Queries de la tabla usuarios
   ===================================================================== */
require_once dirname(__DIR__) . '/config/database.php';

class UsuarioModel {

    public static function todos(): array {
        return get_db()->query("SELECT * FROM usuarios ORDER BY nombre ASC")->fetchAll();
    }

    public static function buscarPorCorreo(string $correo): ?array {
        $stmt = get_db()->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute([':correo' => $correo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function crear(string $nombre, string $ap_pat, string $ap_mat,
                                  string $correo, string $contrasena): void {
        get_db()->prepare(
            "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, correo, contrasena)
             VALUES (:nombre, :ap_pat, :ap_mat, :correo, :contrasena)"
        )->execute([
            ':nombre'     => $nombre,
            ':ap_pat'     => $ap_pat,
            ':ap_mat'     => $ap_mat,
            ':correo'     => $correo,
            ':contrasena' => $contrasena,
        ]);
    }

    public static function editar(int $id, string $nombre, string $ap_pat,
                                   string $ap_mat, string $correo): void {
        get_db()->prepare(
            "UPDATE usuarios SET nombre=:nombre, apellido_paterno=:ap_pat,
             apellido_materno=:ap_mat, correo=:correo WHERE id_usuario=:id"
        )->execute([
            ':nombre' => $nombre, ':ap_pat' => $ap_pat,
            ':ap_mat' => $ap_mat, ':correo' => $correo, ':id' => $id,
        ]);
    }

    public static function eliminar(int $id): void {
        get_db()->prepare("DELETE FROM usuarios WHERE id_usuario=:id")->execute([':id' => $id]);
    }

    public static function toggleActivo(int $id, int $activo): void {
        get_db()->prepare("UPDATE usuarios SET activo=:activo WHERE id_usuario=:id")
                ->execute([':activo' => $activo, ':id' => $id]);
    }

    public static function total(): int {
        return (int) get_db()->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    }
}