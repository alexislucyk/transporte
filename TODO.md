# TODO - trans_dev

- [ ] Mostrar bloque “Datos de Facturación (para el viaje)” en la card “Datos Operativos”
  - [ ] Confirmar que el endpoint `get_viaje_info` trae los campos necesarios para: Cliente, Tarifa (x ton), Flete Neto, Flete Bruto/Importe total, CP, Estado, Fecha cobro.
  - [ ] Corregir el mapeo JS en `cobranzas_fletes_liquidar.php` para usar los nombres reales de columnas (ej: `cliente_razon_social`, `tarifa_tonelada`, `total_flete_neto`, `total_flete_bruto`, `carta_porte_nro`, `estado`, `fecha_cobro`).
  - [ ] Asegurar que si falta alguno, muestre “-” sin romper el HTML.
- [ ] Validar visualmente en `?route=cobranzas_fletes_liquidar&viaje_id=7`.

