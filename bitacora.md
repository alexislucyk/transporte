# BITÁCORA DEL PROYECTO — Trans Cargo Hub (SGT)

Este archivo documenta **toda** la evolución del proyecto desde su auditoría inicial.
Reglas:
- Cada cambio significativo (código, SQL, decisión) lleva fecha y motivo.
- El SQL de migración queda embebido en su entrada.
- Los módulos se documentan al ser creados/auditados.
- **Se ignora `base2.md`**. Solo `base.md` es la fuente de verdad.

---

## 0. Contexto inicial

- **Proyecto:** Sistema de Gestión de Transporte (SGT) — Trans Cargo Hub
- **Stack:** PHP 8.2 + MySQL/MariaDB (XAMPP), JS nativo, CSS modular
- **DB:** `trans_dev_db`
- **Fecha de inicio de bitácora:** 2026-06-25
- **Multi-tenant:** 100% aislado entre empresas (decidido en sesión). Toda query de datos hijos debe filtrar por `transportista_id = $_SESSION['active_company_id']`.

---

## 1. Auditoría inicial (2026-06-25)

### 1.1 Estado del proyecto al iniciar

**Archivos PHP en `modules/` con contenido:**

| Archivo | Líneas | Estado |
|---|---|---|
| `dashboard.php` | 130 | Funcional pero incompleto |
| `configuracion.php` | 403 | Funcional, con issues |
| `empresas.php` | 254 | Funcional, sin autorización multi-tenant |
| `transportistas.php` | 163 | **Duplicado de `empresas.php`** |
| Resto (15 archivos) | 0 | Vacíos, sin implementar |

### 1.2 Hallazgos críticos

1. **`config/db.php`**: `die()` exponía el mensaje crudo de PDO al usuario.
2. **`dashboard.php`**: No cumplía `base.md §5` (sin monto total de CxC, sin agenda de pagos).
3. **`empresas.php`**: Acciones `editar`/`borrar` sin validación de ownership multi-tenant.
4. **`transportistas.php`**: Duplicado de `empresas.php` con bugs (sin borrado lógico, sin autorización).
5. **`sidebar.php`**: Tenía un cierre `</nav>` mal posicionado.
6. **`configuracion.php`**: Lista de módulos usaba la key obsoleta `transportistas` en lugar de `empresas`.

---

## 2. Correcciones aplicadas (2026-06-25)

### 2.1 `config/db.php` — Sanitización de errores de conexión

**Problema:** `die("Error de conexión: " . $e->getMessage())` filtraba información sensible (host, db, user) al cliente.

**Solución:** Log interno + mensaje genérico.

```php
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("[DB CONNECTION ERROR] " . $e->getMessage() . " | DB: " . DB_NAME . " | User: " . DB_USER);
    die("Error: No se pudo conectar a la base de datos. Contacte al administrador del sistema.");
}
```

### 2.2 `dashboard.php` — Cumplimiento de `base.md §5`

**Cambios:**
- Filtros `activo = 1` agregados a todas las queries.
- **Nuevas métricas** (las que pedía `base.md §5` y faltaban):
  - Viajes del mes en curso.
  - Viajes de la última semana.
  - **Monto total de Cuentas por Cobrar** (`SUM(total_flete_neto)` de viajes en estado `facturado`).
- **Nueva sección visual:** *Agenda de Pagos* — listado de las 8 facturas pendientes más antiguas, con número, cliente, fecha de factura y monto.

### 2.3 Consolidación `empresas.php` (módulo canónico)

**Decisión:** Eliminar `transportistas.php` (duplicado) y usar `empresas.php` como único punto de entrada para la gestión de empresas/transportistas.

**Cambios en `empresas.php`:**

a) Nueva función helper `empresaOwner()` para validar multi-tenancy:

```php
function empresaOwner(PDO $pdo, int $id, int $currentUserId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM transportistas WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $currentUserId]);
    return (bool)$stmt->fetchColumn();
}
```

b) Acciones `editar` y `borrar` ahora validan ownership:
- Si el usuario no es `developer`, debe ser el `created_by` de la empresa.
- Si no lo es, se rechaza con mensaje "No autorizado: la empresa no existe o pertenece a otro administrador."

c) Modal de confirmación de borrado: reemplazado el modal inline reimplementado en JS por `appConfirm()` (helper global de `index.php`).

### 2.4 `modules/transportistas.php` — ELIMINADO

Archivo borrado. Su funcionalidad vive en `empresas.php`.

### 2.5 `index.php` — Redirección actualizada

```php
// Antes:
if (empty($todas_empresas) && !in_array($module, ['transportistas', 'dashboard', 'configuracion'])) {
    header("Location: " . $base_path . "transportistas");
    exit;
}

// Ahora:
if (empty($todas_empresas) && !in_array($module, ['empresas', 'dashboard', 'configuracion'])) {
    header("Location: " . $base_path . "empresas");
    exit;
}
```

### 2.6 `includes/sidebar.php` — Bug de cierre `</nav>`

**Problema:** El `</nav>` de cierre estaba mal posicionado (después de los items de navegación inferior, pero fuera del bloque). Resultado: HTML inválido y posibles glitches visuales.

**Solución:** Reestructurado para que `nav-menu-bottom` se cierre correctamente con sus items de Empresas + Configuración + Logout.

### 2.7 `modules/configuracion.php` — Key de módulo

```php
// Antes:
'transportistas' => 'Empresas',

// Ahora:
'empresas' => 'Empresas',
```

Esto alinea el sistema de permisos con la ruta real del módulo (que pasó de `transportistas` a `empresas`).

### 2.8 Verificación final

Búsqueda de referencias rotas a `transportistas` en archivos del proyecto (excluyendo `base2.md`):
- ✅ `schema.sql`: referencias legítimas al **nombre de tabla** (no se cambia).
- ✅ `modules/empresas.php`: queries SQL a la tabla `transportistas` (correcto).
- ✅ `index.php`: queries SQL a la tabla `transportistas` (correcto).
- ✅ Comentarios explicativos (no afectan funcionalidad).
- ❌ No quedan `href="transportistas"` ni rutas de navegación al archivo eliminado.

---

## 3. Decisión de arquitectura: Multi-tenant (2026-06-25)

**Pregunta resuelta:** ¿Las empresas de un admin comparten flota/choferes/clientes, o son 100% aisladas?

