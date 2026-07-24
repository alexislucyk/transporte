# Módulo Cuentas - Funcionalidades Propuestas

## Estado Actual

El módulo `modules/cuentas.php` actualmente tiene:

- ✅ CRUD completo de cuentas (bancos, billeteras virtuales, caja efectivo)
- ✅ Ajuste manual de saldo
- ✅ Visualización de movimientos (solo lectura vía AJAX desde `cuentas_movimientos`)
- ✅ Agrupación por tipo con subtotales y total general
- ✅ Multi-tenant estricto (filtra por `transportista_id`)

---

## 🥇 Prioridad Alta (Core funcional)

### 1. Registro de movimientos manuales (ingresos/egresos)
Actualmente solo se **ven** los movimientos, pero no se pueden **crear** salidas (gastos, retiros de efectivo, pagos a proveedores) ni ingresos manuales desde el módulo. El "Ajustar Saldo" no deja trazabilidad.

**Qué se necesita:**
- Formulario para registrar un movimiento manual (fecha, tipo entrada/salida, concepto, monto, observaciones)
- Validación contra el saldo actual (no permitir salir más de lo disponible si se desea)
- Actualización automática del `saldo_actual` en `cuentas_empresa`
- Registro en `cuentas_movimientos` con `saldo_resultante`

### 2. Transferencias entre cuentas
Mover dinero de una cuenta a otra con un solo formulario.

**Qué se necesita:**
- Formulario: cuenta origen, cuenta destino, monto, fecha, concepto
- Registro automático: salida en origen + entrada en destino
- Actualización de saldos en ambas cuentas
- Validación: saldo suficiente en origen, cuentas diferentes

### 3. Filtros por fecha en movimientos
Poder filtrar el historial de movimientos por rango de fechas.

**Qué se necesita:**
- Inputs fecha_desde y fecha_hasta en el modal de movimientos
- Modificar la query AJAX para incluir filtro por rango de fechas
- Mantener los totales de entradas/salidas filtrados

### 4. Exportar movimientos a Excel/CSV
Descargar el listado de movimientos filtrados.

**Qué se necesita:**
- Botón "Exportar CSV" en el modal de movimientos
- Endpoint PHP que genere CSV con los movimientos filtrados (mismos filtros que la consulta)
- Descarga directa del archivo

---

## 🥈 Prioridad Media (Mejoras significativas)

### 5. Categorías de movimientos (gastos)
Clasificar egresos para reportes por categoría.

**Qué se necesita:**
- Tabla `cuentas_categorias` (id, transportista_id, nombre, tipo_por_defecto [entrada/salida/ambos])
- Nuevo campo `categoria_id` en `cuentas_movimientos`
- Selector de categoría al crear movimientos manuales
- Reporte de gastos agrupado por categoría
- Sugerencia de categorías predefinidas: `combustible`, `peaje`, `sueldos`, `impuestos`, `mantenimiento`, `proveedores`, `varios`

### 6. Dashboard visual con gráficos
Visualización de datos financieros.

**Qué se necesita:**
- Gráfico de línea: evolución del saldo total en el tiempo (últimos 12 meses)
- Gráfico de torta/barras: distribución de egresos por categoría (último mes)
- Widget de saldo actual por cuenta
- Biblioteca JS para gráficos (Chart.js recomendada, ya incluida en el sistema)

### 7. Pagos a choferes desde cuentas
Integrar con `chofer_pagos`.

**Qué se necesita:**
- En el formulario de pago a chofer, agregar selector de cuenta origen
- Al registrar el pago a chofer, crear automáticamente un movimiento de salida en la cuenta
- Vincular el movimiento con `referencia_tipo = 'pago_chofer'` y `referencia_id` = ID del pago

### 8. Pagos a comisionistas desde cuentas
Ídem anterior pero con `comisionista_pagos`.

**Qué se necesita:**
- Misma lógica que pagos a choferes pero con comisionistas
- `referencia_tipo = 'pago_comisionista'`

### 9. Alertas de saldo bajo
Notificaciones visuales cuando una cuenta tiene saldo por debajo de un mínimo configurable.

**Qué se necesita:**
- Campo `saldo_minimo` en `cuentas_empresa` (decimal, default 0)
- Al cargar el módulo, verificar si alguna cuenta está por debajo del mínimo
- Mostrar alerta visual (badge rojo) en la cuenta correspondiente
- Opcional: notificación en sidebar/header

---

## 🥉 Prioridad Baja (Nice to have)

### 10. Conciliación bancaria
Marcar movimientos como conciliados vs extracto bancario.

**Qué se necesita:**
- Campo `conciliado` (tinyint) en `cuentas_movimientos`
- Campo `fecha_conciliacion` (date) en `cuentas_movimientos`
- Vista/panel para marcar movimientos como conciliados
- Reporte de diferencias: movimientos no conciliados vs saldo contable

### 11. Archivos adjuntos en movimientos
Subir comprobantes a cada movimiento.

**Qué se necesita:**
- Campo `archivo_adjunto` (varchar) en `cuentas_movimientos` (ruta del archivo)
- Input file en formulario de movimiento
- Almacenar en `uploads/cuentas/`
- Vista previa / descarga del archivo adjunto en la tabla de movimientos

### 12. Historial de ajustes de saldo
Registrar cada ajuste de saldo con auditoría.

**Qué se necesita:**
- Tabla `cuentas_ajustes_saldo` (id, cuenta_id, saldo_anterior, saldo_nuevo, motivo, created_by, created_at)
- Al ejecutar "Ajustar Saldo", insertar registro en esta tabla
- Visualizar historial de ajustes desde el botón de ajuste

### 13. Soporte múltiples monedas (USD)
Cuentas en dólares con tipo de cambio.

**Qué se necesita:**
- Campo `moneda` en `cuentas_empresa` (enum: 'ARS', 'USD', default 'ARS')
- Campo `tipo_cambio` en `cuentas_movimientos` (para movimientos en USD a ARS)
- Mostrar saldo con símbolo de moneda correspondiente
- Conversión a ARS para totales consolidados

### 14. Chequeras / Cheques
Gestión de cheques emitidos.

**Qué se necesita:**
- Tabla `cuentas_cheques` (id, cuenta_id, numero_cheque, monto, beneficiario, fecha_emision, fecha_pago, estado [pendiente/depositado/rechazado/anulado])
- Formulario para registrar cheque emitido
- Al crear, generar movimiento de salida con tipo 'cheque'
- Seguimiento de estado del cheque

### 15. Presupuesto mensual por categoría
Planificar y monitorear gastos.

**Qué se necesita:**
- Tabla `cuentas_presupuesto` (id, transportista_id, categoria_id, mes, anio, monto_limite)
- Al registrar un gasto, verificar contra el presupuesto del mes
- Mostrar progreso (barra de porcentaje) en el dashboard
- Alerta cuando se supera el 80%/100% del presupuesto

---

## 🎯 Recomendación de Implementación

Arrancar con las **4 funcionalidades de prioridad alta** porque:

1. **Movimientos manuales** - Cierra el ciclo: hoy solo se leen movimientos, no se crean
2. **Transferencias** - Operación diaria fundamental (ej: banco → caja)
3. **Filtros por fecha** - Sin esto, el historial es inmanejable con pocos registros
4. **Exportar CSV** - Necesario para contabilidad y auditoría externa