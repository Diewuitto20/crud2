<?php
/* =====================================================================
   controllers/MaterialController.php
   ===================================================================== */
require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/LogsModel.php';

class MaterialController {

    public static function manejar(): void {
        $accion = $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            /* ── Resolver nombre desde el select desplegable ── */
            $nombre = isset($_POST['nombre_sel'])
                ? (trim($_POST['nombre_sel']) === 'Otro'
                    ? trim($_POST['nombre_otro'] ?? '')
                    : trim($_POST['nombre_sel']))
                : trim($_POST['nombre'] ?? '');

            /* ── Resolver categoría (con opción "Otro") ── */
            $categoria = isset($_POST['categoria'])
                ? (trim($_POST['categoria']) === 'Otro'
                    ? trim($_POST['categoria_otro'] ?? '')
                    : trim($_POST['categoria']))
                : '';

            /* ── CREAR ── */
            if ($accion === 'crear') {
                /* Verificar si ya existe un material con el mismo nombre */
                $existente = MaterialModel::buscarPorNombre($nombre);

                if ($existente) {
                    /* Guardar datos en sesión y redirigir con flag de duplicado */
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

            /* ── ACTUALIZAR EXISTENTE (confirmó duplicado) ── */
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

            /* ── CANCELAR DUPLICADO ── */
            if ($accion === 'cancelar_duplicado') {
                unset($_SESSION['duplicado']);
                self::redirigir();
            }

            /* ── EDITAR ── */
            if ($accion === 'editar') {
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

            /* ── ELIMINAR ── */
            if ($accion === 'eliminar') {
                MaterialModel::eliminar(trim($_POST['id'] ?? ''));
                LogsModel::registrar('materiales', 'eliminar', 'Material eliminado');
                self::redirigir();
            }
        }

        include dirname(__DIR__) . '/views/materiales.php';
    }

    private static function redirigir(): void {
        header('Location: index.php?menu=material&opc=tabla');
        exit;
    }
}