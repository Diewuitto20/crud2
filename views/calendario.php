<?php
require_once __DIR__ . '/data.php';

/* ──  crear / eliminar ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        evento_crear(
            trim($_POST['titulo']       ?? ''),
            trim($_POST['descripcion']  ?? ''),
            $_POST['fecha'] ?? date('Y-m-d'),
            $_POST['hora']  ?? '00:00'
        );
    } elseif ($action === 'eliminar') {
        evento_eliminar($_POST['id'] ?? '');
    }
    header('Location: index.php?menu=calendario');
    exit;
}

/* ── Calendario ── */
$mes_actual  = (int) date('n');
$anio_actual = (int) date('Y');
$primer_dia  = (int) date('w', mktime(0,0,0,$mes_actual,1,$anio_actual));
$dias_mes    = (int) date('t', mktime(0,0,0,$mes_actual,1,$anio_actual));
$dia_hoy     = (int) date('j');


$nombres_meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$nombre_mes = $nombres_meses[$mes_actual];

/* ── Eventos ── */
$todos_eventos   = eventos_leer();
$hoy_str         = date('Y-m-d');
$prefijo_mes     = date('Y-m', mktime(0,0,0,$mes_actual,1,$anio_actual));

// Eventos del mes actual indexados por día
$eventos_por_dia = [];
foreach ($todos_eventos as $ev) {
    if (str_starts_with($ev['fecha'], $prefijo_mes)) {
        $d = (int) substr($ev['fecha'], 8, 2);
        $eventos_por_dia[$d][] = $ev;
    }
}

// Próximos eventos 
$proximos = array_filter($todos_eventos, fn($e) => $e['fecha'] >= $hoy_str);
$proximos = array_slice($proximos, 0, 5);

$pagina_activa = 'calendario';
$titulo_pagina = 'Calendario';
require_once __DIR__ . '/layout_header.php';
?>

<div class="section-header">
    <h2 class="section-title">Calendario</h2>
    <button class="btn-primary" onclick="openModal('modalEvento')">
        <i class="fa-solid fa-plus"></i> Agregar evento
    </button>
</div>

<div class="cal-toolbar">
    <div class="cal-nav">
        <button class="cal-nav-btn"><i class="fa-solid fa-chevron-left"></i></button>
        <div class="cal-month">
            <?= e($nombre_mes) ?> <span style="color:var(--green-mid)"><?= $anio_actual ?></span>
        </div>
        <button class="cal-nav-btn"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
</div>

<div class="cal-grid">
    <table>
        <thead>
            <tr>
                <?php foreach(['DOM','LUN','MAR','MIÉ','JUE','VIE','SÁB'] as $d): ?>
                <th><?= $d ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $dia = 1;
            $total_celdas = $primer_dia + $dias_mes;
            $filas = ceil($total_celdas / 7);
            for ($f = 0; $f < $filas; $f++):
            ?>
            <tr>
                <?php for ($c = 0; $c < 7; $c++):
                    $celda   = $f * 7 + $c;
                    $es_dia  = ($celda >= $primer_dia && $dia <= $dias_mes);
                    $es_hoy  = $es_dia && $dia === $dia_hoy;
                    $tiene_eventos = $es_dia && !empty($eventos_por_dia[$dia]);
                ?>
                <td class="<?= $es_hoy ? 'today' : '' ?>">
                    <?php if ($es_dia): ?>
                        <span class="day-num"><?= $dia ?></span>
                        <?php if ($tiene_eventos): ?>
                            <div class="day-dots">
                                <?php foreach (array_slice($eventos_por_dia[$dia], 0, 3) as $ev): ?>
                                    <span class="day-dot" title="<?= e($ev['titulo']) ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php $dia++; endif; ?>
                </td>
                <?php endfor; ?>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<!-- ── PRÓXIMOS EVENTOS ── -->
<div class="upcoming-section">
    <h3 class="upcoming-title">Próximos Eventos</h3>

    <?php if (empty($proximos)): ?>
        <p class="upcoming-empty">No hay eventos programados</p>
    <?php else: ?>
        <ul class="upcoming-list">
            <?php foreach ($proximos as $ev):
                $fecha_fmt = date('d M Y', strtotime($ev['fecha']));
                $es_hoy_ev = $ev['fecha'] === $hoy_str;
            ?>
            <li class="upcoming-item">
                <div class="upcoming-date <?= $es_hoy_ev ? 'upcoming-date--hoy' : '' ?>">
                    <span class="upcoming-day"><?= date('d', strtotime($ev['fecha'])) ?></span>
                    <span class="upcoming-mon"><?= strtoupper(date('M', strtotime($ev['fecha']))) ?></span>
                </div>
                <div class="upcoming-info">
                    <p class="upcoming-name"><?= e($ev['titulo']) ?></p>
                    <?php if ($ev['descripcion']): ?>
                        <p class="upcoming-desc"><?= e($ev['descripcion']) ?></p>
                    <?php endif; ?>
                    <p class="upcoming-time">
                        <i class="fa-regular fa-clock"></i>
                        <?= e(substr($ev['hora'], 0, 5)) ?> — <?= e($fecha_fmt) ?>
                    </p>
                </div>
                <form method="POST" action="index.php?menu=calendario" style="margin:0">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                    <button type="submit" class="upcoming-del" title="Eliminar evento"
                            onclick="return confirm('¿Eliminar este evento?')">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- ── MODAL EVENTO ── -->
<div class="modal-overlay" id="modalEvento">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Agregar evento</h3>
            <button class="modal-close" onclick="closeModal('modalEvento')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=calendario">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row">
                    <label>Título</label>
                    <input type="text" name="titulo" placeholder="Nombre del evento" required>
                </div>
                <div class="form-row">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3" placeholder="Detalles del evento…"></textarea>
                </div>
                <div class="form-row">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-row">
                    <label>Hora</label>
                    <input type="time" name="hora" value="13:00">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEvento')">Cancelar</button>
                <button type="submit" class="btn-accept">Añadir evento</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>