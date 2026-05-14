<?php
/* =====================================================
   DASHBOARD – Recicladora Diaz
   Archivo: views/dashboard.php
   ===================================================== */

require_once __DIR__ . '/data.php';

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    /* KPI: Stock total — columnas: stock, stock_min */
    $row = $pdo->query("SELECT COALESCE(SUM(stock),0) as total, COUNT(*) as tipos FROM materiales")->fetch();
    $stock_total_kg = (float)$row['total'];
    $stock_tipos    = (int)$row['tipos'];

    /* KPI: Ventas del día — tabla ventas, columna total, fecha */
    $row = $pdo->query("SELECT COALESCE(SUM(total),0) as monto, COUNT(*) as cnt FROM ventas WHERE DATE(fecha)=CURDATE() AND estado='Activa'")->fetch();
    $ventas_dia   = (float)$row['monto'];
    $ventas_trans = (int)$row['cnt'];

    /* KPI: Compras del día — tabla compras, columna total, fecha */
    $row = $pdo->query("SELECT COALESCE(SUM(total),0) as monto, COUNT(*) as cnt FROM compras WHERE DATE(fecha)=CURDATE()")->fetch();
    $compras_dia = (float)$row['monto'];
    $compras_num = (int)$row['cnt'];

    /* Ganancia neta */
    $ganancia_neta = $ventas_dia - $compras_dia;
    $margen = $ventas_dia > 0 ? round(($ganancia_neta / $ventas_dia) * 100) : 0;

    /* Alertas stock bajo — stock < stock_min */
    $alertas = $pdo->query(
        "SELECT nombre, stock, stock_min FROM materiales WHERE stock < stock_min ORDER BY nombre"
    )->fetchAll();

    /* Top 5 materiales en stock */
    $top_materiales = $pdo->query(
        "SELECT nombre, stock, stock_min FROM materiales ORDER BY stock DESC LIMIT 5"
    )->fetchAll();
    $max_kg = !empty($top_materiales) ? max(array_column($top_materiales, 'stock')) : 1;
    if ($max_kg == 0) $max_kg = 1;

    /* Actividad reciente hoy */
    $actividad = $pdo->query(
        "(SELECT 'compra' as tipo, proveedor as desc1, total as monto, fecha FROM compras WHERE DATE(fecha)=CURDATE())
         UNION ALL
         (SELECT 'venta' as tipo, CONCAT('Venta ',id_ventas) as desc1, total as monto, fecha FROM ventas WHERE DATE(fecha)=CURDATE() AND estado='Activa')
         ORDER BY fecha DESC LIMIT 6"
    )->fetchAll();

} catch (PDOException $e) {
    $stock_total_kg = 0; $stock_tipos = 0;
    $ventas_dia = 0;     $ventas_trans = 0;
    $compras_dia = 0;    $compras_num = 0;
    $ganancia_neta = 0;  $margen = 0;
    $alertas = [];       $top_materiales = []; $max_kg = 1; $actividad = [];
}

$titulo_pagina = 'Dashboard';
$pagina_activa = 'dashboard';
require_once __DIR__ . '/layout_header.php';
?>

<style>
.dash-title { font-size:22px; font-weight:700; color:var(--text-dark); margin-bottom:4px; }
.dash-sub   { font-size:13px; color:var(--text-gray); margin-bottom:24px; }

.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media(max-width:900px){ .kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:500px){ .kpi-grid{ grid-template-columns:1fr; } }

.kpi-card {
    background:var(--white); border-radius:var(--radius-card);
    border:1px solid var(--border); padding:18px 20px;
    box-shadow:var(--shadow-sm); display:flex; flex-direction:column; gap:8px;
    animation:dashUp .35s ease both;
}
.kpi-card:nth-child(1){ animation-delay:.05s }
.kpi-card:nth-child(2){ animation-delay:.10s }
.kpi-card:nth-child(3){ animation-delay:.15s }
.kpi-card:nth-child(4){ animation-delay:.20s }

