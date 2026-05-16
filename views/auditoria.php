<?php
require_once __DIR__ . '/data.php';

$titulo_pagina = 'Auditoría';
$pagina_activa = 'auditoria';

define('AUDITORIA_FILE', __DIR__ . '/auditoria.json');

function auditoria_leer(): array {
    if (!file_exists(AUDITORIA_FILE)) return [];
    $data = json_decode(file_get_contents(AUDITORIA_FILE), true);
    return is_array($data) ? $data : [];
}

/* Nota: registrar_auditoria() ya está definida en data.php con PDO.
   Para JSON, usamos esta versión alternativa: */
function registrar_log(string $modulo, string $accion, string $descripcion = ''): void {
    $logs   = auditoria_leer();
    $logs[] = [
        'id'             => uniqid(),
        'fecha'          => date('Y-m-d H:i:s'),
        'usuario_nombre' => $_SESSION['nombre'] ?? 'sistema',
        'modulo'         => $modulo,
        'accion'         => $accion,
        'descripcion'    => $descripcion,
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? '—',
    ];
    // Mantener solo los últimos 1000 registros
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    file_put_contents(AUDITORIA_FILE, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* ── Filtros ── */
$filtro_modulo  = trim($_GET['modulo']  ?? '');
$filtro_usuario = trim($_GET['usuario'] ?? '');
$filtro_fecha   = trim($_GET['fecha']   ?? '');
$buscar         = trim($_GET['buscar']  ?? '');

$all_logs = array_reverse(auditoria_leer()); // más reciente primero

$logs = array_filter($all_logs, function($l) use ($filtro_modulo, $filtro_usuario, $filtro_fecha, $buscar) {
    if ($filtro_modulo  && $l['modulo'] !== $filtro_modulo)                       return false;
    if ($filtro_usuario && stripos($l['usuario_nombre'], $filtro_usuario) === false) return false;
    if ($filtro_fecha   && substr($l['fecha'], 0, 10) !== $filtro_fecha)          return false;
    if ($buscar         && stripos($l['descripcion'], $buscar) === false)          return false;
    return true;
});
$logs = array_slice($logs, 0, 500);

/* ── Stats ── */
$total_logs   = count($all_logs);
$hoy_logs     = count(array_filter($all_logs, fn($l) => substr($l['fecha'],0,10) === date('Y-m-d')));
$modulos_list = array_unique(array_column($all_logs, 'modulo'));
sort($modulos_list);
$usuarios_u   = count(array_unique(array_column($all_logs, 'usuario_nombre')));

include 'layout_header.php';

function badge_accion(string $accion): string {
    $map = [
        'crear'    => 'background:#dcfce7;color:#15803d;',
        'editar'   => 'background:#dbeafe;color:#1d4ed8;',
        'eliminar' => 'background:#fee2e2;color:#dc2626;',
        'login'    => 'background:#f3e8ff;color:#7c3aed;',
        'logout'   => 'background:#fef9c3;color:#92400e;',
        'imprimir' => 'background:#e0f2fe;color:#0369a1;',
    ];
    $style = $map[strtolower(trim($accion))] ?? 'background:#f3f4f6;color:#6b7280;';
    return "<span class='badge' style='$style'>" . htmlspecialchars($accion, ENT_QUOTES, 'UTF-8') . "</span>";
}
?>

<style>
.filter-bar{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-card);padding:16px 20px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;box-shadow:var(--shadow-sm)}
.filter-bar .form-row{margin:0;min-width:150px;flex:1}
.filter-bar label{font-size:12px;color:var(--text-gray);font-weight:500;display:block;margin-bottom:4px}
.filter-bar input,.filter-bar select{border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;width:100%;font-family:inherit}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--green-mid)}
.log-ip{font-size:11px;color:var(--text-gray);font-family:monospace}
</style>

<div class="section-header">
    <div class="section-title"><i class="fa-solid fa-clipboard-list" style="color:var(--green-mid);margin-right:8px;"></i>Logs</div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total eventos</div><div class="stat-value"><?= number_format($total_logs) ?><i class="fa-solid fa-list stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Eventos hoy</div><div class="stat-value"><?= $hoy_logs ?><i class="fa-solid fa-calendar-day stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Módulos</div><div class="stat-value"><?= count($modulos_list) ?><i class="fa-solid fa-layer-group stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Usuarios activos</div><div class="stat-value"><?= $usuarios_u ?><i class="fa-solid fa-users stat-icon"></i></div></div>
</div>

<form method="GET" action="index.php">
    <input type="hidden" name="menu" value="auditoria"><input type="hidden" name="opc" value="tabla">
    <div class="filter-bar">
        <div class="form-row"><label>Buscar descripción</label><input type="text" name="buscar" placeholder="Texto libre…" value="<?= e($buscar) ?>"></div>
        <div class="form-row">
            <label>Módulo</label>
            <select name="modulo">
                <option value="">— Todos —</option>
                <?php foreach ($modulos_list as $mod): ?>
                    <option value="<?= e($mod) ?>" <?= $filtro_modulo===$mod?'selected':'' ?>><?= e(ucfirst($mod)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row"><label>Usuario</label><input type="text" name="usuario" placeholder="Nombre…" value="<?= e($filtro_usuario) ?>"></div>
        <div class="form-row"><label>Fecha</label><input type="date" name="fecha" value="<?= e($filtro_fecha) ?>"></div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
            <a href="index.php?menu=auditoria&opc=tabla" class="btn-cancel" style="padding:9px 16px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;white-space:nowrap;">Limpiar</a>
        </div>
    </div>
</form>

<div class="table-card">
    <table class="data-table">
        <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Descripción</th><th>IP</th></tr></thead>
        <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-gray);padding:32px;">No hay registros con los filtros seleccionados.</td></tr>
        <?php else: foreach ($logs as $l): ?>
            <tr>
                <td style="white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?></td>
                <td style="font-weight:500;"><?= e($l['usuario_nombre'] ?? '—') ?></td>
                <td><span class="badge badge-gray"><?= e(ucfirst($l['modulo'] ?? '—')) ?></span></td>
                <td><?= badge_accion($l['accion'] ?? 'otro') ?></td>
                <td style="max-width:320px;font-size:13px;"><?= e($l['descripcion'] ?? '') ?></td>
                <td class="log-ip"><?= e($l['ip'] ?? '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (count($logs) >= 500): ?>
<p style="text-align:center;color:var(--text-gray);font-size:13px;margin-top:12px;">
    <i class="fa-solid fa-circle-info"></i> Se muestran hasta 500 registros. Usa los filtros para afinar la búsqueda.
</p>
<?php endif; ?>

<?php include 'layout_footer.php'; ?>