<?php

require_once dirname(__DIR__) . '/config/database.php';

class LogsModel {

    public static function todos(array $filtros = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['modulo']))  { $where[] = 'modulo = :modulo';         $params[':modulo']  = $filtros['modulo']; }
        if (!empty($filtros['usuario'])) { $where[] = 'usuario_nombre LIKE :usu'; $params[':usu']     = "%{$filtros['usuario']}%"; }
        if (!empty($filtros['fecha']))   { $where[] = 'DATE(fecha) = :fecha';     $params[':fecha']   = $filtros['fecha']; }
        if (!empty($filtros['buscar']))  { $where[] = 'descripcion LIKE :buscar'; $params[':buscar']  = "%{$filtros['buscar']}%"; }

        $sql  = "SELECT * FROM auditoria WHERE " . implode(' AND ', $where) . " ORDER BY fecha DESC LIMIT 500";
        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function registrar(string $modulo, string $accion,
                                      string $descripcion = ''): void {
        get_db()->prepare(
            "INSERT INTO auditoria (usuario_nombre, modulo, accion, descripcion, ip)
             VALUES (:usuario, :modulo, :accion, :desc, :ip)"
        )->execute([
            ':usuario' => $_SESSION['nombre'] ?? 'sistema',
            ':modulo'  => $modulo,
            ':accion'  => $accion,
            ':desc'    => $descripcion,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '—',
        ]);
    }

    public static function stats(): array {
        $pdo = get_db();
        return [
            'total'       => $pdo->query("SELECT COUNT(*) FROM auditoria")->fetchColumn(),
            'hoy'         => $pdo->query("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha)=CURDATE()")->fetchColumn(),
            'modulos'     => $pdo->query("SELECT COUNT(DISTINCT modulo) FROM auditoria")->fetchColumn(),
            'usuarios'    => $pdo->query("SELECT COUNT(DISTINCT usuario_nombre) FROM auditoria")->fetchColumn(),
        ];
    }

    public static function modulos(): array {
        return get_db()
            ->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}