# Trans Cargo Hub — Sistema de Gestión de Transporte (SGT)

## Presentación Comercial | Oferta de Venta

---

## 1. Resumen Ejecutivo

**Trans Cargo Hub** es un sistema de gestión integral diseñado específicamente para empresas de transporte de cargas. Cubre el ciclo completo del negocio: desde la creación del viaje, pasando por la gestión de flota, choferes y clientes, hasta la facturación, cobranza y liquidación de ganancias a choferes.

Desarrollado con tecnología **PHP 8.2 + MySQL/MariaDB + JavaScript nativo**, es una aplicación web moderna, rápida y segura, accesible desde cualquier navegador sin necesidad de instalar software adicional.

---

## 2. Stack Tecnológico

| Componente | Tecnología |
|---|---|
| Backend | PHP 8.2 (POO, PDO) |
| Base de Datos | MySQL / MariaDB 10.4+ |
| Frontend | HTML5, CSS3 Puro, JavaScript Nativo |
| Arquitectura | Multi-tenant (100% aislamiento entre empresas) |
| Control de Acceso | RBAC (Role-Based Access Control) |
| Seguridad | Sesiones HTTP, encriptación bcrypt, prepared statements, XSS prevention |
| Servidor Web | Apache con mod_rewrite (URLs amigables) |
| Entorno | Laragon / XAMPP / cualquier host compatible |

---

## 3. Arquitectura y Seguridad

### 3.1 Multi-Tenant Estricto
- **Aislamiento total** de datos entre empresas transportistas.
- Cada empresa opera con su propia flota, clientes, choferes, viajes y finanzas.
- Un administrador/empresa **NO puede ver ni acceder** a datos de otra empresa.
- El rol **Developer** tiene visibilidad global para supervisión y soporte técnico.

### 3.2 Control de Acceso (RBAC - 3 Niveles)
- **Developer:** Acceso total al sistema. Crea administradores.
- **Administrador:** Dueño de su empresa. Crea usuarios, asigna permisos específicos por módulo.
- **Usuario Staff:** Acceso limitado a los módulos que el administrador le asigne.

### 3.3 Seguridad Implementada
- Contraseñas encriptadas con **bcrypt** (password_hash / password_verify).
- **Prepared Statements** PDO en todas las consultas SQL — 0% de riesgo de inyección SQL.
- **XSS Prevention:** Todas las salidas HTML escapadas con `htmlspecialchars()`.
- **CSRF Protection:** Formularios con validación de sesión.
- **Timeout de sesión automático:** 30 minutos de inactividad cierra la sesión automáticamente.
- **Logging de errores interno:** Los errores de conexión se registran internamente sin exponer información sensible al usuario.

---

## 4. Módulos del Sistema (Funcionalidades Completas)

### 4.1 Dashboard — Panel de Control
Visión general del estado operativo de la empresa:

- **Métricas rápidas:**
  - Viajes en curso (pendientes de descarga)
  - Viajes pendientes de facturar
  - Viajes pendientes de cobrar
  - Viajes pendientes de liquidar al chofer
  - Viajes del mes en curso y última semana
- **Monto total de Cuentas por Cobrar** (fletes facturados no cobrados)
- **Agenda de Pagos:** Próximos 8 pagos estimados/pactados
- **Alertas de vencimientos:**
  - VTV próximas a vencer (próximos 30 días)
  - Licencias de choferes próximas a vencer
- **Actividad reciente:** Últimos 5 viajes registrados

### 4.2 Módulo de Viajes — Core del Sistema
Gestión completa del ciclo de vida de un viaje (3 estados):

#### Estado 1: Iniciado (En Viaje)
- Registro de viaje con:
  - Cliente, vehículo (auto-asigna chofer y acoplado), origen, destino, producto
  - Fecha de carga (automática)
  - Tarifa por TN, peso estimado
  - Documentación: CTG, Carta de Porte, Otros documentos
  - Generación automática de código **SD-#####** para viajes sin documentación
  - Tipo de comisión (ninguna/porcentaje/monto fijo), valor y comisionista
  - Pagador de flete
- **Eventos en tránsito:**
  - Carga de gastos (combustible, peaje, playa, reparación en ruta, otros)
  - Adelantos al chofer (con impacto contable automático en cuenta corriente)
- **Descarga:**
  - Registro de peso bruto real y tara real
  - Cálculo automático del peso neto (con preview en vivo)
  - **Impacto contable automático:** Genera liquidación en la cuenta corriente del chofer

