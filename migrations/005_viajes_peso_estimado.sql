-- Migration 005: Agrega columna peso_estimado para separar TN estimadas de TN bruta usada para descarga
-- Nota: weight: peso_neto sigue siendo GENERATED ALWAYS a partir de peso_bruto - peso_tara.

ALTER TABLE viajes
  ADD COLUMN peso_estimado DECIMAL(12,2) DEFAULT 0.00 AFTER fecha_carga;

-- Inicializar peso_estimado con el valor actual de peso_bruto (que hoy representa la TN estimada)
UPDATE viajes
   SET peso_estimado = peso_bruto
 WHERE peso_estimado = 0.00;