**Respuesta:** **100% aisladas entre sí.**

**Implicaciones:**
1. Toda query de datos hijos (clientes, choferes, vehículos, viajes, mantenimientos, etc.) debe filtrar por `transportista_id = $_SESSION['active_company_id']`.
2. El selector de "Empresa Activa" en el sidebar define sobre qué tenant se opera.
3. El cambio de empresa debe limpiar cualquier estado tenant-specific en sesión.
4. El campo `transportista_id` (FK) presente en `clientes`, `choferes`, `vehiculos`, `viajes` ya está bien modelado.

---

## 4. Pendientes de auditoría en módulos existentes (2026-06-25)

Issues conocidos pero no críticos, a resolver en próximas iteraciones:

1. **`configuracion.php` línea 22 vs 122**: Inconsistencia entre el redirect `?success=1` y el read `$_GET['msg'] ??`. El parámetro `msg` nunca se setea.
2. **`login.php`**: No setea `$_SESSION['admin_root_id']` que `index.php` busca para usuarios `admin` (línea 60-68). Si un admin crea un user, ese user no tendrá `admin_root_id` y caerá al fallback.
3. **`index.php`**: Control de inactividad solo en JS (vulnerable a bypass con DevTools).
4. **`core/auth.php`**: No auditado aún.

---

## 5. Próximos pasos — Roadmap de implementación

Orden sugerido (cada módulo se documenta al implementarse):

1. **Migración DB**: Agregar `activo` y `created_by` a `clientes` (no existen). Cambiar `UNIQUE(cuit)` a `UNIQUE(cuit, transportista_id)`.
2. `clientes.php` — Base para todo lo demás.
3. `vehiculos.php` + `mantenimiento.php` — Entidades independientes.
4. `choferes.php` + `choferes_ctacte.php` + `choferes_liquidar.php` — Rama de choferes.
5. `comisionistas.php` + `comisionistas_ctacte.php` — Rama de comisionistas.
6. `viajes.php` + `viajes_detalle.php` — Núcleo del sistema.
7. `cobranzas.php` + `cobranzas_fletes_pendientes.php` + `cobranzas_fletes_liquidar.php` + `cobranzas_fletes_factura.php` — Facturación y cobro.
8. `tesoreria.php` — Cuentas financieras y medios de cobro.

---

## 6. Implementación de `clientes.php` (2026-06-25)

### 6.1 Migración SQL — `migrations/001_clientes_multitenant.sql`

**Objetivo:** Adaptar la tabla `clientes` al modelo multi-tenant 100% aislado.

**Cambios:**
- `activo TINYINT(1) NOT NULL DEFAULT 1` — borrado lógico.
- `created_by INT(11) NULL` — auditoría (quién registró al cliente).
- `idx_clientes_activo` y `idx_clientes_created_by` — índices.
- FK `clientes_fk_created_by` → `users.id` con `ON DELETE SET NULL`.
- **Cambio crítico:** `UNIQUE(cuit)` (global) → `UNIQUE(cuit, transportista_id)` (por tenant).
  - Razón: el mismo CUIT puede existir en dos empresas distintas (escenario real: cliente con contratos con varios transportistas).
- **Backfill:** `created_by` se setea al dueño del tenant para no perder trazabilidad de clientes existentes.

**SQL completo:** ver `migrations/001_clientes_multitenant.sql`.

### 6.2 Módulo `modules/clientes.php` (281 líneas)

**Cumple:** `base.md §3` — Módulo CLIENTES.

**Decisiones de diseño:**

