ALTER TABLE Detalle_ventas ADD COLUMN material VARCHAR(100) NULL AFTER id_ventas;
ALTER TABLE Detalle_ventas MODIFY cantidad DECIMAL(10,2) NOT NULL;
