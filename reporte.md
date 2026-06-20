# Reporte técnico del proyecto (Trans Cargo Hub)

> Alcance: este reporte describe la arquitectura, flujo principal, módulos y base de datos del proyecto PHP/MySQL ubicado en `c:/laragon/www/trans_dev`.

---

## 1) Resumen ejecutivo

- El proyecto es una aplicación web monolítica en **PHP** con renderizado server-side y un **front controller** único en `index.php`.
- Usa **PDO** para acceso a MySQL.
- Implementa:
  - Autenticación (login/logout) basada en tabla `users`.
  - Control de permisos por módulo mediante `user_permissions`.
  - Gestión multi-empresa (tabla `transportistas`) usando `$_SESSION['active_company_id']`.
  - Operativa principal: **Viajes**, **Choferes**, **Vehículos**, **Cobranzas/Liquidaciones**, **Tesorería**.
- La UI está implementada con CSS embebido en `index.php` (no hay un sistema de componentes) y modales genéricos por módulo.

---

## 2) Estructura del repositorio (alto nivel)

Archivos y carpetas relevantes:

- `index.php`: front controller + layout (sidebar, theme, enrutamiento) + inclusiones de módulos.
- `login.php`, `logout.php`: autenticación.
- `config/`: `config/db.php` (driver/credentials) para PDO.
- `core/`: `core/helpers.php` (helpers de formato).
- `modules/`: módulos funcionales:
  - `dashboard.php`
  - `choferes.php`
  - `choferes_ctacte.php`
  - `choferes_liquidar.php`
  - `vehiculos.php`
  - `viajes.php`
  - `viajes_detalle.php` (referenciado desde `viajes.php`, no inspeccionado en esta corrida)
  - `clientes.php` (referenciado, no inspeccionado en esta corrida)
  - `comisionistas.php` (referenciado, no inspeccionado)
  - `transportistas.php`
  - `configuracion.php`
  - `tesoreria.php`
  - `mantenimiento.php` (referenciado, no inspeccionado)

Base SQL:
- `database_schema.sql`: define el esquema de BD.

---

## 3) Flujo de ejecución principal (Front Controller)

### 3.1 Entrada y sesión

En `index.php`:
1. `session_start()` y sincronización de vida útil de sesión: `1800` segundos.
2. Carga dependencias:
   - `require_once 'config/db.php'`
   - `require_once 'core/helpers.php'`
3. Protección de acceso:
   - Si no hay `$_SESSION['user_id']` y no se está en `login.php`, redirige a `login.php`.

### 3.2 Enrutamiento

- Calcula `$base_path` para construir un `<base href>`.
- Obtiene `$route` por:
  - `$_GET['route']` o la ruta real (`$_SERVER['REQUEST_URI']`).
- Normaliza `module` y `action` con `explode('/', $route)`.

### 3.3 Logout

- Si `$route === 'logout'`, limpia sesión y redirige a `login.php`.

### 3.4 Multi-empresa

- En `index.php` se arma el selector de empresa activa con `$todas_empresas`:
  - `developer`: ve todas las empresas.
  - otros roles: ve solo empresas con `created_by = $_SESSION['user_id']`.
- Si `$_POST['set_active_company']` está seteado, actualiza `$_SESSION['active_company_id']` (validado contra la lista disponible) y redirige.

### 3.5 Theme

- Lee `configuraciones.valor` donde `clave='tema'`.
- Define paletas para `corporativo`, `medio`, `dark` y expone CSS variables.

### 3.6 Permisos por rol

- Si el rol no es `admin`/`developer` y el módulo no está en `$_SESSION['user_permissions']`, muestra “Acceso Restringido”.

### 3.7 Inclusión de módulos

`index.php` hace `include_once` según `$module`:
- `dashboard` -> `modules/dashboard.php`
- `choferes` -> `modules/choferes.php`
- `vehiculos` -> `modules/vehiculos.php`
- `transportistas` -> `modules/transportistas.php`
- `clientes` -> `modules/clientes.php`
- `comisionistas` -> `modules/comisionistas.php`
- `viajes` -> `modules/viajes.php`
- `cobranzas` -> `modules/choferes_liquidar.php`
- `mantenimiento` -> `modules/mantenimiento.php`
- `configuracion` -> `modules/configuracion.php`
- `tesoreria` -> `modules/tesoreria.php`

---

## 4) Autenticación y usuarios

### 4.1 `login.php`

- Procesa `POST` con `username` y `password`.
- Consulta `users` por `username`.
- Verifica `password_verify()`.
- Setea sesión:
  - `user_id`
  - `user_name`
  - `user_role`
  - `user_permissions` (SELECT desde `user_permissions`)
- Redirige a `dashboard`.

### 4.2 `logout.php`

- `session_start()`, limpia `$_SESSION`, hace `session_destroy()`, redirige a `login.php`.

### 4.3 Base de datos (tabla `users`)

En `database_schema.sql`:
- `users` con `username` único, `password` (hash bcrypt), `full_name`, `role` enum.
- Inserta un admin demo: `admin / admin123` (hash incluido en el SQL proporcionado).

