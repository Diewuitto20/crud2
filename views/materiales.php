<?php
require_once __DIR__ . '/data.php';
$pagina_activa = 'material';
$titulo_pagina = 'Material';
require_once __DIR__ . '/layout_header.php';
?>

        <div class="section-header">
            <h2 class="section-title">Material</h2>
            <button class="btn-primary" onclick="openModal('modalMaterial')">
                <i class="fa-solid fa-plus"></i> Nuevo material
            </button>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Nombre</th>
                        <th>Precio compra</th>
                        <th>Precio venta</th>
                        <th>Stock actual</th>
                        <th style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiales as $m): ?>
                    <tr>
                        <td><?= e($m['id']) ?></td>
                        <td><strong><?= e($m['nombre']) ?></strong></td>
                        <td>$<?= e($m['precio_compra']) ?></td>
                        <td>$<?= e($m['precio_venta']) ?></td>
                        <td><?= e($m['stock']) ?></td>
                        <td>
                            <button class="btn-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="btn-icon danger" title="Eliminar"><i class="fa-regular fa-trash-can"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<!-- MODAL: MATERIAL -->
<div class="modal-overlay" id="modalMaterial">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Nuevo material</h3>
            <button class="modal-close" onclick="closeModal('modalMaterial')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="../index.php?menu=material&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Nombre del material</label><input type="text" name="nombre" placeholder="Ej: Cartón, PET…" required></div>
                <div class="form-row"><label>Precio de compra ($)</label><input type="number" name="precio_compra" placeholder="0.00" step="0.01" min="0" required></div>
                <div class="form-row"><label>Precio de venta ($)</label><input type="number" name="precio_venta" placeholder="0.00" step="0.01" min="0" required></div>
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
                <button type="button" class="btn-cancel" onclick="closeModal('modalMaterial')">Cancelar</button>
                <button type="submit" class="btn-accept">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>