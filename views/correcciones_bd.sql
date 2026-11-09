-- Correcciones de la base de datos "classificatore"
-- Antes de ejecutar: Respaldos > Exportar SQL (.zip)
-- Ejecutar en phpMyAdmin > pestaña SQL, de arriba hacia abajo.

-- 1. Unificar collation (evita errores en JOIN por texto) ---------------------
ALTER DATABASE classificatore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE usuarios        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE materiales      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE gestion_compras CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ventas          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE Detalle_ventas  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE comprobantes    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE agenda          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE respaldos       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE auditoria       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;

-- 2. Correo único (la verificación no encontró duplicados) --------------------
ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_correo (correo);

-- 3. Respaldos que apuntan a usuarios ya eliminados ---------------------------
UPDATE respaldos SET usuario_id = NULL
WHERE usuario_id IS NOT NULL AND usuario_id NOT IN (SELECT id_usuario FROM usuarios);

ALTER TABLE respaldos
  ADD CONSTRAINT fk_respaldos_usuario FOREIGN KEY (usuario_id)
  REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
-- Si marca error de tipos incompatibles, omita este ALTER: el UPDATE ya limpió los datos.

-- 4. Tablas viejas sin uso -----------------------------------------------------
-- "carritos" tiene una llave foránea hacia materiales: impediría eliminar materiales.
-- Revise primero que estén vacías o que no necesite su contenido:
SELECT 'carritos' AS tabla, COUNT(*) AS filas FROM carritos
UNION ALL SELECT 'tickets', COUNT(*) FROM tickets
UNION ALL SELECT 'salidas', COUNT(*) FROM salidas
UNION ALL SELECT 'metas',   COUNT(*) FROM metas;

-- Si no los necesita, quite el comentario y ejecute:
-- DROP TABLE IF EXISTS carritos;
-- DROP TABLE IF EXISTS tickets;
-- DROP TABLE IF EXISTS salidas;
-- (metas: la aplicación guarda las metas en metas.json; la tabla no se usa)
-- DROP TABLE IF EXISTS metas;
