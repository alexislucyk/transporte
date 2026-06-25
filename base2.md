# ERP TRANSPORTE DE CARGAS ARGENTINA

## Descripción General

Sistema ERP web para la gestión integral de empresas de transporte de cargas en Argentina.

El sistema debe permitir administrar una o múltiples empresas desde una única instalación, manteniendo el aislamiento total de información entre empresas.

La aplicación deberá ser desarrollada utilizando:

* PHP 8+
* MySQL/MariaDB
* HTML5
* CSS puro
* JavaScript Vanilla
* Arquitectura MVC
* Sin frameworks pesados

---

# MULTIEMPRESA

## Objetivo

La aplicación debe soportar múltiples empresas.

Todos los registros deberán estar asociados a una empresa mediante:

```sql
empresa_id
```

Los datos de una empresa nunca podrán ser visibles para otra empresa.

Las entidades que deberán pertenecer a una empresa son:

* Usuarios
* Clientes
* Comisionistas
* Choferes
* Vehículos
* Acoplados
* Viajes
* Facturas
* Cobros
* Cuentas corrientes
* Gastos
* Configuraciones

---

# ROLES Y PERMISOS

## Developer

Rol con acceso total al sistema.

Permisos:

* Acceso completo a todas las empresas.
* Acceso completo a todos los usuarios.
* Crear administradores.
* Modificar cualquier registro.
* Configurar el sistema globalmente.
* Ver auditorías.

---

## Administrador

Solo puede ser creado por un Developer.

Permisos:

* Gestionar exclusivamente su empresa.
* Crear usuarios.
* Asignar permisos.
* Gestionar clientes.
* Gestionar choferes.
* Gestionar vehículos.
* Gestionar viajes.
* Gestionar facturación.
* Gestionar cobranzas.

Restricciones:

* No puede visualizar otras empresas.
* No puede visualizar usuarios de otras empresas.

---

## Usuario

Acceso limitado según permisos asignados.

Los permisos deben poder configurarse por módulo y acción.

Ejemplo:

* Ver viajes
* Crear viajes
* Editar viajes
* Eliminar viajes
* Facturación
* Cobranza
* Tesorería
* Reportes

---

# MÓDULO VIAJES

## Registro Inicial

Datos obligatorios:

### Cliente

Empresa o persona para quien se realiza el transporte.

### Vehículo

* Camión
* Acoplado

### Chofer

Asignado al vehículo.

### Carga

* Producto
* Origen
* Destino

### Documentación

Tipo de documento:

* CTG
* Carta de Porte
* Remito

Número del documento.

### Facturación

* Pagador del flete
* Tarifa por tonelada
* Peso estimado

### Comisión

* Comisionista
* Tipo de comisión

  * Porcentaje
  * Monto fijo

---

## Estados del Viaje

```text
Pendiente
En Curso
Descargado
En Liquidación
Facturado
Cobrado
Cerrado
```

---

## Gastos del Viaje

Los gastos deben seleccionarse desde una tabla parametrizable.

Ejemplos:

* Combustible
* Peajes
* Viáticos
* Reparaciones
* Lavado
* Balanza

Cada gasto debe registrar:

* Fecha
* Tipo
* Importe
* Observación

---

## Adelantos al Chofer

Registrar:

* Fecha
* Importe
* Observación

Los adelantos podrán utilizarse para:

* Cubrir gastos del viaje.
* Anticipar ganancias del chofer.

---

## Descarga

Al finalizar el viaje registrar:

### Opción A

Toneladas netas descargadas.

### Opción B

* Peso bruto
* Peso tara

Calcular automáticamente:

```text
Toneladas Netas = Bruto - Tara
```

---

## Cálculos Automáticos

### Monto a Facturar

```text
Toneladas Descargadas × Tarifa
```

### Diferencia de Peso

```text
Toneladas Descargadas - Toneladas Estimadas
```

Mostrar:

* Faltante
* Sobrante

---

## Ganancia del Chofer

Cada chofer tendrá configurado:

```text
Porcentaje de Participación
```

Ejemplo:

10%

Si el viaje factura:

```text
$1.000.000
```

La ganancia será:

```text
$100.000
```

Generar automáticamente un movimiento en la cuenta corriente del chofer:

```text
Tipo: HABER
Concepto: Ganancia de Viaje
```

---

## Resumen del Viaje

Mostrar:

* Número de viaje
* Estado
* Cliente
* Producto
* Origen
* Destino
* Documento
* Tarifa
* Peso estimado
* Peso descargado
* Diferencia
* Total estimado
* Total facturado

