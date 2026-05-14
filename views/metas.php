<?php
require_once __DIR__ . '/data.php';

$titulo_pagina = 'Metas Ambientales';
$pagina_activa = 'metas';

define('METAS_FILE',   __DIR__ . '/metas.json');
define('TICKETS_FILE2', __DIR__ . '/tickets.json');

function metas_leer(): array {
    if (!file_exists(METAS_FILE)) return [];
    $data = json_decode(file_get_contents(METAS_FILE), true);
    return is_array($data) ? $data : [];
}
function metas_guardar(array $m): void {
    file_put_contents(METAS_FILE, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function tickets_leer2(): array {
    if (!file_exists(TICKETS_FILE2)) return [];
    $data = json_decode(file_get_contents(TICKETS_FILE2), true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $all   = metas_leer();
        $all[] = [
            'id'          => uniqid(),
            'nombre'      => trim($_POST['nombre']      ?? ''),
            'material'    => trim($_POST['material']    ?? ''),
            'meta_kg'     => floatval($_POST['meta_kg'] ?? 0),
            'mes'         => intval($_POST['mes']       ?? date('n')),
            'año'         => intval($_POST['año']       ?? date('Y')),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'creado_en'   => date('Y-m-d H:i:s'),
        ];
        metas_guardar($all);
        header('Location: index.php?menu=metas&opc=tabla'); exit;
    }
    if ($action === 'eliminar') {
        $id  = $_POST['id'] ?? '';
        $all = array_values(array_filter(metas_leer(), fn($m) => $m['id'] !== $id));
        metas_guardar($all);
        header('Location: index.php?menu=metas&opc=tabla'); exit;
    }
}

$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$tickets_all = tickets_leer2();

/* Calcular progreso de cada meta */
$metas = [];
foreach (metas_leer() as $m) {
    $actual = array_sum(array_column(
        array_filter($tickets_all, fn($t) =>
            $t['material'] === $m['material'] &&
            (int)date('n', strtotime($t['fecha'])) === (int)$m['mes'] &&
            (int)date('Y', strtotime($t['fecha'])) === (int)$m['año']
        ),
        'peso'
    ));
    $pct    = $m['meta_kg'] > 0 ? min(100, round(($actual / $m['meta_kg']) * 100, 1)) : 0;
    $metas[] = array_merge($m, ['actual' => $actual, 'pct' => $pct, 'mes_nombre' => $meses_es[$m['mes']] ?? '']);
}
usort($metas, fn($a,$b) => ($b['año'] <=> $a['año']) ?: ($b['mes'] <=> $a['mes']));

/* Impacto ambiental acumulado desde tickets */
$por_mat = [];
foreach ($tickets_all as $t) {
    $por_mat[$t['material']] = ($por_mat[$t['material']] ?? 0) + $t['peso'];
}
$kg_pet      = $por_mat['PET']     ?? 0;
$kg_carton   = $por_mat['Cartón']  ?? 0;
$kg_aluminio = $por_mat['Aluminio'] ?? 0;
$kg_papel    = $por_mat['Papel']   ?? 0;
$co2         = ($kg_pet * 1.5) + ($kg_carton * 0.9) + ($kg_aluminio * 9.0) + ($kg_papel * 1.1);
$arboles     = round($co2 / 21, 1);
$agua        = round(($kg_pet * 17) + ($kg_papel * 10));
$total_rec   = array_sum($por_mat);

include 'layout_header.php';
?>

<style>
.progress-bar-wrap{background:#f3f4f6;border-radius:20px;height:14px;overflow:hidden;margin-top:8px}
.progress-bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--green-dark),var(--green-mid));transition:width .6s ease}
.progress-bar-fill.warn{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.progress-bar-fill.dang{background:linear-gradient(90deg,#ef4444,#f87171)}
.progress-bar-fill.succ{background:linear-gradient(90deg,#059669,#10b981)}
.impact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px}
.impact-card{background:linear-gradient(135deg,var(--green-dark),var(--green-mid));border-radius:var(--radius-card);padding:20px;color:#fff;text-align:center;box-shadow:var(--shadow-md)}
.impact-card .ic-icon{font-size:26px;margin-bottom:8px}
.impact-card .ic-value{font-size:20px;font-weight:700}
.impact-card .ic-label{font-size:11px;opacity:.85;margin-top:4px}
.meta-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-card);padding:20px 24px;margin-bottom:14px;box-shadow:var(--shadow-sm)}
.meta-card-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.meta-nums{display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:var(--text-gray)}
</style>

<div class="section-header">
    <div class="section-title"><i class="fa-solid fa-leaf" style="color:var(--green-mid);margin-right:8px;"></i>Metas ambientales</div>
    <button class="btn-primary" onclick="openModal('modal-nueva-meta')"><i class="fa-solid fa-plus"></i> Nueva meta</button>
</div>

<div style="margin-bottom:6px;font-size:12px;font-weight:500;color:var(--text-gray);text-transform:uppercase;letter-spacing:.5px;">
    <i class="fa-solid fa-earth-americas" style="margin-right:6px;"></i>Impacto ambiental acumulado
</div>
<div class="impact-grid">
    <div class="impact-card"><div class="ic-icon">🌿</div><div class="ic-value"><?= number_format($co2,1) ?> kg</div><div class="ic-label">CO₂ evitado</div></div>
    <div class="impact-card"><div class="ic-icon">🌳</div><div class="ic-value"><?= $arboles ?></div><div class="ic-label">Árboles equivalentes</div></div>
    <div class="impact-card"><div class="ic-icon">💧</div><div class="ic-value"><?= number_format($agua) ?> L</div><div class="ic-label">Agua ahorrada</div></div>
    <div class="impact-card"><div class="ic-icon">♻️</div><div class="ic-value"><?= number_format($total_rec,1) ?> kg</div><div class="ic-label">Material reciclado</div></div>
</div>

<div style="margin-bottom:14px;font-size:12px;font-weight:500;color:var(--text-gray);text-transform:uppercase;letter-spacing:.5px;">
    <i class="fa-solid fa-bullseye" style="margin-right:6px;"></i>Metas registradas
</div>

<?php if (empty($metas)): ?>
    <div class="table-card" style="padding:40px;text-align:center;color:var(--text-gray);">
        <i class="fa-solid fa-leaf" style="font-size:36px;color:#d1d5db;margin-bottom:12px;display:block;"></i>
        No hay metas registradas. Crea una para comenzar a medir tu impacto.
    </div>
<?php else: foreach ($metas as $m):
    $cls = $m['pct'] >= 100 ? 'succ' : ($m['pct'] >= 60 ? '' : ($m['pct'] >= 30 ? 'warn' : 'dang'));
?>
    <div class="meta-card">
        <div class="meta-card-head">
            <div>
                <div style="font-weight:600;font-size:15px;"><?= e($m['nombre']) ?></div>
                <div style="font-size:12px;color:var(--text-gray);margin-top:3px;">
                    <span class="badge badge-green"><?= e($m['material']) ?></span>
                    &nbsp;<?= e($m['mes_nombre']) ?> <?= (int)$m['año'] ?>
                    <?php if ($m['descripcion']): ?> · <?= e($m['descripcion']) ?><?php endif; ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;font-weight:700;color:<?= $m['pct']>=100?'#059669':'var(--green-dark)' ?>;">
                    <?= $m['pct'] ?>%
                    <?php if ($m['pct'] >= 100): ?><i class="fa-solid fa-circle-check" style="font-size:16px;"></i><?php endif; ?>
                </span>
                <form method="POST" action="index.php?menu=metas&opc=tabla" style="display:inline;" onsubmit="return confirm('¿Eliminar esta meta?')">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                    <button type="submit" class="btn-icon danger"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        </div>
        <div class="progress-bar-wrap"><div class="progress-bar-fill <?= $cls ?>" style="width:<?= $m['pct'] ?>%"></div></div>
        <div class="meta-nums">
            <span>Recibido: <strong><?= number_format($m['actual'],2) ?> kg</strong></span>
            <span>Meta: <strong><?= number_format($m['meta_kg'],2) ?> kg</strong></span>
            <span>Restante: <strong><?= number_format(max(0,$m['meta_kg']-$m['actual']),2) ?> kg</strong></span>
        </div>
    </div>
<?php endforeach; endif; ?>

<div class="modal-overlay" id="modal-nueva-meta">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa-solid fa-leaf" style="color:var(--green-mid);margin-right:8px;"></i>Nueva meta ambiental</h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-meta')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="index.php?menu=metas&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Nombre de la meta *</label><input type="text" name="nombre" required placeholder="Ej. Recolección mensual PET"></div>
                <div class="form-row">
                    <label>Material *</label>
                    <select name="material" required>
                        <option value="">— Seleccionar —</option>
                        <option>PET</option><option>Cartón</option><option>Aluminio</option>
                        <option>Vidrio</option><option>Papel</option><option>Cobre</option><option>Hierro</option><option>Otro</option>
                    </select>
                </div>
                <div class="form-row"><label>Meta en kg *</label><input type="number" name="meta_kg" step="0.1" min="1" required placeholder="Ej. 500"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-row">
                        <label>Mes *</label>
                        <select name="mes" required>
                            <?php for ($i=1;$i<=12;$i++) echo "<option value='$i'" . (date('n')==$i?' selected':'') . ">{$meses_es[$i]}</option>"; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Año *</label><input type="number" name="año" value="<?= date('Y') ?>" min="2020" max="2099" required></div>
                </div>
                <div class="form-row"><label>Descripción (opcional)</label><textarea name="descripcion" rows="2" placeholder="Detalle de la meta…"></textarea></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-nueva-meta')">Cancelar</button>
                <button type="submit" class="btn-accept"><i class="fa-solid fa-check"></i> Crear meta</button>
            </div>
        </form>
    </div>
</div>

<?php include 'layout_footer.php'; ?>