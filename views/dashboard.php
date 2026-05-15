<?php
/* =====================================================
   DASHBOARD – Recicladora Diaz
   ===================================================== */
require_once __DIR__ . '/data.php';

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    /* KPI: Stock total */
    $row = $pdo->query("SELECT COALESCE(SUM(stock),0) as total, COUNT(*) as tipos FROM materiales")->fetch();
    $stock_total_kg = (float)$row['total'];
    $stock_tipos    = (int)$row['tipos'];

    /* Alertas stock bajo */
    $alertas = $pdo->query(
        "SELECT nombre, stock, stock_min FROM materiales WHERE stock < stock_min ORDER BY nombre"
    )->fetchAll();

    /* Datos para gráfica de usuarios */
    $row = $pdo->query("SELECT SUM(activo = 1) as activos, SUM(activo = 0) as inactivos FROM usuarios")->fetch();
    $usuarios_activos   = (int)$row['activos'];
    $usuarios_inactivos = (int)$row['inactivos'];

    /* Datos para gráfica de materiales */
    $materiales_grafica = $pdo->query(
        "SELECT nombre, stock, stock_min FROM materiales ORDER BY stock DESC LIMIT 8"
    )->fetchAll();

    /* KPI: Ventas del día — puede fallar si la tabla no existe */
    try {
        $row = $pdo->query("SELECT COALESCE(SUM(total),0) as monto, COUNT(*) as cnt FROM ventas WHERE DATE(fecha)=CURDATE() AND estado='Activa'")->fetch();
        $ventas_dia   = (float)$row['monto'];
        $ventas_trans = (int)$row['cnt'];
    } catch (PDOException $e) { $ventas_dia = 0; $ventas_trans = 0; }

    /* KPI: Compras del día */
    try {
        $row = $pdo->query("SELECT COALESCE(SUM(total),0) as monto, COUNT(*) as cnt FROM compras WHERE DATE(fecha)=CURDATE()")->fetch();
        $compras_dia = (float)$row['monto'];
        $compras_num = (int)$row['cnt'];
    } catch (PDOException $e) { $compras_dia = 0; $compras_num = 0; }

    /* Ganancia neta */
    $ganancia_neta = $ventas_dia - $compras_dia;
    $margen = $ventas_dia > 0 ? round(($ganancia_neta / $ventas_dia) * 100) : 0;

} catch (PDOException $e) {
    $stock_total_kg = 0; $stock_tipos = 0;
    $ventas_dia = 0;     $ventas_trans = 0;
    $compras_dia = 0;    $compras_num = 0;
    $ganancia_neta = 0;  $margen = 0;
    $alertas = [];
    $usuarios_activos = 0; $usuarios_inactivos = 0;
    $materiales_grafica = [];
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

/* Gráficas */
.two-col-dash { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
@media(max-width:700px){ .two-col-dash{ grid-template-columns:1fr; } }

.dash-panel {
    background:var(--white); border-radius:var(--radius-card);
    border:1px solid var(--border); padding:20px;
    box-shadow:var(--shadow-sm); animation:dashUp .35s .28s ease both;
}
.dash-panel-title { font-size:15px; font-weight:700; margin-bottom:16px; color:var(--text-dark); }
.chart-wrap { position:relative; height:260px; }

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

<!-- Gráficas -->
<div class="two-col-dash">

    <!-- Gráfica: Usuarios activos vs inactivos (dona) -->
    <div class="dash-panel">
        <div class="dash-panel-title">Usuarios — Activos vs Inactivos</div>
        <div class="chart-wrap">
            <canvas id="chartUsuarios"></canvas>
        </div>
    </div>

    <!-- Gráfica: Stock por material (barras) -->
    <div class="dash-panel">
        <div class="dash-panel-title">Stock por Material (kg)</div>
        <div class="chart-wrap">
            <canvas id="chartMateriales"></canvas>
        </div>
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

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
/* ── Gráfica dona: Usuarios ── */
const ctxU = document.getElementById('chartUsuarios').getContext('2d');
new Chart(ctxU, {
    type: 'doughnut',
    data: {
        labels: ['Activos', 'Inactivos'],
        datasets: [{
            data: [<?= $usuarios_activos ?>, <?= $usuarios_inactivos ?>],
            backgroundColor: ['#16a34a', '#e5e7eb'],
            borderColor:     ['#15803d', '#d1d5db'],
            borderWidth: 2,
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 13 }, padding: 16 }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.parsed} usuarios`
                }
            }
        }
    }
});

/* ── Gráfica barras: Materiales ── */
const materiales = <?= json_encode(array_column($materiales_grafica, 'nombre')) ?>;
const stocks     = <?= json_encode(array_map(fn($m) => (float)$m['stock'], $materiales_grafica)) ?>;
const stocksMins = <?= json_encode(array_map(fn($m) => (float)$m['stock_min'], $materiales_grafica)) ?>;

const colores = stocks.map((s, i) => s < stocksMins[i] ? '#dc2626' : '#16a34a');

const ctxM = document.getElementById('chartMateriales').getContext('2d');
new Chart(ctxM, {
    type: 'bar',
    data: {
        labels: materiales,
        datasets: [{
            label: 'Stock actual (kg)',
            data: stocks,
            backgroundColor: colores,
            borderRadius: 6,
            borderSkipped: false,
        }, {
            label: 'Stock mínimo (kg)',
            data: stocksMins,
            type: 'line',
            borderColor: '#f97316',
            borderWidth: 2,
            borderDash: [5, 4],
            pointRadius: 4,
            pointBackgroundColor: '#f97316',
            fill: false,
            tension: 0,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 12 }, padding: 12 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { font: { size: 11 } },
                grid: { color: '#f0f2f5' }
            },
            x: {
                ticks: { font: { size: 11 } },
                grid: { display: false }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>