---

## 5) Base de datos: modelo de dominio (resumen)

`database_schema.sql` define:

### 5.1 Entidades principales

- `transportistas`: empresa/owner de la flota.
- `choferes`: choferes (ligados a `transportistas`).
- `vehiculos`: unidades (ligadas a `transportistas` y opcionalmente `chofer_id`).
- `clientes`: clientes que también pueden ser:
  - `es_comercial`
  - `es_pagador`
  - `es_comisionista`

### 5.2 Viajes

Tabla `viajes` con:
- Relaciones: `transportista_id`, `cliente_id`, `chofer_id`, `vehiculo_id`, `comisionista_id`, `pagador_id`.
- Datos operativos: `origen`, `destino`, `producto`, `fecha_carga`, pesajes (`peso_bruto`, `peso_tara`, `peso_neto`).
- Finanzas:
  - `tarifa_tonelada`
  - `total_flete_bruto`, `total_flete_neto`
  - `chofer_porcentaje`
  - comisión: `comision_tipo`, `comision_valor`, `comisionista_id`, `comision_pagada`
  - facturación y cobro: `factura_nro`, `factura_fecha`, `fecha_cobro`
- Estados: `en_viaje`, `descargado`, `facturado`, `cobrado`, `liquidado`.

### 5.3 Finanzas dependientes

- `viajes_gastos`: gastos del viaje.
- `viajes_adelantos`: adelantos vinculados al viaje.
- `mantenimientos`: mantenimiento de vehículos.
- `chofer_pagos`: cuenta corriente/ movimientos de choferes (`adelanto`, `sueldo`, `liquidacion`, `otro`).
- `comisionista_pagos`: pagos a intermediarios (comisionistas).

### 5.4 Configuraciones

- `configuraciones`: clave/valor (por ejemplo tema).

---

## 6) Módulos funcionales (detalle)

### 6.1 `modules/transportistas.php`

- Permite CRUD de empresas.
- Filtra empresas por role:
  - developer: todas
  - otros: `created_by = $_SESSION['user_id']`
- Maneja `POST action=nuevo|editar`.
- UI: tabla + modal con inputs.

### 6.2 `modules/configuracion.php`

Dos áreas principales:

1. **Apariencia**
   - Radio buttons del tema.
   - Actualiza `configuraciones.valor`.

2. **Gestión de Usuarios**
   - CRUD parcial por POST:
     - `nuevo_usuario`
     - `eliminar_usuario`
     - `cambiar_password`
     - `actualizar_permisos` (reemplaza `user_permissions` del usuario)
   - Para `developer` lista usuarios completos.
   - Modales para cambiar contraseña y permisos.

### 6.3 `modules/dashboard.php`

- Calcula métricas por estado de `viajes` y vencimientos:
  - `en_viaje`
  - `descargado`
  - `facturado`
  - pendientes de liquidación (`acreditado_chofer = 0` en viajes no `en_viaje`)
  - alertas VTV y licencia próximas a 30 días.
- Muestra “Actividad reciente” de los últimos 5 viajes.

### 6.4 `modules/viajes.php`

- Lógica del alta de viaje (`POST action=nuevo`):
  - congela `porcentaje_ganancia` desde `choferes`.
  - calcula `total_flete` como `peso_estimado * tarifa_tonelada`.
  - inserta en `viajes` y setea estado inicial `en_viaje`.
- Carga selectores:
  - clientes `es_comercial` como dadores de carga.
  - clientes `es_pagador` y `es_comisionista`.
  - choferes activos.
  - vehiculos (camiones + acoplado como texto).
- Listado por `fecha_carga` y estado con badge.
- Acción de detalle:
  - link `viajes/detalle/{id}`.

### 6.5 `modules/choferes.php`

- Permite CRUD de choferes (POST action=nuevo|editar).
- Valida CUIL: 11 dígitos numéricos.
- Calcula saldo por chofer con subqueries a `chofer_pagos`:
  - saldo = sum(liquidacion) - sum(otros tipos).
- UI incluye:
  - modal de alta/edición
  - link a `choferes/ctacte/{id}`.

### 6.6 `modules/choferes_ctacte.php`

- Muestra “Cuenta Corriente” de un chofer.
- Maneja:
  - Registro manual de movimientos (`registrar_pago`).
  - Acreditar flete por viaje (`action=acreditar_flete`):
    - inserta en `chofer_pagos` con tipo `liquidacion`
    - marca `viajes.acreditado_chofer = 1`.
- Trae movimientos desde `chofer_pagos` y arma tabla debe/haber por la lógica `haber=liquidacion`.
- Muestra lista de “fletes pendientes” para acreditar.

### 6.7 `modules/choferes_liquidar.php` (cobranzas y liquidaciones)

Este módulo concentra el flujo post-viaje:

- Si `get_viaje_info` está seteado, funciona como endpoint interno (AJAX) y devuelve JSON con:
  - viaje
  - gastos
  - adelantos

