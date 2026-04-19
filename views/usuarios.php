<?php
require_once __DIR__ . '/data.php';
$pagina_activa = 'usuarios';
$titulo_pagina = 'Usuarios';
require_once __DIR__ . '/layout_header.php';
?>

        <div class="section-header">
            <h2 class="section-title">Usuarios</h2>
            <button class="btn-primary" onclick="openModal('modalUsuario')">
                <i class="fa-solid fa-plus"></i> Nuevo usuario
            </button>
        </div>

        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr); max-width:480px; margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div class="stat-label">Total</div>
                <div class="stat-value"><?= count($usuarios) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-label">Activos</div>
                <div class="stat-value"><?= count(array_filter($usuarios, fn($u) => $u['activo'])) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div class="stat-label">Inactivos</div>
                <div class="stat-value"><?= count(array_filter($usuarios, fn($u) => !$u['activo'])) ?></div>
            </div>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Nombre</th>
                        <th>Correo electrónico</th>
                        <th>Estado</th>
                        <th style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= e($u['id']) ?></td>
                        <td><strong><?= e($u['nombre']) ?></strong></td>
                        <td><a href="mailto:<?= e($u['correo']) ?>" class="email-link"><?= e($u['correo']) ?></a></td>
                        <td>
                            <span class="badge <?= $u['activo'] ? 'badge-green' : 'badge-gray' ?>">
                                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="btn-icon danger" title="Eliminar"><i class="fa-regular fa-trash-can"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<!-- MODAL: USUARIO -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Nuevo usuario</h3>
            <button class="modal-close" onclick="closeModal('modalUsuario')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="../index.php?menu=usuarios&opc=tabla">
            <input type="hidden" name="action" value="crear">
            <div class="modal-body">
                <div class="form-row"><label>Nombre</label><input type="text" name="nombre" placeholder="Nombre completo" required></div>
                <div class="form-row"><label>Correo electrónico</label><input type="email" name="correo" placeholder="correo@ejemplo.com" required></div>
                <div class="form-row"><label>Contraseña</label><input type="password" name="password" placeholder="Mínimo 8 caracteres" required></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalUsuario')">Cancelar</button>
                <button type="submit" class="btn-accept">Crear usuario</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>