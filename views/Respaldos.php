<?php

require_once __DIR__ . '/data.php';

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

/* ── Acciones ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    /* Generar respaldo JSON y descargarlo */
    if ($accion === 'descargar_json') {
        $datos = [
            'fecha_respaldo' => date('Y-m-d H:i:s'),
            'materiales'     => materiales_leer(),
            'compras'        => compras_leer(),
            'eventos'        => eventos_leer(),
            'usuarios'       => $pdo->query("SELECT id_usuario,nombre,apellido_paterno,apellido_materno,correo,activo FROM usuarios")->fetchAll(),
            'respaldos'      => $pdo->query("SELECT * FROM Respaldos ORDER BY fecha DESC")->fetchAll(),
        ];
        $json     = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'respaldo_' . date('Ymd_His') . '.json';

        /* Registrar en BD */
        $uid  = $_SESSION['id_usuario'] ?? 1;
        $size = round(strlen($json) / 1024 / 1024, 2);
        $pdo->prepare("INSERT INTO Respaldos (fecha, hora_modificacion, descripcion, usuario_creacion, usuario_ultima_modificacion)
                       VALUES (CURDATE(), CURTIME(), :desc, :uid, :uid)")
            ->execute([':desc' => "Respaldo JSON generado ({$size} MB)", ':uid' => $uid]);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    /* Exportar CSV de compras */
    if ($accion === 'exportar_csv') {
        $compras  = compras_leer();
        $filename = 'compras_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM para Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Fecha','Material','Proveedor','Cantidad (kg)','Precio/kg','Total']);
        foreach ($compras as $c) {
            fputcsv($out, [
                $c['id'], $c['fecha'], $c['material'],
                $c['proveedor'], $c['cantidad'], $c['precio_kg'], $c['total']
            ]);
        }
        fclose($out);
        exit;
    }

    /* Eliminar respaldo */
    if ($accion === 'eliminar_respaldo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM Respaldos WHERE id_respaldo=:id")->execute([':id' => $id]);
        }
        header('Location: index.php?menu=respaldos&opc=tabla');
        exit;
    }
}

/* ── Datos ── */
$respaldos = $pdo->query(
    "SELECT r.id_respaldo, r.fecha, r.hora_modificacion, r.descripcion,
            u1.nombre AS creado_por
     FROM Respaldos r
     LEFT JOIN usuarios u1 ON u1.id_usuario = r.usuario_creacion
     ORDER BY r.fecha DESC, r.hora_modificacion DESC"
)->fetchAll();

$total_respaldos = count($respaldos);
$ultimo          = $total_respaldos > 0 ? $respaldos[0] : null;

/* Resumen de datos */
$total_materiales = count(materiales_leer());
$total_compras    = count(compras_leer());
$total_eventos    = count(eventos_leer());
$total_usuarios   = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