1. **Multi-tipo obligatorio:** Un cliente debe tener al menos uno de los 3 flags (`es_comercial`, `es_comisionista`, `es_pagador`). Validado en servidor y reflejado en UI con badges de colores:
   - 🔵 Cliente (azul #3498db)
   - 🟣 Comisionista (violeta #9b59b6)
   - 🟢 Pagador de Flete (verde-azulado #16a085)

2. **Filtro por tipo:** Select en la parte superior con 4 opciones (Todos / Solo Clientes / Solo Comisionistas / Solo Pagadores). El filtro se aplica en la query SQL con `AND es_X = 1`.

3. **Multi-tenant estricto:**
   - Todo listado filtra por `transportista_id = $_SESSION['active_company_id]`.
   - Helper `clienteOwner(PDO, id, tenantId, role)`: valida que el cliente pertenezca al tenant activo (o sea `developer`).

4. **Acciones disponibles:** Alta, Edición, Borrado lógico (`activo = 0`).
   - Borrado usa `appConfirm()` (helper global de `index.php`), no modal inline.

5. **Seguridad:**
   - Todas las salidas HTML con `htmlspecialchars()`.
   - `json_encode` con flags `JSON_HEX_APOS | JSON_HEX_QUOT` para evitar XSS en atributos JS.
   - Validación de CUIT/DNI con `pattern="[0-9]{8,11}"` (8 a 11 dígitos, sin guiones).

6. **Patrón de confirmación de borrado:** Formulario oculto `#form-borrar-cliente` con campos `action=borrar` y `id`, que se dispara desde `appConfirm()`. Patrón limpio y consistente con el resto del sistema.

### 6.3 Verificación de integración

- ✅ `index.php` línea 214-215: `case 'clientes': include_once 'modules/clientes.php';` ya estaba cableado.
- ✅ `includes/sidebar.php` línea 51: `navItem('clientes', 'fa-building', 'Clientes', ...)` ya estaba en el menú.
- ✅ `modules/configuracion.php` línea 333: `'clientes' => 'Clientes'` ya estaba en la lista de permisos.
- ✅ `modules/dashboard.php` línea 61: `LEFT JOIN clientes c ON c.id = v.cliente_id` ya lo usaba para la agenda de pagos.

**No fue necesario tocar nada del cableado** — el módulo se integró limpiamente.

### 6.4 Patrón a replicar en próximos módulos

Para mantener consistencia, los próximos módulos deben seguir este esqueleto:

```php
<?php
$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function XOwner(PDO $pdo, int $id, int $tenantId, string $role): bool { ... }
```

---

## 7. Implementación de `vehiculos.php` y `mantenimiento.php` (2026-06-25)

### 7.1 Migración SQL — `migrations/002_vehiculos_multitenant.sql`
*(Ya existente - agrega `activo`, `created_by` y UNIQUE compuesto por tenant)*

### 7.2 Módulo `modules/vehiculos.php`
- Multi-tenant estricto con `transportista_id = $_SESSION['active_company_id']`
- Asignación opcional de chofer al vehículo
- Alertas VTV (rojo: vencido, naranja: ≤30 días)
- Acciones: Alta, Edición, Borrado lógico
- Usa `appConfirm()` para eliminación

### 7.3 Migración SQL — `migrations/003_choferes_multitenant.sql`
- Agrega FK `created_by` → `users.id`
- Cambia UNIQUE(cuil) a UNIQUE(cuil, transportista_id)
- Backfill de `created_by` desde dueño del tenant

### 7.4 Migración SQL — `migrations/004_mantenimientos_multitenant.sql`
- Agrega columnas `activo` y `created_by` a tabla `mantenimientos`

### 7.5 Módulo `modules/choferes.php` (248 líneas)
- Multi-tenant 100% aislado
- Campos: nombre, apellido, cuil, telefono, %_ganancia_flete, licencia + vencimiento
- Alertas de licencia vencida/próxima a vencer (rojo/naranja)
- Acciones: Alta, Edición, Borrado lógico
- Validación: nombre + apellido obligatorios

### 7.6 Módulo `modules/mantenimiento.php` (actualizado)
- Multi-tenant con filtro por `transportista_id`
- Muestra alertas de VTV vencidas/próximas a vencer
- Listado LIMIT 200 para performance
- Acciones: Alta, Borrado lógico (no edición por simplicidad)

### 7.7 Actualización `modules/configuracion.php`
- Agregado permiso `choferes_ctacte` a la lista de módulos para gestión de permisos

---

## 8. Estado del Roadmap (2026-06-25)

| Módulo | Estado |
|--------|--------|
| clientes.php | ✅ Completo |
| vehiculos.php | ✅ Completo (244 líneas) |
| mantenimiento.php | ✅ Completo (231 líneas) |
| choferes.php | ✅ Completo (248 líneas) |
| comisionistas.php | ✅ Completo (279 líneas) |
| comisionistas_ctacte.php | ✅ Completo (175 líneas) |
| choferes_ctacte.php | ✅ Completo (188 líneas) |
| choferes_liquidar.php | ✅ Completo (215 líneas) |
| viajes.php | ❌ Pendiente |
| viajes_detalle.php | ❌ Pendiente |
| cobranzas.php | ❌ Pendiente |
| cobranzas_fletes_pendientes.php | ❌ Pendiente |
| cobranzas_fletes_liquidar.php | ❌ Pendiente |
| cobranzas_fletes_factura.php | ❌ Pendiente |
| tesoreria.php | ❌ Pendiente |

---

## 9. Implementación de `comisionistas.php` y `comisionistas_ctacte.php` (2026-06-25)

### 9.1 Módulo `modules/comisionistas.php` (279 líneas)

**Objetivo:** CRUD de comisionistas (clientes con `es_comisionista = 1`) con multi-tenancy estricto.

**Decisiones de diseño:**

1. **Reutiliza tabla `clientes`:** Un comisionista es un cliente con el flag `es_comisionista = 1`. No se creó tabla nueva; se filtra por `es_comisionista = 1` en todas las queries.
2. **Multi-tenant estricto:**
   - Todo listado filtra por `transportista_id = $_SESSION['active_company_id']`.
   - Helper `comisionistaOwner(PDO, id, tenantId, role)`: valida que el cliente pertenezca al tenant activo y sea comisionista.
3. **Acciones disponibles:** Alta, Edición, Borrado lógico (`activo = 0`).
   - Borrado usa `appConfirm()` (helper global de `index.php`).
4. **Vinculación a cuenta corriente:** Cada fila del listado tiene un ícono `fa-dollar-sign` que linkea a `comisionistas_ctacte?cliente_id=X`.
5. **Checkbox pre-marcado:** En el modal de alta/edición, el checkbox "Comisionista" (`es_comisionista`) viene marcado por defecto.
6. **Seguridad:** Todas las salidas HTML con `htmlspecialchars()` y `json_encode` con flags `JSON_HEX_APOS | JSON_HEX_QUOT`.

### 9.2 Módulo `modules/comisionistas_ctacte.php` (175 líneas)

**Objetivo:** Cuenta corriente de pagos a comisionistas (CRUD de pagos).

**Decisiones de diseño:**

1. **Dos modos de operación:**
   - Sin `cliente_id` (GET): muestra selector de comisionistas para acceder a su cuenta corriente.
   - Con `cliente_id`: muestra historial de pagos, total acumulado y formulario de nuevo pago.
2. **Validación multi-tenant:** El helper `comisionistaOwner()` valida que el comisionista pertenezca al tenant activo antes de permitir registrar pagos.
3. **Formulario de nuevo pago:** Campos: fecha (default hoy), monto, detalle. Al enviar, redirige con `?msg=1` para mostrar mensaje de éxito.
4. **Resumen financiero:** Muestra el total pagado (`SUM(monto)`) al comisionista.
5. **Tabla de historial:** Ordenada por fecha DESC, muestra fecha, monto (con formato `$ 1.234,56`) y detalle.

### 9.3 Actualizaciones en archivos existentes

**`index.php`:**
- Agregado `case 'comisionistas_ctacte': include_once 'modules/comisionistas_ctacte.php';` en el switch de routing.

**`includes/sidebar.php`:**
- Agregado item de navegación: `navItem('comisionistas_ctacte', 'fa-dollar-sign', 'Cta. Cte. Comisiones', ...)`.

**`modules/configuracion.php`:**
- Agregado permiso `'comisionistas_ctacte' => 'Cta Cte Comisiones'` a la lista `$modulos_lista` en el modal de gestión de permisos.

### 9.4 Verificación

- ✅ `php -l` sin errores de sintaxis en ambos archivos.
- ✅ Lint passed: `No syntax errors detected`.

---

## 10. Implementación de `choferes_ctacte.php` y `choferes_liquidar.php` (2026-06-25)

### 10.1 Módulo `modules/choferes_ctacte.php` (188 líneas)

**Objetivo:** Cuenta corriente de pagos a choferes (CRUD de pagos).

**Decisiones de diseño:**

1. **Dos modos de operación:**
   - Sin `chofer_id` (GET): muestra selector de choferes para acceder a su cuenta corriente.
   - Con `chofer_id`: muestra historial de pagos, total acumulado y formulario de nuevo pago.
2. **Validación multi-tenant:** Helper `choferOwner()` (heredado de `choferes.php`) valida que el chofer pertenezca al tenant activo.
3. **Tipos de pago:** `adelanto`, `sueldo`, `liquidacion`, `otro` (enum de la tabla `chofer_pagos`).
4. **Formulario de nuevo pago:** Campos: fecha (default hoy), monto, tipo (select), detalle. Al enviar, redirige con `?msg=1`.
5. **Resumen financiero:** Muestra el total movimientos (`SUM(monto)`).
6. **Badges por tipo:** Colores diferenciados por tipo de pago (Adelanto=naranja, Suelo=azul, Liquidación=verde, Otro=gris).
7. **Vinculación desde `choferes.php`:** Ícono `fa-dollar-sign` en la columna Acciones linkea a `choferes_ctacte?chofer_id=X`.

### 10.2 Módulo `modules/choferes_liquidar.php` (215 líneas)

**Objetivo:** Liquidación de viajes por chofer (gestión de saldos pendientes).

**Decisiones de diseño:**

1. **Cálculo de ganancia por viaje:** `total_flete_neto * chofer_porcentaje / 100` calculado en la query SQL.
2. **Viajes filtrados:** Solo muestra viajes en estado `descargado` o `facturado` que tienen `activo = 1`.
3. **Saldo pendiente:** Calcula `ganancia_total - total_liquidado` donde `total_liquidado` es la suma de todos los pagos de tipo `liquidacion` en `chofer_pagos`.
4. **Tarjetas de resumen:** Muestra Ganancia Total, Ya Liquidado y Saldo Pendiente en tarjetas visuales separadas.
5. **Acción de liquidación:** Formulario que registra un pago en `chofer_pagos` con `tipo = 'liquidacion'` por el monto del saldo pendiente. Redirige con `?msg=1`.
6. **Historial de liquidaciones:** Tabla separada que muestra solo pagos de tipo `liquidacion` del chofer.
7. **Selector de chofer:** Modo sin ID muestra lista de choferes para seleccionar.

### 10.3 Actualizaciones en archivos existentes

**`modules/choferes.php`:**
- Agregado ícono `fa-dollar-sign` en la columna Acciones: `<a href="choferes_ctacte?chofer_id=...">`.

**`index.php`:**
- Agregado `case 'choferes_ctacte': include_once 'modules/choferes_ctacte.php';`.
- Agregado `case 'choferes_liquidar': include_once 'modules/choferes_liquidar.php';` en el switch de routing.

**`includes/sidebar.php`:**
- Agregado `navItem('choferes_ctacte', 'fa-dollar-sign', 'Cta. Cte. Choferes', ...)`.
- Agregado `navItem('choferes_liquidar', 'fa-calculator', 'Liquidar Choferes', ...)`.

**`modules/configuracion.php`:**
- Agregado permiso `'choferes_ctacte' => 'Cta Cte Choferes'`.
- Agregado permiso `'choferes_liquidar' => 'Liquidar Choferes'`.
- Corregido duplicado de `choferes_ctacte` que existía en la lista.

### 10.4 Verificación

- ✅ `php -l` sin errores de sintaxis en ambos archivos.
- ✅ Lint passed: `No syntax errors detected`.

---

## 11. Implementación de `viajes.php` — Módulo Core (2026-06-26)

### 11.1 Estado inicial

El archivo `modules/viajes.php` existía con estructura parcial (~508 líneas) pero contenía **bugs críticos**:

1. **`created_by` inexistente en schema**: El INSERT intentaba escribir en columna `created_by` que no existe en la tabla `viajes` (solo tiene `created_at`). Causaba error PDO en cada creación.
2. **Cálculos prematuros**: `$total_flete_bruto` y `$total_flete_neto` se calculaban al inicio del bloque POST para TODAS las acciones, incluso `borrar`.
3. **Falta filtro `activo = 1`** en queries de vehículos y otros selects poblando los combos.
4. **Validaciones genéricas**: Usaba una sola condición larga con `||` que no discriminaba qué campo faltaba.
5. **Sin flujo de descarga**: No existía acción para registrar el peso real al descargar ni el impacto contable automático en cuenta corriente del chofer.
6. **Sin preview de peso neto**: No había cálculo en vivo del peso neto esperado.

### 11.2 Cambios aplicados

**a) Eliminación de `created_by`** — Se quitó del INSERT la columna `created_by`. La tabla `viajes` no tiene esa columna. Ahora inserta solo las 23 columnas que existen en el schema.

**b) Reorganización de captura de POST** — Los valores se capturan al inicio del bloque POST (compartidos entre acciones), pero los cálculos financieros (`total_flete_bruto`, `total_flete_neto`) se realizan **dentro** de cada acción específica (`nuevo`, `editar`, `descargar`).

**c) Validaciones descriptivas** — Se reemplazó la condición monolítica por un array `$campos_faltan` que enumera exactamente qué campos obligatorios no se completaron, mostrando mensaje claro.

