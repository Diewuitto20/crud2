<?php
require_once __DIR__ . '/data.php';

$mes_actual  = (int) date('n');
$anio_actual = (int) date('Y');
$nombre_mes  = strtoupper(strftime('%b', mktime(0,0,0,$mes_actual,1,$anio_actual)));
$primer_dia  = (int) date('w', mktime(0,0,0,$mes_actual,1,$anio_actual));
$dias_mes    = (int) date('t', mktime(0,0,0,$mes_actual,1,$anio_actual));
$dia_hoy     = (int) date('j');

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
                    <?= $nombre_mes ?> <span style="color:var(--green-mid)"><?= $anio_actual ?></span>
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
                            $celda = $f * 7 + $c;
                            $es_dia = ($celda >= $primer_dia && $dia <= $dias_mes);
                            $es_hoy = $es_dia && $dia === $dia_hoy;
                        ?>
                        <td class="<?= $es_hoy ? 'today' : '' ?>">
                            <?php if ($es_dia): ?>
                                <span class="day-num"><?= $dia ?></span>
                            <?php $dia++; endif; ?>
                        </td>
                        <?php endfor; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

<!-- MODAL: EVENTO -->
<div class="modal-overlay" id="modalEvento">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Agregar evento</h3>
            <button class="modal-close" onclick="closeModal('modalEvento')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="../index.php?menu=calendario">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Título</label><input type="text" name="titulo" placeholder="Nombre del evento" required></div>
                <div class="form-row"><label>Descripción</label><textarea name="descripcion" rows="3" placeholder="Detalles del evento…"></textarea></div>
                <div class="form-row"><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-row"><label>Hora</label><input type="time" name="hora" value="13:00"></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEvento')">Cancelar</button>
                <button type="submit" class="btn-accept">Añadir evento</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>