.kpi-top   { display:flex; justify-content:space-between; align-items:flex-start; }
.kpi-label { font-size:12.5px; color:var(--text-gray); font-weight:500; line-height:1.4; max-width:110px; }
.kpi-ico   { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; flex-shrink:0; }
.ico-blue  { background:#2563eb; }
.ico-green { background:#16a34a; }
.ico-purple{ background:#7c3aed; }
.ico-red   { background:#dc2626; }
.kpi-val   { font-size:28px; font-weight:800; letter-spacing:-.5px; line-height:1; color:var(--text-dark); }
.kpi-val span { font-size:16px; font-weight:600; }
.kpi-val.sm   { font-size:20px; }
.kpi-sub2  { font-size:12.5px; color:var(--text-gray); }

.alert-stock {
    background:#fef2f2; border:1.5px solid #fca5a5;
    border-radius:var(--radius-card); padding:16px 20px;
    margin-bottom:20px; animation:dashUp .35s .22s ease both;
}
.alert-stock-title { display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:700; color:#dc2626; margin-bottom:8px; }
.alert-stock ul    { list-style:none; display:flex; flex-direction:column; gap:3px; }
.alert-stock li    { font-size:13px; color:#dc2626; font-weight:500; }

.two-col-dash { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
@media(max-width:700px){ .two-col-dash{ grid-template-columns:1fr; } }

.dash-panel {
    background:var(--white); border-radius:var(--radius-card);
    border:1px solid var(--border); padding:20px;
    box-shadow:var(--shadow-sm); animation:dashUp .35s .28s ease both;
}
.dash-panel-title { font-size:15px; font-weight:700; margin-bottom:16px; color:var(--text-dark); }

.activity-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; height:140px; gap:8px; color:var(--text-gray); font-size:13.5px; }
.activity-empty i { font-size:28px; opacity:.3; }
.activity-list  { list-style:none; display:flex; flex-direction:column; gap:10px; }
.activity-item  { display:flex; align-items:center; gap:12px; padding:10px 12px; background:var(--bg); border-radius:10px; }
.act-icon  { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#fff; flex-shrink:0; }
.act-compra{ background:#7c3aed; }
.act-venta { background:#16a34a; }
.act-info  { flex:1; min-width:0; }
.act-desc  { font-size:13px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.act-monto { font-size:12px; color:var(--text-gray); }

.mat-row  { margin-bottom:12px; }
.mat-row:last-child { margin-bottom:0; }
.mat-meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
.mat-name { font-size:13.5px; font-weight:500; }
.mat-kg   { font-size:13.5px; font-weight:700; }
.mat-kg.red { color:#dc2626; }
.bar-track{ height:7px; background:#e8eaed; border-radius:99px; overflow:hidden; }
.bar-fill { height:100%; border-radius:99px; transition:width .8s cubic-bezier(.4,0,.2,1); }
.bar-green{ background:#16a34a; }
.bar-red  { background:#dc2626; }

.ganancia-banner {
    background:linear-gradient(135deg,#1a5632 0%,#2563eb 100%);
    border-radius:var(--radius-card); padding:24px 28px;
    display:flex; align-items:center; justify-content:space-between;
    color:#fff; box-shadow:var(--shadow-md); animation:dashUp .35s .33s ease both;
}
.gan-left p      { font-size:13px; font-weight:600; opacity:.85; margin-bottom:4px; }
.gan-left .amount{ font-size:32px; font-weight:800; letter-spacing:-.5px; }
.gan-left .marg  { font-size:12.5px; opacity:.7; margin-top:3px; }
.gan-icon        { font-size:60px; opacity:.18; }

@keyframes dashUp {
    from{ opacity:0; transform:translateY(16px); }
    to  { opacity:1; transform:translateY(0); }
}
</style>

<div class="dash-title">Dashboard</div>
<div class="dash-sub">Resumen de operaciones del día</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Materiales en Stock</span>
            <div class="kpi-ico ico-blue"><i class="fa-solid fa-box"></i></div>
        </div>
        <div class="kpi-val"><?= number_format($stock_total_kg) ?> <span>kg</span></div>
        <div class="kpi-sub2"><?= $stock_tipos ?> tipos</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Ventas del Día</span>
            <div class="kpi-ico ico-green"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-val sm">$<?= number_format($ventas_dia, 2) ?></div>
        <div class="kpi-sub2"><?= $ventas_trans ?> transacciones</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Compras del Día</span>
            <div class="kpi-ico ico-purple"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-val sm">$<?= number_format($compras_dia, 2) ?></div>
        <div class="kpi-sub2"><?= $compras_num ?> compras</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Alertas de Inventario</span>
            <div class="kpi-ico ico-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="kpi-val"><?= count($alertas) ?></div>
        <div class="kpi-sub2">Stock bajo mínimo</div>
    </div>
</div>

<!-- Alertas -->
<?php if (!empty($alertas)): ?>
<div class="alert-stock">
    <div class="alert-stock-title"><i class="fa-solid fa-triangle-exclamation"></i> Alertas de Stock Bajo</div>
    <ul>
        <?php foreach ($alertas as $a): ?>
        <li><?= e($a['nombre']) ?>: <?= number_format($a['stock']) ?> kg (mínimo: <?= number_format($a['stock_min']) ?> kg)</li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Actividad + Top materiales -->
<div class="two-col-dash">
    <div class="dash-panel">
        <div class="dash-panel-title">Actividad Reciente</div>
        <?php if (empty($actividad)): ?>
            <div class="activity-empty">
                <i class="fa-regular fa-clock"></i>
                No hay actividad hoy
            </div>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($actividad as $act): ?>
                <li class="activity-item">
                    <div class="act-icon act-<?= e($act['tipo']) ?>">
                        <i class="fa-solid <?= $act['tipo']==='venta' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                    </div>
                    <div class="act-info">
                        <div class="act-desc"><?= e($act['desc1']) ?></div>
                        <div class="act-monto">$<?= number_format($act['monto'],2) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="dash-panel">
        <div class="dash-panel-title">Top 5 Materiales en Stock</div>
        <?php if (empty($top_materiales)): ?>
            <div class="activity-empty">
                <i class="fa-solid fa-box-open"></i>
                Sin materiales registrados
            </div>
        <?php else: ?>
            <?php foreach ($top_materiales as $m):
                $pct    = $max_kg > 0 ? round(($m['stock'] / $max_kg) * 100) : 0;
                $alerta = $m['stock'] < $m['stock_min'];
            ?>
            <div class="mat-row">
                <div class="mat-meta">
                    <span class="mat-name"><?= e($m['nombre']) ?></span>
                    <span class="mat-kg <?= $alerta ? 'red' : '' ?>"><?= number_format($m['stock']) ?> kg</span>
                </div>
                <div class="bar-track">
                    <div class="bar-fill <?= $alerta ? 'bar-red' : 'bar-green' ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Ganancia neta -->
<div class="ganancia-banner">
    <div class="gan-left">
        <p>Ganancia Neta del Día</p>
        <div class="amount">$<?= number_format($ganancia_neta, 2) ?></div>
        <div class="marg">Margen: <?= $margen ?>%</div>
    </div>
    <div class="gan-icon"><i class="fa-solid fa-dollar-sign"></i></div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>