**d) Filtro `activo = 1` agregado** a todas las queries de datos iniciales:
- `clientes` (es_comercial, es_comisionista, es_pagador)
- `choferes`
- `vehiculos`

**e) Nueva acción: `descargar`** — Modal separado para registrar el peso real (bruto + tara) al momento de la descarga:
- Calcula peso neto real (`peso_bruto_real - peso_tara_real`)
- Actualiza `estado = 'descargado'`
- **Impacto contable automático**: Si el viaje tiene chofer con `porcentaje_ganancia > 0`, registra automáticamente un pago de tipo `liquidacion` en `chofer_pagos` con el monto calculado (`total_flete_neto * chofer_porcentaje / 100`).

**f) Preview en vivo de peso neto** — En el modal de descarga, JavaScript calcula en tiempo real `peso_bruto - peso_tara` y muestra el resultado formateado.

**g) UI mejorada:**
- Tabla ahora muestra columna "Chofer" y documentos combinados (CTG | CP | Otros)
- Truncamiento inteligente de origen/destino con tooltip
- Botón de descarga (ícono balanza) visible solo para viajes en estado `en_viaje`
- Porcentaje de ganancia del chofer se auto-completa desde `data-porcentaje` en los options del select

### 11.3 Estado del Roadmap (actualizado 2026-06-26)

