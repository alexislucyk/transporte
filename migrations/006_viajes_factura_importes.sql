-- --------------------------------------------------------
-- Migración 006: Importes de Factura (IVA / Total) en viajes
-- --------------------------------------------------------

ALTER TABLE `viajes`
    ADD COLUMN `factura_importe_neto` DECIMAL(15,2) NULL DEFAULT 0.00 AFTER `total_flete_neto`,
    ADD COLUMN `factura_iva_porcentaje` DECIMAL(5,2) NULL DEFAULT 21.00 AFTER `factura_importe_neto`,
    ADD COLUMN `factura_importe_iva` DECIMAL(15,2) NULL DEFAULT 0.00 AFTER `factura_iva_porcentaje`,
    ADD COLUMN `factura_importe_total` DECIMAL(15,2) NULL DEFAULT 0.00 AFTER `factura_importe_iva`;