#### Estado 2: Facturado (Liquidación)
- Facturación individual por viaje
- Cálculo automático de IVA 21%
- Registro de número de factura, fecha de emisión y fecha estimada de cobro

#### Estado 3: Cobrado (Finanzas)
- Registro de cobro con selección de cuenta destino
- Retenciones detalladas (IVA, Ganancias, Ingresos Brutos, SUSS, Otro)
- Múltiples medios de cobro (efectivo, transferencia, cheque, Mercado Pago)
- **Para cheques:** registro completo de tipo, banco, número, fechas, librador, endosante
- **Cálculo automático del neto** a cobrar (total facturado - retenciones)
- **Actualización automática del saldo** en la cuenta destino
- **Liquidación al chofer:** Cálculo automático de ganancia según porcentaje acordado

### 4.3 Módulo de Clientes
- Registro multi-tipo: Un cliente puede ser **Comercial**, **Comisionista** y/o **Pagador de Flete**
- Badges de colores identificando cada tipo:
  - 🔵 Cliente (azul)
  - 🟣 Comisionista (violeta)
  - 🟢 Pagador de Flete (verde-azulado)
- Filtro inteligente por tipo de cliente
- CUIT/DNI único por empresa (el mismo CUIT puede existir en distintas empresas)
- Borrado lógico (no se pierde información histórica)

### 4.4 Módulo de Choferes
- Gestión completa de legajos de choferes
- Campos: nombre, apellido, CUIL, teléfono, porcentaje de ganancia por flete
- Licencia: número y fecha de vencimiento
- **Alertas visuales:** Licencia vencida (rojo) o próxima a vencer ≤30 días (naranja)
- **Cuenta Corriente:** Historial completo de pagos por tipo (adelanto, sueldo, liquidación, otro)
- **Liquidación de viajes:** Cálculo automático de ganancia, saldo pendiente y liquidación con un clic

### 4.5 Módulo de Vehículos
- Registro de camiones y acoplados
- Asignación opcional de chofer al vehículo
- VTV: registro de vencimiento con **alertas visuales** (rojo: vencido, naranja: ≤30 días)
- Borrado lógico

### 4.6 Módulo de Mantenimiento
- Registro de mantenimientos preventivos y correctivos
- Alertas de VTV vencidas/próximas a vencer
- Historial por vehículo

### 4.7 Módulo de Comisionistas
- Gestión de comisionistas (clientes con flag es_comisionista)
- Cuenta corriente de pagos a comisionistas
- Historial completo con total acumulado

### 4.8 Módulo de Cobranzas
Flujo completo de facturación y cobro:

- **Fletes Pendientes:** Viajes descargados listos para facturar
  - Vista detalle del viaje
  - Botón para facturar con cálculo automático de IVA
- **Fletes a Cobrar:** Viajes facturados pendientes de cobro
  - Modal completo de cobro con retenciones, medios de pago, cuentas destino
  - Cálculo en vivo del neto a cobrar
- **Viajes Cobrados:** Pendientes de liquidar al chofer
  - Liquidación con un clic, con impacto contable automático
- **Historial:** Últimos viajes liquidados

### 4.9 Módulo de Cuentas (Tesorería)
- Registro de cuentas bancarias, billeteras virtuales y caja de efectivo
- Tipos: Banco, Billetera Virtual, Caja de Efectivo, Otro
- Campos: nombre, banco/entidad, número de cuenta, CBU, alias, titular, CUIT
- **Saldo actual** en cada cuenta
- **Saldo total consolidado** de todas las cuentas
- **Ajuste manual de saldo**
- **Movimientos por cuenta:** Historial detallado con:
  - Tipo (entrada/salida), concepto, referencia (viaje asociado)
  - Monto y saldo resultante
  - Totales de entradas y salidas
  - Carga vía AJAX sin recargar la página

### 4.10 Módulo de Empresas (Transportistas)
- Gestión de empresas/transportistas dentro del sistema
- Validación de ownership: solo el creador (o developer) puede editar/eliminar
- Borrado lógico

### 4.11 Módulo de Configuración
- **Temas visuales:** 3 temas intercambiables (Corporativo, Medio, Dark)
- **Gestión de usuarios:** Creación y administración de usuarios con permisos específicos
- **Permisos granulares:** Asignación de acceso a cada módulo del sistema por usuario

---

## 5. Flujo de Negocio Completo (Ciclo del Viaje)