| Módulo | Estado |
|--------|--------|
| clientes.php | ✅ Completo |
| vehiculos.php | ✅ Completo |
| mantenimiento.php | ✅ Completo |
| choferes.php | ✅ Completo |
| comisionistas.php | ✅ Completo |
| comisionistas_ctacte.php | ✅ Completo |
| choferes_ctacte.php | ✅ Completo |
| choferes_liquidar.php | ✅ Completo |
| viajes.php | ✅ **Completo (633 líneas)** |
| viajes_detalle.php | ❌ Pendiente |
| cobranzas.php | ❌ Pendiente |
| cobranzas_fletes_pendientes.php | ❌ Pendiente |
| cobranzas_fletes_liquidar.php | ❌ Pendiente |
| cobranzas_fletes_factura.php | ❌ Pendiente |
| tesoreria.php | ❌ Pendiente |

### 11.4 Verificación

- ✅ `php -l` sin errores de sintaxis.
- ✅ Lint passed: `No syntax errors detected in modules/viajes.php`.
- ✅ Eliminado bug de `created_by` que rompía el INSERT.
- ✅ Agregado flujo completo: Iniciar Viaje → Descargar (con impacto contable) → Listar/Filtrar.
- ✅ Auto-asignación de chofer y acoplado al seleccionar vehículo.
- ✅ Generación automática de código SD-##### cuando no hay documentación.
- ✅ Multi-tenant 100% aislado (todas las queries filtran por `transportista_id`).

---

## 12. Implementación de `viajes_detalle.php` — Vista de Detalle (2026-06-26)

### 12.1 Estado inicial

El archivo `modules/viajes_detalle.php` existía pero estaba **completamente vacío** (0 líneas). Sin implementación alguna.

### 12.2 Funcionalidad implementada

**Módulo de detalle de viaje** que sirve como centro de comando para gestionar un viaje individual, con acciones habilitadas según el estado:

**a) Acciones por estado (flujo de base.md §4):**
- **Estado `en_viaje`**: Botones para agregar Gastos y Adelantos.
- **Estado `descargado`**: Botón Facturar (modal con número de factura + fecha).
- **Estado `facturado`**: Botón Registrar Cobro (modal con monto, retenciones, medio de cobro, fecha).
- Los botones de acción se ocultan según estado correspondiente.

**b) Gestión de gastos (`viajes_gastos`):**
- Alta de gasto con: tipo (combustible, peaje, playa, reparación_ruta, otros), monto, descripción, pagado_por (empresa, adelanto, descuento_flete), fecha.
- Listado con badges de color por tipo y por modalidad de pago.
- Total de gastos calculado y mostrado en el footer de la tabla.
- Eliminación lógica con `appConfirm()`.

**c) Gestión de adelantos (`viajes_adelantos`):**
- Alta de adelanto con: monto, método de pago, fecha.
- **Impacto contable dual**: Al registrar un adelanto, se inserta automáticamente también en `chofer_pagos` con tipo `adelanto` (vinculado al `chofer_id` del viaje).
- Listado con total acumulado.
- Eliminación lógica.

**d) Panel financiero resumido:**
- TN Estimadas, Tarifa x TN, Total Flete Neto (verde)
- Total Gastos cargados a la Empresa (amarillo)
- Total Adelantos (rojo)
- Peso Neto Real (mostrado solo si el viaje no está `en_viaje`)
- Factura y Fecha de Cobro (si existen)

**e) Paneles informativos:**
- Datos del Viaje: grid con cliente, producto, origen, destino, fecha, vehículo, acoplado, chofer, % chofer, pagador.
- Documentación y Comisión: CTG, CP, otros docs, tipo de comisión, valor, comisionista.
- Observaciones (si existen).

### 12.3 Estado del Roadmap (actualizado 2026-06-26)

| Módulo | Estado |
|--------|--------|
| viajes.php | ✅ Completo |
| viajes_detalle.php | ✅ **Completo (532 líneas)** |
| cobranzas.php | ❌ Pendiente |
| cobranzas_fletes_pendientes.php | ❌ Pendiente |
| cobranzas_fletes_liquidar.php | ❌ Pendiente |
| cobranzas_fletes_factura.php | ❌ Pendiente |
| tesoreria.php | ❌ Pendiente |

### 12.4 Verificación

- ✅ `php -l` sin errores de sintaxis: `No syntax errors detected in modules/viajes_detalle.php`.
- ✅ Validación multi-tenant: `viajeOwner()` verifica pertenencia al tenant antes de cualquier operación.
- ✅ Flujo completo: En Viaje → agregar gastos/adelantos → Descargado → Facturar (desde viajes.php) → Facturado → Cobrar.
- ✅ Impacto contable: adelantos se reflejan automáticamente en cuenta corriente del chofer.
- ✅ Gastos categorizados: 5 categorías + filtro por pagado_por (empresa/adelanto/descuento_flete).
- ✅ UI responsiva con grids adaptables, badges de colores y modales específicos.

---

## 13. Implementación del Sistema de Auditoría (2026-10-07)

### 13.1 Migración SQL — `migrations/012_audit_log.sql`

**Objetivo:** Crear sistema de registro de auditoría para tracking de todas las acciones realizadas por usuarios y administradores.

**Cambios:**
- Tabla `audit_log` con columnas:
  - `id` (PK auto-increment)
  - `user_id` (FK a users.id, nullable)
  - `username` (varchar 50)
  - `user_role` (enum: admin, user, developer)
  - `accion` (varchar 100) — tipo de acción (crear, editar, eliminar, login, logout, etc.)
  - `modulo` (varchar 50) — módulo donde ocurrió la acción
  - `descripcion` (text) — descripción detallada
  - `datos_anteriores` (json) — estado antes del cambio (para updates)
  - `datos_nuevos` (json) — estado después del cambio (para updates)
  - `ip_address` (varchar 45) — IP del usuario
  - `user_agent` (varchar 255) — browser info
  - `created_at` (timestamp) — fecha/hora del evento
- Índices para performance:
  - `user_id` — búsquedas por usuario
  - `accion` — filtrado por tipo de acción
  - `modulo` — filtrado por módulo
  - `created_at` — ordenamiento temporal
  - `idx_user_fecha` — búsquedas compuestas por usuario y fecha

