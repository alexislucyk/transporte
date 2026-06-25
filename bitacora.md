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
| comisionistas.php | ❌ Pendiente |
| comisionistas_ctacte.php | ❌ Pendiente |
| choferes_ctacte.php | ❌ Pendiente |
| choferes_liquidar.php | ❌ Pendiente |
| viajes.php | ❌ Pendiente |
| viajes_detalle.php | ❌ Pendiente |
| cobranzas.php | ❌ Pendiente |
| cobranzas_fletes_pendientes.php | ❌ Pendiente |
| cobranzas_fletes_liquidar.php | ❌ Pendiente |
| cobranzas_fletes_factura.php | ❌ Pendiente |
| tesoreria.php | ❌ Pendiente |
```
