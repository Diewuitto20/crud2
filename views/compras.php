<?php
require_once __DIR__ . '/data.php';
$pagina_activa = 'compras';
$titulo_pagina = 'Registro de compras';
require_once __DIR__ . '/layout_header.php';
?>

        <div class="section-header">
            <h2 class="section-title">Registro de compras</h2>
            <button class="btn-primary" onclick="openModal('modalCompra')">
                <i class="fa-solid fa-plus"></i> Nueva compra
            </button>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Fecha</th>
                        <th>Cantidad</th>
                        <th>Clasificación</th>
                        <th style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $cp): ?>
                    <tr>
                        <td><?= e($cp['id']) ?></td>
                        <td><?= e($cp['fecha']) ?></td>
                        <td><?= e($cp['cantidad']) ?></td>
                        <td><span class="badge badge-green"><?= e($cp['clasificacion']) ?></span></td>
                        <td>
                            <button class="btn-icon" title="Ver detalle"><i class="fa-solid fa-eye"></i></button>
                            <button class="btn-icon danger" title="Eliminar"><i class="fa-regular fa-trash-can"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<!-- MODAL: COMPRA -->
<div class="modal-overlay" id="modalCompra">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Nueva compra</h3>
            <button class="modal-close" onclick="closeModal('modalCompra')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="../index.php?menu=compras&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-row"><label>Cantidad (kg)</label><input type="number" name="cantidad" placeholder="0" min="0" step="0.1" required></div>
                <div class="form-row">
                    <label>Clasificación</label>
                    <select name="clasificacion" required>
                        <option value="">— Selecciona —</option>
                        <option value="PET">PET</option>
                        <option value="HDPE">HDPE</option>
                        <option value="Carton">Cartón</option>
                        <option value="Aluminio">Aluminio</option>
                        <option value="Vidrio">Vidrio</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalCompra')">Cancelar</button>
                <button type="submit" class="btn-accept">Registrar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>