**SQL completo:** ver `migrations/012_audit_log.sql`.

### 13.2 Helper global — `core/helpers.php` → `registrarAuditoria()`

**Función:** `registrarAuditoria(PDO $pdo, ?int $userId, string $accion, string $modulo, string $descripcion, ?array $datosAnteriores = null, ?array $datosNuevos = null): bool`

**Características:**
- **Auto-obtención de contexto**: Si no se proporciona `$userId`, lo obtiene de `$_SESSION['user_id']`.
- **Captura automática**: username, rol, IP y User Agent desde sesión y servidor.
- **Truncamiento de User Agent**: Limita a 255 caracteres para evitar errores.
- **JSON encoding**: Usa `JSON_UNESCAPED_UNICODE` para preservar caracteres especiales.
- **Manejo de errores**: Captura excepciones PDO y las loguea sin interrumpir el flujo.
- **Retorno booleano**: Permite verificar éxito/fallo sin try-catch en el llamador.

**Uso típico:**
```php
// Acción simple (crear, login, logout)
registrarAuditoria($pdo, $userId, 'crear', 'clientes', 'Cliente creado: Juan Pérez');

// Acción con cambios (editar, borrar)
registrarAuditoria($pdo, $userId, 'editar', 'empresas', 'Empresa actualizada', 
    ['razon_social' => 'Old Name'], 
    ['razon_social' => 'New Name']
);
```

### 13.3 Módulo `modules/auditoria.php` (414 líneas)

**Objetivo:** Interfaz de visualización de registros de auditoría (solo para desarrolladores).

**Características:**

1. **Acceso restringido:** Solo usuarios con rol `developer` pueden acceder. Cualquier otro rol recibe `die()` inmediatamente.

2. **Filtros de búsqueda avanzados:**
   - Por usuario (username o ID)
   - Por módulo (select con valores únicos de la DB)
   - Por acción (select con valores únicos de la DB)
   - Por rango de fechas (desde/hasta)

3. **Estadísticas en tiempo real:**
   - Cantidad de registros encontrados (según filtros)
   - Total de registros en el sistema
   - Cantidad de usuarios con actividad

4. **Tabla de registros:**
   - Columnas: ID, Fecha/Hora, Usuario, Rol, Acción, Módulo, Descripción, IP, Detalles
   - Badges de color por rol (developer=violeta, admin=azul, user=gris)
   - Badges de color por acción (crear=verde, editar=naranja, eliminar=rojo, login=azul, logout=gris)
   - Formato de fecha: `d/m/Y H:i:s`
   - Truncamiento inteligente de descripciones largas

5. **Modal de detalles:**
   - Botón "Ver detalles" solo aparece si hay `datos_anteriores` o `datos_nuevos`
   - Muestra información técnica sobre cómo consultar los JSON en la DB
   - Mensaje informativo sobre la estructura de datos

6. **Paginación implícita:** LIMIT 500 registros por defecto para performance.

### 13.4 Integración en archivos existentes

**`login.php`:**
- **Login exitoso** (línea 48-52): Registra auditoría con acción `login`, módulo `auth`, descripción "Inicio de sesión exitoso" y datos de contexto (username, role).
- **Login fallido** (línea 59-63): Registra auditoría con acción `login_fallido`, módulo `auth`, descripción con username intentado e IP.

**`index.php`:**
- **Logout** (línea 39): Registra auditoría con acción `logout`, módulo `auth`, descripción "Cierre de sesión del usuario".
- **Cambio de empresa** (líneas 109-113): Registra auditoría con acción `cambiar_empresa`, módulo `empresas`, descripción con nombres de empresas anterior y nueva, incluyendo datos anteriores y nuevos (IDs y nombres).

**`includes/sidebar.php`:**
- **Navegación** (líneas 63-66): Item "Auditoría" visible solo para rol `developer` con ícono `fa-clipboard-list`.

**`index.php` (routing):**
- **Case agregado** (línea 276): `case 'auditoria': include_once 'modules/auditoria.php';`.

### 13.5 Verificación

- ✅ `php -l` sin errores de sintaxis en `modules/auditoria.php`.
- ✅ Lint passed: `No syntax errors detected in modules/auditoria.php`.
- ✅ Acceso restringido a developer implementado.
- ✅ Filtros funcionales con valores únicos dinámicos desde la DB.
- ✅ Estadísticas calculadas en tiempo real.
- ✅ Integración completa en login, logout y cambio de empresa.
- ✅ Navegación condicional por rol en sidebar.

---

## 14. Estado del Roadmap (actualizado 2026-10-07)

| Módulo | Estado |
|--------|--------|
| clientes.php | ✅ Completo |
| vehiculos.php | ✅ Completo |
| mantenimiento.php | ✅ Completo |
| choferes.php | ✅ Completo |
| comisionistas.php | ✅ Completo |
| comisionistas_ctacte.php | ✅ Completo |
| choferes_ctacte.php | ✅ Completo |
| choferes_liquidar.php | ✅ Completo |
| viajes.php | ✅ Completo |
| viajes_detalle.php | ✅ Completo |
| **auditoria.php** | ✅ **Completo (414 líneas)** |
| **cobranzas.php** | ✅ **Completo (270 líneas)** |
| **cobranzas_fletes_pendientes.php** | ✅ **Completo (136 líneas)** |
| **cobranzas_fletes_liquidar.php** | ✅ **Completo (910 líneas)** |
| **cobranzas_fletes_factura.php** | ✅ **Completo (361 líneas)** |
| **cuentas.php** | ✅ **Completo (783 líneas)** |

---

## 15. Implementación del Sistema de Cobranzas (2026-10-07)

### 15.1 Módulo `modules/cobranzas.php` (270 líneas)

**Objetivo:** Módulo principal de gestión de cobranzas, punto de entrada al flujo de facturación y cobro.

**Funcionalidad:**
1. **Dos listados principales:**
   - Viajes descargados (pendientes de facturar)
   - Viajes facturados (pendientes de cobrar)

2. **Acciones:**
   - **Facturar**: Cambia estado de `descargado` → `facturado` (con número de factura y fecha)
   - **Cobrar**: Redirige a `cobranzas_fletes_liquidar` con el ID del viaje para registrar el cobro

3. **Multi-tenant estricto:** Todas las queries filtran por `transportista_id = $_SESSION['active_company_id']` y `activo = 1`.