- Procesa POST con múltiples acciones:
  - `registrar_factura` (set factura y estado `facturado`)
  - `registrar_cobro` (set fecha_cobro y estado `cobrado`)
  - `acreditar_chofer` (inserta `chofer_pagos` tipo `liquidacion` y set `acreditado_chofer=1`)
  - `pagar_comision` (inserta `comisionista_pagos` y set `comision_pagada=1`)
  - `editar_viaje_liq` (actualiza parámetros y recalcula `total_flete_neto`)
  - Manejo genérico de `movimiento`:
    - guarda `viajes_gastos` o `viajes_adelantos`
  - eliminaciones:
    - `delete_gasto`, `delete_adelanto`.

- Presenta tres listados principales:
  1. Viajes pendientes de cierre financiero (descargados/facturados/cobrados) con estado de acreditación y comisión.
  2. Facturas pendientes de cobro.
  3. (Dentro del modal) carga dinámica de gastos/adelantos para liquidación.

- UI compleja: modales para liquidación, editar viaje/comisiones, cargar gasto, adelanto, generar factura y registrar cobro.

### 6.8 `modules/tesoreria.php`

- Consolida flujo de caja por empresa:
  - ingresos reales: `viajes` en `cobrado`/`liquidado`
  - egresos reales:
    - pagos a choferes (`chofer_pagos` tipo != liquidacion)
    - gastos de empresa (viajes_gastos.pagado_por='empresa')
    - mantenimientos (m.costo_total)
    - pagos a comisionistas
- Saldo de caja = ingresos - egresos.
- Cálculo de deudas:
  - `por_cobrar`: suma `total_flete_neto` de viajes en `descargado`/`facturado`
  - `saldo_total_choferes`: recorre choferes y calcula saldo usando subqueries incluyendo adelantos al chofer.

---

## 7) Seguridad y riesgos detectados (observaciones)

### 7.1 Credenciales en claro

- `config/db.php` contiene DB_PASS en texto plano.

### 7.2 Falta de CSRF

- Formularios POST no incorporan tokens CSRF.

### 7.3 Validación incompleta en endpoints

- Existen acciones POST en módulos con parámetros `id`, `viaje_id`, etc. No se valida ownership contra `transportista_id`/`active_company_id` en todas las ramas (riesgo de acceso cruzado si se manipula ID).

### 7.4 Repetición/duplicación de lógica

- `choferes.php` y `choferes_ctacte.php`/`choferes_liquidar.php` comparten concepto de movimiento, saldo y acreditación pero la implementación está distribuida.

### 7.5 Consistencia de estados

- El flujo depende de banderas como `acreditado_chofer` y estados `viajes.estado`, más `comision_pagada`. No hay una “máquina de estados” central, lo que puede permitir combinaciones inconsistentes si se ejecutan acciones en orden no previsto.

---

## 8) Problemas de calidad/bugs probables (a verificar)

- En `index.php`, hay un bug/fragilidad: se usa `$pdo` directamente en el front controller sin que esté garantizado que `config/db.php` inicializa `$pdo` correctamente (aunque `require_once 'config/db.php'` se ejecuta antes).
- `index.php` calcula `$base_path` con `str_replace('index.php', '', $_SERVER['SCRIPT_NAME'])`; si la instalación no está en la ruta esperada puede romper el `<base href>`.
- `core/helpers.php` solo tiene helpers de formato; módulos grandes implementan formato adicional (JS) y estilos locales.

---

## 9) Recomendaciones de mejora (priorizadas)

1. **Seguridad**
   - Introducir CSRF token para todos los formularios.
   - Mover credenciales DB a variables de entorno.
   - Validar permisos/ownership en cada endpoint POST usando `active_company_id`.

2. **Arquitectura**
   - Separar lógica (servicios) de presentación (views).
   - Centralizar cálculo de saldos/movimientos para evitar divergencias.

3. **Dominio y consistencia**
   - Definir transición de estados permitidas de `viajes.estado` y validar transiciones al ejecutar acciones.

4. **Mantenibilidad**
   - Extraer CSS/JS a archivos estáticos.
   - Estandarizar nombres de acciones (`action`/`movement`) y retornos JSON.

---

## 10) Archivos inspeccionados en esta corrida

- `index.php`
- `login.php`
- `logout.php`
- `config/db.php`
- `core/helpers.php`
- `modules/configuracion.php`
- `modules/transportistas.php`
- `modules/dashboard.php`
- `modules/viajes.php`
- `modules/choferes.php`
- `modules/choferes_ctacte.php`
- `modules/choferes_liquidar.php`
- `modules/vehiculos.php`
- `modules/tesoreria.php`
- `database_schema.sql`

---

## 11) Nota sobre cobertura

No se leyó en esta corrida la totalidad de módulos referenciados (`clientes.php`, `comisionistas.php`, `mantenimiento.php`, `viajes_detalle.php`, subcarpetas `modules/*/`). Este reporte se basa en el código inspeccionado.