$pagina_activa = 'respaldos';
$titulo_pagina = 'Respaldos';
require_once __DIR__ . '/layout_header.php';
?>

        <div class="section-header">
            <div>
                <h2 class="section-title">Respaldos</h2>
                <p style="font-size:13px;color:var(--text-gray);margin-top:2px">Gestión de copias de seguridad</p>
            </div>
        </div>

        <!-- ── TARJETAS DE ACCIÓN ── -->
        <div class="resp-action-grid">

            <!-- Respaldo JSON -->
            <div class="resp-action-card resp-action-card--green">
                <div class="resp-action-top">
                    <div>
                        <p class="resp-action-title">Respaldo Completo</p>
                        <p class="resp-action-desc">Exporta todos los datos del sistema en formato JSON</p>
                    </div>
                    <i class="fa-solid fa-hard-drive resp-action-icon"></i>
                </div>
                <form method="POST" action="index.php?menu=respaldos&opc=tabla">
                    <input type="hidden" name="action" value="descargar_json">
                    <button type="submit" class="resp-action-btn">
                        <i class="fa-solid fa-download"></i> Generar Respaldo JSON
                    </button>
                </form>
            </div>

            <!-- Exportar CSV -->
            <div class="resp-action-card resp-action-card--blue">
                <div class="resp-action-top">
                    <div>
                        <p class="resp-action-title">Exportar Reportes</p>
                        <p class="resp-action-desc">Descarga reportes en formato Excel/CSV</p>
                    </div>
                    <i class="fa-solid fa-file-lines resp-action-icon"></i>
                </div>
                <form method="POST" action="index.php?menu=respaldos&opc=tabla">
                    <input type="hidden" name="action" value="exportar_csv">
                    <button type="submit" class="resp-action-btn resp-action-btn--blue">
                        <i class="fa-solid fa-download"></i> Exportar CSV
                    </button>
                </form>
            </div>

        </div>

        <!-- ── RESUMEN DE DATOS ── -->
        <div class="resp-summary-card">
            <h3 class="resp-summary-title">Resumen de Datos</h3>
            <div class="resp-summary-grid">
                <div class="resp-summary-item">
                    <p class="resp-summary-label">Materiales</p>
                    <p class="resp-summary-val"><?= $total_materiales ?></p>
                </div>
                <div class="resp-summary-item">
                    <p class="resp-summary-label">Eventos</p>
                    <p class="resp-summary-val"><?= $total_eventos ?></p>
                </div>
                <div class="resp-summary-item">
                    <p class="resp-summary-label">Compras</p>
                    <p class="resp-summary-val"><?= $total_compras ?></p>
                </div>
                <div class="resp-summary-item">
                    <p class="resp-summary-label">Usuarios</p>
                    <p class="resp-summary-val"><?= $total_usuarios ?></p>
                </div>
            </div>
        </div>

        <!-- ── HISTORIAL ── -->
        <div class="resp-historial-card">
            <h3 class="resp-summary-title">Historial de Respaldos</h3>

            <?php if (empty($respaldos)): ?>
                <p style="text-align:center;color:var(--text-gray);padding:24px 0">No hay respaldos registrados.</p>
            <?php else: ?>
            <ul class="resp-list">
                <?php foreach ($respaldos as $r):
                    $fecha_fmt = date('d \d\e F \d\e Y, h:i a', strtotime($r['fecha'] . ' ' . $r['hora_modificacion']));
                ?>
                <li class="resp-list-item">
                    <div class="resp-list-icon">
                        <i class="fa-solid fa-hard-drive"></i>
                    </div>
                    <div class="resp-list-info">
                        <p class="resp-list-name">
                            <?= e($r['descripcion'] ?: 'Respaldo Manual') ?>
                        </p>
                        <p class="resp-list-date">
                            <i class="fa-regular fa-calendar"></i>
                            <?= e($fecha_fmt) ?>
                            <?php if ($r['creado_por']): ?>
                                · <span style="color:var(--green-mid)"><?= e($r['creado_por']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <form method="POST" action="index.php?menu=respaldos&opc=tabla"
                          style="margin:0"
                          onsubmit="return confirm('¿Eliminar este respaldo del historial?')">
                        <input type="hidden" name="action" value="eliminar_respaldo">
                        <input type="hidden" name="id" value="<?= (int)$r['id_respaldo'] ?>">
                        <button type="submit" class="resp-del-btn" title="Eliminar">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- ── RECOMENDACIONES ── -->
        <div class="resp-rec-card">
            <p class="resp-rec-title">
                <i class="fa-solid fa-hard-drive"></i> Recomendaciones
            </p>
            <ul class="resp-rec-list">
                <li>Genera un respaldo completo al menos una vez por semana.</li>
                
            </ul>
        </div>

<style>
/* Tarjetas de acción */
.resp-action-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
.resp-action-card { border-radius:var(--radius-card); padding:22px 24px; display:flex; flex-direction:column; gap:18px; }
.resp-action-card--green { background:#16a34a; }
.resp-action-card--blue  { background:#2563eb; }
.resp-action-top  { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.resp-action-title { color:#fff; font-size:17px; font-weight:700; margin-bottom:6px; }
.resp-action-desc  { color:rgba(255,255,255,.8); font-size:13px; }
.resp-action-icon  { color:rgba(255,255,255,.7); font-size:28px; flex-shrink:0; }
.resp-action-btn {
    width:100%; background:#fff; border:none; border-radius:10px;
    padding:11px 0; font-size:14px; font-weight:600; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:opacity .15s;
}
.resp-action-btn       { color:#16a34a; }
.resp-action-btn--blue { color:#2563eb; }
.resp-action-btn:hover { opacity:.88; }

/* Resumen */
.resp-summary-card { background:var(--white); border-radius:var(--radius-card); border:1px solid var(--border); padding:22px 24px; margin-bottom:24px; box-shadow:var(--shadow-sm); }
.resp-summary-title { font-size:15px; font-weight:600; margin-bottom:18px; color:var(--text-dark); }
.resp-summary-grid  { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.resp-summary-item  { background:var(--bg); border-radius:10px; padding:14px 16px; border:1px solid var(--border); }
.resp-summary-label { font-size:12px; color:var(--text-gray); margin-bottom:4px; }
.resp-summary-val   { font-size:26px; font-weight:700; color:var(--text-dark); }

/* Historial */
.resp-historial-card { background:var(--white); border-radius:var(--radius-card); border:1px solid var(--border); padding:22px 24px; margin-bottom:24px; box-shadow:var(--shadow-sm); }
.resp-list      { list-style:none; display:flex; flex-direction:column; gap:4px; }
.resp-list-item { display:flex; align-items:center; gap:14px; padding:14px 12px; border-radius:10px; transition:background .15s; }
.resp-list-item:hover { background:var(--bg); }
.resp-list-icon { width:40px; height:40px; border-radius:10px; background:var(--green-light); color:var(--green-dark); display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.resp-list-info { flex:1; min-width:0; }
.resp-list-name { font-weight:600; font-size:14px; margin:0 0 3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.resp-list-date { font-size:12px; color:var(--text-gray); margin:0; }
.resp-list-date i { margin-right:4px; }
.resp-del-btn { background:none; border:none; cursor:pointer; color:#ccc; font-size:14px; padding:6px 8px; border-radius:6px; transition:color .15s, background .15s; }
.resp-del-btn:hover { color:#dc2626; background:#fef2f2; }

/* Recomendaciones */
.resp-rec-card  { background:#fffbeb; border:1px solid #fde68a; border-radius:var(--radius-card); padding:18px 22px; }
.resp-rec-title { font-size:14px; font-weight:600; color:#b45309; margin-bottom:10px; }
.resp-rec-title i { margin-right:6px; }
.resp-rec-list  { padding-left:18px; display:flex; flex-direction:column; gap:5px; }
.resp-rec-list li { font-size:13px; color:#92400e; }

@media (max-width:700px) {
    .resp-action-grid  { grid-template-columns:1fr; }
    .resp-summary-grid { grid-template-columns:repeat(2,1fr); }
}
</style>

<?php require_once __DIR__ . '/layout_footer.php'; ?>