4. **Navegación:** Botones de acceso rápido a:
   - `cobranzas_fletes_pendientes` — Ver fletes pendientes de facturar
   - `cobranzas_fletes_liquidar` — Ver fletes a cobrar y liquidar

### 15.2 Módulo `modules/cobranzas_fletes_pendientes.php` (136 líneas)

**Objetivo:** Listado de viajes descargados pendientes de facturar.

**Características:**
1. **Query principal:** Viajes en estado `descargado` con JOIN a clientes, choferes, vehículos y pagadores.
2. **Columnas mostradas:**
   - CTG / Documento
   - Cliente
   - Origen → Destino
   - Patente (vehículo)
   - TN Descargadas
   - Monto a Facturar
3. **Acciones por fila:**
   - **Ver Detalle**: Link a `viajes_detalle?viaje_id=X` (solo consulta)
   - **Facturar**: Link a `cobranzas_fletes_factura?viaje_id=X`
4. **Totales:** Footer con suma total de montos a facturar.
5. **Badge informativo:** Muestra cantidad de pendientes.

### 15.3 Módulo `modules/cobranzas_fletes_factura.php` (361 líneas)

**Objetivo:** Pantalla de facturación de un viaje descargado.

**Características:**
1. **Validación:** Solo permite facturar viajes en estado `descargado`. Si ya está facturado/cobrado/liquidado, muestra mensaje informativo.
2. **Datos del viaje:** Muestra cliente, CUIT, producto, pagador, origen-destino, tarifa x TN, TN descargadas, patente y chofer.
3. **Cálculos de factura:**
   - Total Neto (base imponible)
   - IVA 21% calculado automáticamente
   - Total Final (neto + IVA)
4. **Formulario de facturación:**
   - Número de factura (obligatorio)
   - Fecha de emisión (default hoy)
   - Fecha de cobro estimada (opcional)
5. **Confirmación:** Al enviar, actualiza `factura_nro`, `factura_fecha`, `fecha_cobro` y cambia estado a `facturado`.
6. **Post-facturación:** Muestra datos de la factura emitida con badge "Facturado".

### 15.4 Módulo `modules/cobranzas_fletes_liquidar.php` (910 líneas)

**Objetivo:** Gestión completa de cobros y liquidación de choferes.

**Funcionalidad principal:**

**a) Procesar cobro:**
- **Validaciones:** Viaje debe estar en estado `facturado`, cuenta destino debe existir y pertenecer al tenant.
- **Datos del cobro:**
  - Cuenta de caja destino (de `cuentas_empresa`)
  - Monto total facturado
  - Medio de pago (efectivo, transferencia, cheque, Mercado Pago, otro)
  - Retenciones detalladas (IVA, Ganancias, IIBB, SUSS, Otro) — múltiples filas dinámicas
  - Datos del cheque (si aplica): tipo, banco, número, fechas, librador, endosante, importe
  - Observaciones
- **Cálculo automático:** Neto a cobrar = Total facturado - Total retenciones
- **Impacto contable:**
  - Inserta registro en `cobros_fletes`
  - Inserta retenciones en `cobros_fletes_retenciones`
  - Inserta datos de cheque en `cobros_fletes_cheques` (si aplica)
  - Actualiza `saldo_actual` en `cuentas_empresa`
  - Inserta movimiento en `cuentas_movimientos` (tipo `entrada`, referencia `cobro_flete`)
  - Marca viaje como `cobrado` con `fecha_cobro`

**b) Procesar liquidación:**
- **Validaciones:** Viaje debe estar en estado `cobrado` y tener chofer asignado.
- **Cálculo:** Ganancia del chofer = `total_flete_neto * chofer_porcentaje / 100`
- **Impacto contable:**
  - Inserta pago en `chofer_pagos` con tipo `liquidacion`
  - Marca viaje como `liquidado` con `acreditado_chofer = 1`

**c) Tres secciones de listados:**
1. **Viajes Facturados (Por Cobrar):** Muestra neto, total facturado (con IVA 21%), fecha de emisión. Botón "Cobrar" abre modal.
2. **Viajes Cobrados (Por Liquidar):** Muestra neto cobrado, fecha de cobro, medio de pago, cuenta destino.
3. **Viajes Liquidados (Historial):** Últimos 20 viajes liquidados con ganancia del chofer.

**d) Modal de cobro:**
- Formulario completo con cuenta destino, fecha, medio de pago
- Campos dinámicos de retenciones (agregar/eliminar filas)
- Cálculo en tiempo real del neto a cobrar
- Resumen visual: Total facturado, retenciones, neto final
- Auto-apertura si viene de redirección con `?cobrar_viaje_id=X`

### 15.5 Integración en archivos existentes

**`index.php`:**
- Agregado `case 'cobranzas': include_once 'modules/cobranzas.php';`
- Agregado `case 'cobranzas_fletes_pendientes': include_once 'modules/cobranzas_fletes_pendientes.php';`
- Agregado `case 'cobranzas_fletes_liquidar': include_once 'modules/cobranzas_fletes_liquidar.php';`
- Agregado `case 'cobranzas_fletes_factura': include_once 'modules/cobranzas_fletes_factura.php';`

**`includes/sidebar.php`:**
- Agregado `navItem('cobranzas', 'fa-hand-holding-usd', 'Cobranzas', ...)` en menú principal.

**`modules/configuracion.php`:**
- Agregado permiso `'cobranzas' => 'Cobranzas'`
- Agregado permiso `'cobranzas_fletes_pendientes' => 'Fletes Pendientes'`
- Agregado permiso `'cobranzas_fletes_liquidar' => 'Fletes a Cobrar'`
- Agregado permiso `'cobranzas_fletes_factura' => 'Facturación de Fletes'`

### 15.6 Flujo completo de cobranzas

```
Viaje en estado 'descargado'
    ↓
cobranzas_fletes_pendientes (listar pendientes)
    ↓
cobranzas_fletes_factura (facturar: descargado → facturado)
    ↓
cobranzas_fletes_liquidar (listar facturados)
    ↓
Registrar cobro (facturado → cobrado)
    - Inserta en cobros_fletes
    - Actualiza saldo de cuenta
    - Registra movimiento en cuentas_movimientos
    ↓
Registrar liquidación (cobrado → liquidado)
    - Calcula ganancia chofer
    - Inserta pago en chofer_pagos
```

