<?php
require_once __DIR__ . '/data.php';

$titulo_pagina = 'Comprobantes';
$pagina_activa = 'tickets';

define('TICKETS_FILE', __DIR__ . '/tickets.json');

function tickets_leer(): array {
    if (!file_exists(TICKETS_FILE)) return [];
    $data = json_decode(file_get_contents(TICKETS_FILE), true);
    return is_array($data) ? $data : [];
}
function tickets_guardar(array $t): void {
    file_put_contents(TICKETS_FILE, json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $all = tickets_leer();
        $all[] = [
            'id'               => uniqid(),
            'folio'            => str_pad(count($all) + 1, 6, '0', STR_PAD_LEFT),
            'fecha'            => date('Y-m-d H:i:s'),
            'donante_nombre'   => trim($_POST['donante_nombre']   ?? ''),
            'donante_telefono' => trim($_POST['donante_telefono'] ?? ''),
            'material'         => trim($_POST['material']         ?? ''),
            'peso'             => floatval($_POST['peso']         ?? 0),
            'unidad'           => trim($_POST['unidad']           ?? 'kg'),
            'notas'            => trim($_POST['notas']            ?? ''),
        ];
        tickets_guardar($all);
        header('Location: index.php?menu=tickets&opc=tabla'); exit;
    }
    if ($action === 'eliminar') {
        $id  = $_POST['id'] ?? '';
        $all = array_values(array_filter(tickets_leer(), fn($t) => $t['id'] !== $id));
        tickets_guardar($all);
        header('Location: index.php?menu=tickets&opc=tabla'); exit;
    }
}

$all_tickets   = tickets_leer();
$total_tickets = count($all_tickets);
$total_kg      = array_sum(array_column($all_tickets, 'peso'));
$hoy           = count(array_filter($all_tickets, fn($t) => substr($t['fecha'],0,10) === date('Y-m-d')));

$buscar  = trim($_GET['buscar'] ?? '');
$tickets = array_reverse($all_tickets);
if ($buscar !== '') {
    $tickets = array_filter($tickets, fn($t) =>
        stripos($t['donante_nombre'], $buscar) !== false ||
        stripos($t['material'], $buscar) !== false
    );
}

$ticket_print = null;
if (!empty($_GET['imprimir'])) {
    foreach ($all_tickets as $t) { if ($t['id'] === $_GET['imprimir']) { $ticket_print = $t; break; } }
}

include 'layout_header.php';
?>