```
┌─────────────────────────────────────────────────────────────┐
│                    CICLO COMPLETO DEL VIAJE                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. INICIAR VIAJE (En Viaje)                                 │
│     ├─ Seleccionar cliente, vehículo (auto-asigna chofer)    │
│     ├─ Origen, destino, producto, fecha carga                │
│     ├─ Documentación (CTG, CP, otros o SD-#####)             │
│     ├─ Tarifa, peso estimado, comisiones                     │
│     └─ Pagador de flete                                      │
│                                                              │
│  2. EN TRÁNSITO                                              │
│     ├─ Cargar gastos (combustible, peaje, etc.)              │
│     └─ Adelantos al chofer (impacto en cta cte automático)   │
│                                                              │
│  3. DESCARGAR (Descargado)                                   │
│     ├─ Registrar peso bruto real y tara                      │
│     ├─ Cálculo automático del peso neto                      │
│     └─ Impacto contable: liquidación automática al chofer    │
│                                                              │
│  4. FACTURAR (Facturado)                                     │
│     ├─ Cálculo IVA 21%                                       │
│     ├─ N° factura, fecha emisión, fecha cobro estimada       │
│     └─ Total final con IVA incluido                          │
│                                                              │
│  5. COBRAR (Cobrado)                                         │
│     ├─ Seleccionar cuenta destino                            │
│     ├─ Registrar retenciones (IVA, Ganancias, IIBB, etc.)    │
│     ├─ Medio de cobro (efectivo, transferencia, cheque, MP)  │
│     ├─ Cálculo automático del neto a cobrar                  │
│     └─ Actualización automática del saldo en la cuenta       │
│                                                              │
│  6. LIQUIDAR CHOFER (Liquidado)                              │
│     ├─ Cálculo automático de ganancia (% del chofer)         │
│     ├─ Registro en cuenta corriente del chofer               │
│     └─ Viaje completado al 100%                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 6. Dashboard y Reportes

El panel principal ofrece una vista ejecutiva del estado del negocio:

| Indicador | Descripción |
|---|---|
| Viajes en Curso | Cantidad de viajes activos sin descargar |
| Por Facturar | Viajes descargados esperando factura |
| Por Cobrar | Facturas emitidas pendientes de cobro |
| Por Liquidar | Viajes cobrados pendientes de pago al chofer |
| Cuentas por Cobrar | Monto total adeudado a la empresa |
| Viajes del Mes | Total de viajes realizados en el período |
| Alertas VTV | Vehículos con VTV vencida o próxima a vencer |
| Alertas Licencias | Choferes con licencia próxima a vencer |
| Agenda de Pagos | Próximas facturas a cobrar ordenadas por fecha |
| Actividad Reciente | Últimos 5 viajes registrados |

---

## 7. Interfaz de Usuario

- **Diseño moderno y responsive:** Adaptable a escritorio y dispositivos móviles
- **Sidebar colapsable:** Menú lateral con iconos y etiquetas, colapsable para más espacio
- **Selector de empresa activa:** Cambio rápido entre empresas del mismo administrador
- **Temas visuales:** 3 temas intercambiables (Corporativo, Medio, Dark)
- **Modal de confirmación genérico:** Reemplaza el `confirm()` nativo con un modal estilizado
- **Badges de colores:** Indicadores visuales de estado en todas las tablas
- **Tablas responsivas:** Con scroll horizontal en pantallas pequeñas
- **Alertas visuales:** Para vencimientos y acciones exitosas/fallidas

---

## 8. Base de Datos (11 Tablas Principales)

| Tabla | Propósito |
|---|---|
| `transportistas` | Empresas (tenants) |
| `users` | Usuarios del sistema |
| `user_permissions` | Permisos por usuario |
| `clientes` | Clientes (comerciales, comisionistas, pagadores) |
| `choferes` | Legajo de choferes |
| `vehiculos` | Flota de vehículos (con acoplados) |
| `viajes` | Registro principal de viajes (23+ campos) |
| `viajes_gastos` | Gastos asociados a cada viaje |
| `viajes_adelantos` | Adelantos a choferes por viaje |
| `chofer_pagos` | Cuenta corriente de choferes |
| `comisionista_pagos` | Pagos a comisionistas |
| `mantenimientos` | Mantenimiento de flota |
| `cuentas_empresa` | Cuentas bancarias/billeteras/caja |
| `cuentas_movimientos` | Movimientos de cada cuenta |
| `cobros_fletes` | Registro de cobros |
| `cobros_fletes_retenciones` | Retenciones aplicadas |
| `cobros_fletes_cheques` | Datos de cheques recibidos |
| `configuraciones` | Configuración del sistema (temas, etc.) |

---

## 9. Ventajas Competitivas

### ✅ 100% Web — Sin Instalación
Accesible desde cualquier dispositivo con navegador web. No requiere instalar software en las computadoras de los usuarios.

### ✅ Multi-Empresa
Un solo sistema puede gestionar múltiples empresas transportistas con aislamiento total de datos.

### ✅ Control de Acceso Granular
Cada usuario puede tener permisos específicos por módulo (solo lectura, crear, editar, eliminar).

### ✅ Flujo Completo del Negocio
Cubre desde la creación del viaje hasta la liquidación final del chofer, pasando por facturación, cobranza y tesorería.

### ✅ Impacto Contable Automático
Cada acción (adelanto, descarga, cobro, liquidación) genera automáticamente los movimientos contables correspondientes sin intervención manual.

### ✅ Alertas Inteligentes
Notifica automáticamente sobre VTV y licencias próximas a vencer, evitando multas y vehículos fuera de circulación.

### ✅ Sin Costos Recurrentes de Licencia
Al ser una solución desarrollada a medida, no hay suscripciones mensuales ni costos por usuario.

### ✅ Código Limpio y Mantenible
PHP estructurado con separación de responsabilidades, CSS modular, JavaScript funcional. Fácil de mantener y extender.

### ✅ Base de Datos Optimizada
Con índices, claves foráneas y columnas generadas (peso neto calculado automáticamente).

---

## 10. Público Objetivo

- **Pequeñas y medianas empresas de transporte de cargas**
- **Transportistas independientes** que quieren profesionalizar su gestión
- **Flotas de camiones** con múltiples vehículos y choferes
- **Empresas de logística** que necesitan gestionar viajes, clientes y cobranzas
- **Administradores de flotas** que tercerizan servicios de transporte

---

## 11. Beneficios Clave para el Cliente

| Problema | Solución Trans Cargo Hub |
|---|---|
| Desorden en viajes y documentación | Sistema organizado con CTG, CP, códigos SD automáticos |
| Falta de control de gastos | Registro detallado de gastos por viaje categorizados |
| Demoras en cobranzas | Agenda de pagos, alertas de facturas pendientes |
| Dificultad para liquidar choferes | Cálculo automático de ganancias y liquidación con un clic |
| Pérdida de documentación | Historial completo de cada viaje con todos los documentos |
| Multas por VTV/licencias vencidas | Alertas automáticas con 30 días de anticipación |
| Falta de visibilidad financiera | Dashboard con métricas y saldos consolidados |
| Dependencia de un técnico | Sistema intuitivo, autogestionable, con soporte remoto |

---

## 12. Próximas Funcionalidades (Roadmap)

- ✅ Módulo de Viajes (completo)
- ✅ Módulo de Clientes (completo)
- ✅ Módulo de Choferes + Cta Cte + Liquidación (completo)
- ✅ Módulo de Vehículos (completo)
- ✅ Módulo de Mantenimiento (completo)
- ✅ Módulo de Comisionistas + Cta Cte (completo)
- ✅ Módulo de Cobranzas (completo con facturación, cobros, retenciones, cheques)
- ✅ Módulo de Cuentas / Tesorería (completo con movimientos)
- ✅ Dashboard (completo con métricas y alertas)
- ✅ Configuración y Gestión de Usuarios (completo)
- 🔜 Informes y reportes exportables (PDF/Excel)
- 🔜 Facturación electrónica (AFIP)
- 🔜 App móvil para choferes
- 🔜 Integración con APIs de peajes y combustible
- 🔜 Notificaciones por email/WhatsApp

---

## 13. Soporte y Mantenimiento

- **Instalación y puesta en marcha** en servidor del cliente o hosting compartido
- **Capacitación del personal** incluida
- **Soporte técnico remoto** con garantía de respuesta
- **Actualizaciones y mejoras** según necesidades del cliente
- **Código fuente 100% entregado** — el cliente es dueño del software

---

## 14. Contacto

**Desarrollado por:** Sistemas Lucyk  
**Tecnología:** PHP 8.2 + MySQL + JavaScript Nativo  
**Arquitectura:** Multi-tenant 100% aislado  
**Licencia:** Desarrollo a medida — código fuente propiedad del cliente

---

*"Trans Cargo Hub — La solución completa para la gestión de tu empresa de transporte."*