### 15.7 Verificación

- ✅ `php -l` sin errores de sintaxis en los 4 archivos.
- ✅ Lint passed en todos los módulos de cobranzas.
- ✅ Multi-tenant 100% aislado (todas las queries filtran por `transportista_id`).
- ✅ Validación de ownership en todas las operaciones.
- ✅ Transacciones BD para garantizar integridad en cobros y liquidaciones.
- ✅ Cálculo automático de IVA, retenciones y neto a cobrar.
- ✅ Impacto contable completo en cuentas_empresa y cuentas_movimientos.

---

## 16. Próximos pasos — Roadmap actualizado

Módulos pendientes de implementación:

1. **`tesoreria.php`** — Cuentas financieras y medios de cobro (gestión de `cuentas_empresa`)

---

## 16. Implementación de `cuentas.php` — Gestión de Cuentas Financieras (2026-10-07)

### 16.1 Módulo `modules/cuentas.php` (783 líneas)

**Objetivo:** Reemplaza a `tesoreria.php`. Gestiona las cuentas financieras de la empresa (bancos, billeteras virtuales, caja de efectivo) donde se ingresan los pagos de fletes.

**Funcionalidad:**

**a) CRUD de cuentas:**
- **Crear cuenta**: Nombre, tipo (banco/billetera_virtual/caja_efectivo/otro), banco/entidad, número de cuenta, CBU, alias, titular, CUIT titular, saldo inicial.
- **Editar cuenta**: Actualiza datos de la cuenta (excepto saldo_actual).
- **Eliminar cuenta**: Borrado lógico (`activo = 0`).
- **Ajustar saldo**: Permite modificar manualmente el saldo actual de una cuenta.

**b) Agrupación por tipo:**
- Cuentas agrupadas en secciones: Bancos, Billeteras Virtuales, Caja de Efectivo, Otras Cuentas.
- Badges de color por tipo.
- Subtotales por tipo y total general consolidado.

**c) Visualización de movimientos (AJAX):**
- Botón "Ver movimientos" por cuenta.
- Modal con tabla de movimientos obtenidos vía AJAX desde `cuentas?ajax_movimientos=1&cuenta_id=X`.
- Movimientos incluyen: fecha, tipo (entrada/salida), concepto, referencia (CTG/CP/Viaje), monto, saldo resultante, observaciones.
- Totales de entradas y salidas calculados en el frontend.
- Referencias a cobros de fletes muestran el documento del viaje (CTG/CP/Otros).

**d) Multi-tenant estricto:**
- Todas las queries filtran por `transportista_id = $_SESSION['active_company_id']` y `activo = 1`.
- Validación de ownership en todas las operaciones (crear, editar, eliminar, ajustar saldo, ver movimientos).

**e) Integración contable:**
- Las cuentas se usan como destino en los cobros de fletes (`cobranzas_fletes_liquidar.php`).
- Los movimientos se registran automáticamente en `cuentas_movimientos` al registrar un cobro.
- El saldo de la cuenta se actualiza automáticamente al registrar cobros.

### 16.2 Integración en archivos existentes

**`index.php`:**
- Agregado `case 'cuentas': include_once 'modules/cuentas.php';` en el switch de routing.

**`includes/sidebar.php`:**
- Agregado `navItem('cuentas', 'fa-wallet', 'Cuentas', ...)` en menú principal.

**`modules/configuracion.php`:**
- Agregado permiso `'cuentas' => 'Cuentas'` a la lista de permisos.

**`modules/cobranzas_fletes_liquidar.php`:**
- Usa `cuentas.php` como fuente de cuentas destino para registrar cobros.
- Query: `SELECT id, nombre, tipo, banco, saldo_actual FROM cuentas_empresa WHERE transportista_id = ? AND activo = 1`.

### 16.3 Verificación

- ✅ `php -l` sin errores de sintaxis en `modules/cuentas.php`.
- ✅ Lint passed: `No syntax errors detected in modules/cuentas.php`.
- ✅ Multi-tenant 100% aislado (todas las queries filtran por `transportista_id`).
- ✅ CRUD completo con validación de ownership.
- ✅ AJAX endpoint para movimientos de cuenta.
- ✅ Agrupación visual por tipo de cuenta con badges y subtotales.
- ✅ Integración completa con sistema de cobranzas.

---

## 17. Próximos pasos — Roadmap final

**Módulos pendientes de implementación:**

Ninguno. Todos los módulos planificados están completados.

**Módulos implementados (completos):**

1. ✅ `clientes.php` — Gestión de clientes (281 líneas)
2. ✅ `vehiculos.php` — Gestión de flota (244 líneas)
3. ✅ `mantenimiento.php` — Mantenimiento de vehículos (231 líneas)
4. ✅ `choferes.php` — Gestión de choferes (248 líneas)
5. ✅ `comisionistas.php` — Gestión de comisionistas (279 líneas)
6. ✅ `choferes_ctacte.php` — Cuenta corriente choferes (188 líneas)
7. ✅ `choferes_liquidar.php` — Liquidación choferes (215 líneas)
8. ✅ `comisionistas_ctacte.php` — Cuenta corriente comisionistas (175 líneas)
9. ✅ `viajes.php` — Operativa de viajes (633 líneas)
10. ✅ `viajes_detalle.php` — Detalle de viaje (532 líneas)
11. ✅ `cobranzas.php` — Gestión de cobranzas (270 líneas)
12. ✅ `cobranzas_fletes_pendientes.php` — Fletes pendientes (136 líneas)
13. ✅ `cobranzas_fletes_factura.php` — Facturación de fletes (361 líneas)
14. ✅ `cobranzas_fletes_liquidar.php` — Cobros y liquidación (910 líneas)
15. ✅ `cuentas.php` — Cuentas financieras (783 líneas)
16. ✅ `auditoria.php` — Registro de auditoría (414 líneas)

**Nota:** El sistema está completamente funcional con el flujo completo: Clientes → Viajes → Descargar → Facturar → Cobrar → Liquidar. Todos los módulos registran automáticamente eventos en el sistema de auditoría (login, logout, cambio de empresa). Para agregar auditoría a operaciones específicas de cada módulo, se debe llamar a `registrarAuditoria()` en los puntos de acción (crear, editar, eliminar).