---

# MÓDULO LIQUIDACIÓN

Permitir:

* Modificar información del viaje.
* Agregar gastos.
* Agregar adelantos.
* Ver cálculos finales.

---

## Adelantos Sobrantes

Si sobra dinero entregado al chofer:

Generar movimiento:

```text
Tipo: DEBE
Concepto: Adelanto Pendiente de Rendición
```

---

## Facturación

Registrar:

* Fecha de factura
* Tipo de comprobante
* Punto de venta
* Número de factura

Calcular automáticamente:

```text
Toneladas Descargadas × Tarifa
```

---

## Factura Individual

Una factura asociada a un único viaje.

---

## Factura Múltiple

Permitir asociar varios viajes a una factura.

Regla obligatoria:

Todos los viajes deben tener el mismo pagador de flete.

Si los pagadores son distintos:

```text
No permitir la agrupación.
```

---

# MÓDULO COBRANZAS

Registrar el cobro de facturas.

---

## Descuentos

Permitir registrar:

* Retenciones
* IIBB
* Otros descuentos

---

## Medios de Cobro

* Efectivo
* Transferencia
* Cheque
* E-Cheq

---

## Datos de Cheque

Registrar:

* Banco
* Número
* Emisor
* CUIT
* Fecha de emisión
* Fecha de cobro
* Importe

---

## Resultado del Cobro

Generar automáticamente un ingreso en la cuenta financiera de la empresa.

---

# MÓDULO CLIENTES

Permitir registrar:

## Personas

* Nombre
* Apellido
* DNI
* CUIL
* Dirección
* Teléfono
* Email

## Empresas

* Razón Social
* CUIT
* Dirección
* Contacto
* Teléfono
* Email

---

## Comisionistas

Un cliente puede ser marcado como:

```text
Es Comisionista = Sí
```

---

# MÓDULO CHOFERES

Datos mínimos:

* Nombre
* Apellido
* DNI
* CUIL
* Dirección
* Teléfono
* Fecha de ingreso
* Porcentaje de participación
* Estado

---

## Cuenta Corriente de Chofer

Movimientos:

### HABER

* Ganancias por viajes

### DEBE

* Adelantos
* Ajustes
* Descuentos

Mostrar:

* Saldo actual
* Historial completo

---

# MÓDULO VEHÍCULOS

## Camiones

* Patente
* Marca
* Modelo
* Año
* Capacidad
* Estado

## Acoplados

* Patente
* Tipo
* Capacidad
* Estado

---

# MÓDULO TESORERÍA

Administración de fondos de la empresa.

---

## Cuentas

Permitir múltiples cuentas:

* Caja Efectivo
* Cuenta Bancaria
* Billetera Virtual
* Otras

---

## Movimientos

### Ingresos

* Cobro de facturas
* Ajustes

### Egresos

* Gastos
* Adelantos
* Pagos

---

# DASHBOARD PRINCIPAL

La pantalla principal debe mostrar información resumida y de fácil lectura.

---

## Indicadores

* Viajes en curso
* Viajes pendientes de liquidación
* Facturas emitidas
* Facturas pendientes de cobro
* Facturas vencidas
* Cobros del mes
* Gastos del mes
* Ganancia estimada

---

## Próximos Cobros

Mostrar:

* Cliente
* Factura
* Fecha estimada de cobro
* Importe

---

## Últimos Viajes

Tabla con los viajes más recientes.

---

## Alertas

Mostrar alertas automáticas para:

* Facturas vencidas
* Cheques próximos a cobrar
* Cheques vencidos
* Documentación próxima a vencer

---

# AUDITORÍA

Registrar automáticamente:

* Usuario
* Fecha y hora
* Acción realizada
* Tabla afectada
* Registro afectado
* Valor anterior
* Valor nuevo

Toda modificación importante debe quedar auditada.

---

# TABLAS PRINCIPALES

```text
empresas
usuarios
roles
permisos

clientes
choferes

vehiculos
acoplados

viajes
viajes_gastos
viajes_adelantos

facturas
facturas_viajes

cobranzas

cuentas_empresa
movimientos_empresa

cuentas_choferes
movimientos_choferes

tipos_gastos

auditoria
```

---

# OBJETIVO FINAL

Desarrollar un ERP robusto para empresas de transporte de cargas en Argentina, con control total de viajes, choferes, vehículos, gastos, facturación, cobranzas, cuentas corrientes y tesorería, manteniendo una interfaz simple, rápida y fácil de utilizar.
