<?php
class Usuarios {
    private $db;

    public function __construct($conexion) {
        $this->db = $conexion;
    }

    public function mostrar() {
        $sql = "SELECT * FROM usuarios ORDER BY id_usuario DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function guardar($contrasena, $apellido_paterno, $apellido_materno, $correo, $nombre) {
        $sql = "INSERT INTO usuarios (contrasena, apellido_paterno, apellido_materno, correo, nombre) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([trim($contrasena), trim($apellido_paterno), trim($apellido_materno), trim($correo), trim($nombre)]);
    }

    public function eliminar($id) {
        $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function buscar($id) {
        $sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function actualizar($id, $contrasena, $correo) {
        $sql = "UPDATE usuarios SET contrasena = ?, correo = ? WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([trim($contrasena), trim($correo), $id]);
    }
}