<?php if ($ticket_print): ?>
<style>
@media print { body>*:not(#pa){display:none!important} #pa{display:block!important} #pa .pab{display:none!important} }
#pa{display:none;max-width:380px;margin:40px auto;font-family:'Roboto',sans-serif;border:2px dashed #1a5632;border-radius:14px;padding:28px 32px;color:#1f2937}
#pa .tl{text-align:center;margin-bottom:16px} #pa .tl i{font-size:32px;color:#1a5632}
#pa .te{font-size:18px;font-weight:700;color:#1a5632;text-align:center}
#pa .ts{font-size:11px;color:#6b7280;text-align:center;margin-bottom:20px}
#pa hr{border:none;border-top:1px dashed #d1d5db;margin:14px 0}
#pa .tr{display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:8px}
#pa .tr span:first-child{color:#6b7280} #pa .tr span:last-child{font-weight:500}
#pa .tt{display:flex;justify-content:space-between;font-size:16px;font-weight:700;color:#1a5632;margin-top:8px}
#pa .tf{text-align:center;font-size:11px;color:#9ca3af;margin-top:18px}
#pa .pab{text-align:center;margin-top:24px;display:flex;gap:10px;justify-content:center}
</style>
<div id="pa">
    <div class="tl"><i class="fa-solid fa-recycle"></i></div>
    <div class="te">Recicladora Diaz</div>
    <div class="ts">Comprobante de recepción de material</div>
    <hr>
    <div class="tr"><span>Folio</span><span>#<?= e($ticket_print['folio']) ?></span></div>
    <div class="tr"><span>Fecha</span><span><?= date('d/m/Y H:i', strtotime($ticket_print['fecha'])) ?></span></div>
    <hr>
    <div class="tr"><span>Donante</span><span><?= e($ticket_print['donante_nombre']) ?></span></div>
    <?php if ($ticket_print['donante_telefono']): ?>
    <div class="tr"><span>Teléfono</span><span><?= e($ticket_print['donante_telefono']) ?></span></div>
    <?php endif; ?>
    <hr>
    <div class="tr"><span>Material</span><span><?= e($ticket_print['material']) ?></span></div>
    <div class="tt"><span>Peso recibido</span><span><?= number_format($ticket_print['peso'],2) ?> <?= e($ticket_print['unidad']) ?></span></div>
    <?php if ($ticket_print['notas']): ?><hr><div style="font-size:12px;color:#6b7280;">Notas: <?= e($ticket_print['notas']) ?></div><?php endif; ?>
    <hr>
    <div class="tf">Gracias por contribuir al reciclaje 🌱<br>Recicladora Diaz &copy; <?= date('Y') ?></div>
    <div class="pab">
        <button onclick="window.print()" class="btn-primary"><i class="fa-solid fa-print"></i> Imprimir</button>
        <a href="index.php?menu=tickets&opc=tabla" style="padding:9px 20px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
</div>
<script>window.onload=()=>window.print();</script>
<?php include 'layout_footer.php'; return; endif; ?>

<div class="section-header">
    <div class="section-title"><i class="fa-solid fa-ticket" style="color:var(--green-mid);margin-right:8px;"></i>Comprobantes de recepción</div>
    <button class="btn-primary" onclick="openModal('modal-nuevo-ticket')"><i class="fa-solid fa-plus"></i> Nuevo comprobante</button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total comprobantes</div><div class="stat-value"><?= $total_tickets ?><i class="fa-solid fa-ticket stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Kg totales recibidos</div><div class="stat-value"><?= number_format($total_kg,1) ?><i class="fa-solid fa-weight-hanging stat-icon"></i></div></div>
    <div class="stat-card"><div class="stat-label">Recibidos hoy</div><div class="stat-value"><?= $hoy ?><i class="fa-solid fa-calendar-day stat-icon"></i></div></div>
</div>

<form method="GET" action="index.php" style="margin-bottom:16px;display:flex;gap:10px;">
    <input type="hidden" name="menu" value="tickets"><input type="hidden" name="opc" value="tabla">
    <div class="search-bar" style="width:320px;"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="buscar" placeholder="Buscar donante o material…" value="<?= e($buscar) ?>"></div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-search"></i> Buscar</button>
    <?php if ($buscar): ?><a href="index.php?menu=tickets&opc=tabla" class="btn-cancel" style="padding:9px 18px;border-radius:30px;border:1px solid #e5e7eb;background:#fff;font-size:14px;text-decoration:none;color:#374151;display:inline-flex;align-items:center;">Limpiar</a><?php endif; ?>
</form>

<div class="table-card">
    <table class="data-table">
        <thead><tr><th>Folio</th><th>Fecha</th><th>Donante</th><th>Teléfono</th><th>Material</th><th>Peso</th><th>Notas</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($tickets)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-gray);padding:32px;">No hay comprobantes registrados.</td></tr>
        <?php else: foreach ($tickets as $t): ?>
            <tr>
                <td><span class="badge badge-green">#<?= e($t['folio']) ?></span></td>
                <td><?= date('d/m/Y H:i', strtotime($t['fecha'])) ?></td>
                <td style="font-weight:500;"><?= e($t['donante_nombre']) ?></td>
                <td><?= e($t['donante_telefono'] ?: '—') ?></td>
                <td><?= e($t['material']) ?></td>
                <td><strong><?= number_format($t['peso'],2) ?></strong> <?= e($t['unidad']) ?></td>
                <td style="color:var(--text-gray);font-size:13px;"><?= e($t['notas'] ?: '—') ?></td>
                <td>
                    <a href="index.php?menu=tickets&opc=tabla&imprimir=<?= urlencode($t['id']) ?>" class="btn-icon" title="Imprimir"><i class="fa-solid fa-print"></i></a>
                    <form method="POST" action="index.php?menu=tickets&opc=tabla" style="display:inline;" onsubmit="return confirm('¿Eliminar este comprobante?')">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <button type="submit" class="btn-icon danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal-nuevo-ticket">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa-solid fa-ticket" style="color:var(--green-mid);margin-right:8px;"></i>Nuevo comprobante</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo-ticket')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="index.php?menu=tickets&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Nombre del donante *</label><input type="text" name="donante_nombre" required placeholder="Ej. Juan Pérez"></div>
                <div class="form-row"><label>Teléfono (opcional)</label><input type="text" name="donante_telefono" placeholder="Ej. 2221234567"></div>
                <div class="form-row">
                    <label>Material entregado *</label>
                    <select name="material" required>
                        <option value="">— Seleccionar —</option>
                        <option>PET</option><option>Cartón</option><option>Aluminio</option>
                        <option>Vidrio</option><option>Papel</option><option>Cobre</option><option>Hierro</option><option>Otro</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row"><label>Peso *</label><input type="number" name="peso" step="0.01" min="0.01" required placeholder="0.00"></div>
                    <div class="form-row"><label>Unidad</label><select name="unidad"><option value="kg">kg</option><option value="g">g</option><option value="ton">ton</option></select></div>
                </div>
                <div class="form-row"><label>Notas adicionales</label><textarea name="notas" rows="2" placeholder="Estado del material, observaciones…"></textarea></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-nuevo-ticket')">Cancelar</button>
                <button type="submit" class="btn-accept"><i class="fa-solid fa-check"></i> Guardar comprobante</button>
            </div>
        </form>
    </div>
</div>

<?php include 'layout_footer.php'; ?>