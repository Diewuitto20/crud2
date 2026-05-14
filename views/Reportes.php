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

/* ── Filtros de fecha ── */
$fecha_ini = $_GET['fecha_ini'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

/* ── Datos: Compras  ── */
$stmt_c = $pdo->prepare(
    "SELECT COUNT(*) AS total_reg,
            COALESCE(SUM(kilos), 0)          AS total_kilos,
            COALESCE(SUM(dinero_recibido), 0) AS total_dinero
     FROM gestion_compras
     WHERE fecha BETWEEN :ini AND :fin"
);
$stmt_c->execute([':ini' => $fecha_ini, ':fin' => $fecha_fin]);
$resumen_compras = $stmt_c->fetch();

/* ── Detalle compras por clasificación ── */
$stmt_dc = $pdo->prepare(
    "SELECT clasificacion,
            COUNT(*)          AS registros,
            SUM(kilos)        AS kilos,
            SUM(dinero_recibido) AS dinero
     FROM gestion_compras
     WHERE fecha BETWEEN :ini AND :fin
     GROUP BY clasificacion
     ORDER BY dinero DESC"
);
$stmt_dc->execute([':ini' => $fecha_ini, ':fin' => $fecha_fin]);
$detalle_compras = $stmt_dc->fetchAll();

/* ── Datos: Ventas ── */
$stmt_v = $pdo->prepare(
    "SELECT COUNT(*) AS total_reg,
            COALESCE(SUM(total), 0) AS total_ventas
     FROM ventas
     WHERE DATE(fecha) BETWEEN :ini AND :fin"
);
$stmt_v->execute([':ini' => $fecha_ini, ':fin' => $fecha_fin]);
$resumen_ventas = $stmt_v->fetch();

/* ── Últimas compras del período ── */
$stmt_ult = $pdo->prepare(
    "SELECT id_gestion_venta, fecha, nombre_empresa, clasificacion, kilos, dinero_recibido
     FROM gestion_compras
     WHERE fecha BETWEEN :ini AND :fin
     ORDER BY fecha DESC
     LIMIT 50"
);
$stmt_ult->execute([':ini' => $fecha_ini, ':fin' => $fecha_fin]);
$ultimas_compras = $stmt_ult->fetchAll();

$pagina_activa = 'reportes';
$titulo_pagina = 'Reportes';
require_once __DIR__ . '/layout_header.php';
?>

<style>
.report-filter-bar { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap; }
.report-filter-bar label { font-size:12px; color:var(--text-gray); font-weight:500; display:block; margin-bottom:4px; }
.report-filter-bar input[type="date"] { border:1.5px solid var(--border); border-radius:8px; padding:8px 12px; font-size:14px; outline:none; font-family:inherit; }
.report-filter-bar input[type="date"]:focus { border-color:var(--green-mid); }
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:24px; }
.kpi-card { background:#fff; border-radius:14px; padding:20px; border:1px solid var(--border); display:flex; flex-direction:column; gap:6px; }
.kpi-card .kpi-icon { font-size:22px; color:var(--green-mid); }
.kpi-card .kpi-label { font-size:12px; color:var(--text-gray); font-weight:500; text-transform:uppercase; letter-spacing:.4px; }
.kpi-card .kpi-value { font-size:28px; font-weight:700; color:var(--text-dark); }
.kpi-card .kpi-sub { font-size:12px; color:var(--text-gray); }
.section-block { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:20px; }
.section-block-head { padding:14px 20px; border-bottom:1px solid var(--border); font-weight:600; font-size:15px; color:var(--text-dark); display:flex; align-items:center; gap:10px; }
.section-block-head i { color:var(--green-mid); }
.pdf-btn { background:var(--green-dark); color:#fff; border:none; padding:10px 22px; border-radius:30px; font-size:14px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; transition:background .2s; }
.pdf-btn:hover { background:var(--green-mid); }
</style>

        <div class="section-header">
            <h2 class="section-title">Reportes</h2>
            <a class="pdf-btn"
               href="generar_pdf.php?fecha_ini=<?= urlencode($fecha_ini) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>"
               target="_blank">
                <i class="fa-solid fa-file-pdf"></i> Exportar PDF
            </a>
        </div>

        <!-- Filtros de fecha -->
        <form method="GET" action="index.php" class="report-filter-bar">
            <input type="hidden" name="menu" value="reportes">
            <input type="hidden" name="opc"  value="tabla">
            <div>
                <label>Fecha inicio</label>
                <input type="date" name="fecha_ini" value="<?= e($fecha_ini) ?>">
            </div>
            <div>
                <label>Fecha fin</label>
                <input type="date" name="fecha_fin" value="<?= e($fecha_fin) ?>">
            </div>
            <button type="submit" class="btn-primary" style="height:38px;">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
        </form>

        
        <div class="kpi-grid">
            <div class="kpi-card">
                <i class="fa-solid fa-arrow-down-to-bracket kpi-icon"></i>
                <div class="kpi-label">Total compras (período)</div>
                <div class="kpi-value">$<?= number_format((float)$resumen_compras['total_dinero'], 2) ?></div>
                <div class="kpi-sub"><?= (int)$resumen_compras['total_reg'] ?> registros · <?= number_format((float)$resumen_compras['total_kilos'], 1) ?> kg</div>
            </div>
            <div class="kpi-card">
                <i class="fa-solid fa-arrow-up-from-bracket kpi-icon"></i>
                <div class="kpi-label">Total ventas (período)</div>
                <div class="kpi-value">$<?= number_format((float)($resumen_ventas['total_ventas'] ?? 0), 2) ?></div>
                <div class="kpi-sub"><?= (int)($resumen_ventas['total_reg'] ?? 0) ?> ventas registradas</div>
            </div>
            <div class="kpi-card">
                <i class="fa-solid fa-scale-balanced kpi-icon"></i>
                <div class="kpi-label">Balance (ventas − compras)</div>
                <?php $balance = (float)($resumen_ventas['total_ventas'] ?? 0) - (float)$resumen_compras['total_dinero']; ?>
                <div class="kpi-value" style="color:<?= $balance >= 0 ? '#15803d' : '#dc2626' ?>">
                    $<?= number_format(abs($balance), 2) ?>
                </div>
                <div class="kpi-sub"><?= $balance >= 0 ? 'Ganancia' : 'Pérdida' ?> en el período</div>
            </div>
        </div>

        <!-- Compras por clasificación -->
        <div class="section-block">
            <div class="section-block-head">
                <i class="fa-solid fa-chart-pie"></i> Compras por clasificación
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Clasificación</th>
                        <th>Registros</th>
                        <th>Kilos totales</th>
                        <th>Dinero recibido</th>
                        <th>% del total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detalle_compras)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-gray);padding:20px;">Sin datos en el período.</td></tr>
                    <?php else: ?>
                    <?php foreach ($detalle_compras as $dc): ?>
                    <?php $pct = $resumen_compras['total_dinero'] > 0 ? ($dc['dinero'] / $resumen_compras['total_dinero'] * 100) : 0; ?>
                    <tr>
                        <td><span class="badge badge-green"><?= e($dc['clasificacion']) ?></span></td>
                        <td><?= (int)$dc['registros'] ?></td>
                        <td><?= number_format((float)$dc['kilos'], 2) ?> kg</td>
                        <td>$<?= number_format((float)$dc['dinero'], 2) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;background:#e5e7eb;border-radius:20px;height:6px;">
                                    <div style="width:<?= round($pct) ?>%;background:var(--green-mid);height:6px;border-radius:20px;"></div>
                                </div>
                                <span style="font-size:12px;color:var(--text-gray);min-width:36px;"><?= round($pct, 1) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Últimas compras -->
        <div class="section-block">
            <div class="section-block-head">
                <i class="fa-solid fa-list-check"></i> Detalle de compras del período
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Fecha</th>
                        <th>Empresa</th>
                        <th>Clasificación</th>
                        <th>Kilos</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_compras)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-gray);padding:20px;">Sin registros en el período.</td></tr>
                    <?php else: ?>
                    <?php foreach ($ultimas_compras as $uc): ?>
                    <tr>
                        <td><?= e((string)$uc['id_gestion_venta']) ?></td>
                        <td><?= e($uc['fecha']) ?></td>
                        <td><strong><?= e($uc['nombre_empresa']) ?></strong></td>
                        <td><span class="badge badge-green"><?= e($uc['clasificacion']) ?></span></td>
                        <td><?= number_format((float)$uc['kilos'], 2) ?> kg</td>
                        <td>$<?= number_format((float)$uc['dinero_recibido'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>