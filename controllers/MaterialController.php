<?php
require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/LogsModel.php';

class MaterialController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

          
            $nombre = isset($_POST['nombre_sel'])
                ? (trim($_POST['nombre_sel']) === 'Otro'
                    ? trim($_POST['nombre_otro'] ?? '')
                    : trim($_POST['nombre_sel']))
                : trim($_POST['nombre'] ?? '');

            $categoria = isset($_POST['categoria'])
                ? (trim($_POST['categoria']) === 'Otro'
                    ? trim($_POST['categoria_otro'] ?? '')
                    : trim($_POST['categoria']))
                : '';

           
            if (in_array($accion, ['crear', 'editar'], true)) {
                $pr = (float)($_POST['precio_kg'] ?? 0); $mx = (float)($_POST['stock_max'] ?? 0); $mn = (float)($_POST['stock_min'] ?? 0);
                $uni = trim($_POST['unidad'] ?? 'kg');
                $mal = $nombre === '' ? 'El nombre del material es obligatorio.'
                     : ($categoria === '' ? 'La categoría es obligatoria.'
                     : ($pr <= 0 ? 'El precio debe ser mayor a cero.'
                     : ($mx <= 0 || $mn < 0 || $mn > $mx ? 'Revise los límites: el máximo debe ser mayor a cero y el mínimo no puede superarlo.'
                     : ($uni !== 'kg' ? 'Por ahora solo se admite la unidad kg.' : ''))));
                if ($mal !== '') self::redirigir($mal);
            }

            if ($accion === 'crear') {
           
                $existente = MaterialModel::buscarPorNombre($nombre);

                if ($existente) {
                   
                    $_SESSION['duplicado'] = [
                        'id_existente' => $existente['id_material'],
                        'nombre'       => $nombre,
                        'categoria'    => $categoria,
                        'unidad'       => trim($_POST['unidad']    ?? 'kg'),
                        'precio_kg'    => (float)($_POST['precio_kg'] ?? 0),
                        'stock_max'    => (float)($_POST['stock_max'] ?? 0),
                        'stock_min'    => (float)($_POST['stock_min'] ?? 0),
                    ];
                    header('Location: index.php?menu=material&opc=tabla&duplicado=1');
                    exit;
                }

                MaterialModel::crear(
                    $nombre, $categoria,
                    trim($_POST['unidad']    ?? 'kg'),
                    (float)($_POST['precio_kg'] ?? 0),
                    (float)($_POST['stock_max'] ?? 0),
                    (float)($_POST['stock_min'] ?? 0),
                    $_SESSION['id_usuario'] ?? null
                );
                LogsModel::registrar('materiales', 'crear', 'Material creado');
                self::redirigir();
            }

            if ($accion === 'actualizar_existente') {
                $dup = $_SESSION['duplicado'] ?? null;
                if ($dup) {
                    MaterialModel::editar(
                        $dup['id_existente'],
                        $dup['nombre'],
                        $dup['categoria'],
                        $dup['unidad'],
                        $dup['precio_kg'],
                        $dup['stock_max'],
                        $dup['stock_min']
                    );
                    LogsModel::registrar('materiales', 'editar', 'Material actualizado por duplicado');
                    unset($_SESSION['duplicado']);
                }
                self::redirigir();
            }


            if ($accion === 'cancelar_duplicado') {
                unset($_SESSION['duplicado']);
                self::redirigir();
            }

            if ($accion === 'editar') {
                $idm = trim($_POST['id'] ?? '');
                $ant = MaterialModel::buscarPorId($idm);
                $otro = MaterialModel::buscarPorNombre($nombre);
                if ($otro && $otro['id_material'] !== $idm) self::redirigir('Ya existe otro material con ese nombre.');
                if ($ant && $ant['nombre'] !== $nombre) {
                    $pdo = get_db();
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare("UPDATE gestion_compras SET clasificacion=? WHERE LOWER(TRIM(clasificacion))=LOWER(TRIM(?))")->execute([$nombre, $ant['nombre']]);
                        try { $pdo->prepare("UPDATE Detalle_ventas SET material=? WHERE LOWER(TRIM(material))=LOWER(TRIM(?))")->execute([$nombre, $ant['nombre']]); } catch (PDOException $e) {}
                        $pdo->prepare("UPDATE ventas SET clasificacion=? WHERE clasificacion=?")->execute([$nombre, $ant['nombre']]);
                        $pdo->commit();
                    } catch (Throwable $e) { $pdo->rollBack(); self::redirigir('No se pudo renombrar el material.'); }
                }
                MaterialModel::editar(
                    trim($_POST['id']     ?? ''),
                    $nombre, $categoria,
                    trim($_POST['unidad']    ?? 'kg'),
                    (float)($_POST['precio_kg'] ?? 0),
                    (float)($_POST['stock_max'] ?? 0),
                    (float)($_POST['stock_min'] ?? 0)
                );
                LogsModel::registrar('materiales', 'editar', 'Material editado');
                self::redirigir();
            }

            if ($accion === 'eliminar') {
                $m = MaterialModel::buscarPorId(trim($_POST['id'] ?? ''));
                if ($m) {
                    $pdo = get_db();
                    $q = $pdo->prepare("SELECT COUNT(*) FROM gestion_compras WHERE LOWER(TRIM(clasificacion))=LOWER(TRIM(?))");
                    $q->execute([$m['nombre']]);
                    $uso = (int)$q->fetchColumn();
                    try {
                        $q = $pdo->prepare("SELECT COUNT(*) FROM Detalle_ventas WHERE LOWER(TRIM(material))=LOWER(TRIM(?))");
                        $q->execute([$m['nombre']]);
                        $uso += (int)$q->fetchColumn();
                    } catch (PDOException $e) {}
                    if ($uso > 0) self::redirigir('No se puede eliminar: el material tiene compras o ventas registradas.');
                }
                MaterialModel::eliminar(trim($_POST['id'] ?? ''));
                LogsModel::registrar('materiales', 'eliminar', 'Material eliminado');
                self::redirigir();
            }
        }

        include dirname(__DIR__) . '/views/materiales.php';
    }

    private static function redirigir(string $err = ''): void {
        header('Location: index.php?menu=material&opc=tabla' . ($err !== '' ? '&err=' . urlencode($err) : ''));
        exit;
    }
}