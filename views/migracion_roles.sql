ALTER TABLE usuarios ADD COLUMN rol VARCHAR(10) NOT NULL DEFAULT 'empleado';
-- Ajuste el id del dueño real:
UPDATE usuarios SET rol = 'dueno' WHERE id_usuario = 1;
