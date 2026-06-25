# ESPECIFICACIÓN TÉCNICA: SISTEMA DE GESTIÓN DE TRANSPORTE (SGT)

## 1. ARQUITECTURA Y REQUISITOS GENERALES
* **Stack Tecnológico:** PHP (Backend), JS Nativo (Frontend), HTML5, CSS3 Puro (Modular: archivos por página/función importados en `main.css`).
* **Arquitectura de Datos:** Multi-tenant (Aislamiento de datos por Empresa/Administrador).
* **Regla Crítica de Aislamiento:** Un `Administrador` (y sus usuarios dependientes) **no puede** ver, editar ni listar datos, empresas o usuarios pertenecientes a otro `Administrador`. El rol `Developer` tiene bypass total (Tenant ID = NULL o Global).

---

## 2. CONTROL DE ACCESOS (RBAC)
* **Developer:** Acceso total e irrestricto al sistema. Único rol capaz de crear usuarios con rol `Administrador`.
* **Administrador:** Creado por el Developer. Vinculado a su propio Tenant (Empresa/s). Puede crear usuarios subordinados dentro de su Tenant y asignarles permisos específicos por página y función (Ej: `crear_viaje`, `solo_lectura_clientes`).
* **Usuarios Staff:** Creados por el Administrador. Tienen restricciones según los permisos asignados por su Administrador.

---

## 3. MODELO DE DATOS Y ENTIDADES (MÓDULOS BASE)

### Módulo: CLIENTES (`clientes`)
* **Campos:** ID, Tenant_ID, Razón_Social/Nombre, CUIT/DNI, Tipo (Cliente, Comisionista, Pagador de Flete o Todos), Dirección, Teléfono, Estado.

### Módulo: CHOFERES (`choferes`)
* **Campos:** ID, Tenant_ID, Nombre_Completo, DNI, Teléfono, %_Ganancia_Flete (Valor fijo por chofer), Estado.

### Módulo: VEHÍCULOS (`vehiculos`)
* **Campos:** ID, Tenant_ID, Patente_Camión, Patente_Acoplado, Marca, Modelo, Estado.

---

## 4. LÓGICA DE NEGOCIO: MÓDULO VIAJES
Un viaje es un proceso que pasa por 3 estados secuenciales: **1. Iniciado** $\rightarrow$ **2. Liquidación** $\rightarrow$ **3. Cobro**.

### ESTADO 1: INICIADO (Registro y Tránsito)
* **Datos Iniciales:** Cliente_ID, Camion_ID, Chofer_ID (auto-asignado por camión), Producto, Documento_Tipo (CTG, CP, Remito), Documento_Número, Pagador_Flete_ID, Origen, Destino, Fecha_Carga, Tarifa_Por_TN, Peso_Estimado_TN.
* **Comisión Dador de Carga:** Tipo_Comision (Porcentaje o Monto Fijo), Valor_Comision, Comisionista_ID.
* **Eventos en Tránsito:**
    * `Cargar_Gasto()`: Registra gastos del viaje usando categorías precargadas en la DB.
    * `Cargar_Adelanto()`: Dinero entregado al chofer para gastos. (Si sobra, el excedente se computará como un adelanto de su ganancia).
* **Evento de Cierre de Tránsito (Descarga):**
    * Se ingresa: `Peso_Neto_TN` (o en su defecto `Peso_Bruto` - `Tara`).
    * **Cálculos Automáticos:**
        * Total Facturar Estimado = Peso Neto Descargado * Tarifa Por TN
        * Diferencial TN = Peso Neto Descargado - Peso Estimado
    * **Impacto Contable Inmediato:** Se genera un movimiento en la Cuenta Corriente del Chofer $\rightarrow$ **Haber** (Ingreso) = Total Facturar Estimado * (%_Ganancia_Chofer / 100).
* **Vista Resumen:** Debe mostrar Origen, Destino, Cliente, Producto, Documento, Tarifa, Total Estimado (luego Total a Facturar) y el Diferencial de TN.

### ESTADO 2: LIQUIDACIÓN (Ajustes y Facturación)
* **Acciones Permitidas:**
    * Modificar datos del Estado 1 (Auditable).
    * Cargar nuevos gastos o adelantos tardíos asociados al viaje.
* **Impacto Contable Excedente:** Si el adelanto inicial superó los gastos reales del viaje, el sobrante se impacta en la Cuenta Corriente del Chofer $\rightarrow$ **Debe** (Débito/Dinero que ya se le dio).
* **Proceso de Facturación (2 Opciones):**
    * **Opción A (Factura Individual):** Se levanta el viaje. Se calcula el monto final ($TN\_Descargadas \times Tarifa$). Se registra: Fecha_Factura, Número_Factura. El viaje cambia a Estado: **Liquidación Cerrada**.
    * **Opción B (Factura Agrupada/Multi-viaje):** Permite seleccionar *varios* viajes en Estado 1, **siempre y cuando compartan el mismo `Pagador_Flete_ID`**. Se genera una única Factura asociada a múltiples IDs de viajes.

### ESTADO 3: INSTANCIA DE COBRO (Finanzas)
* **Acción:** Se levanta la Factura (sea individual o multi-viaje).
* **Registros de Entrada:**
    * Descuentos / Retenciones (Retenciones impositivas, IIBB, u otros conceptos).
    * Fecha de Pago.
    * Medio de Cobro: Efectivo, Transferencia, Cheque / E-Cheq (Si es cheque, requerir obligatoriamente: Número, Banco, Fecha_Vencimiento, Emisor).
* **Impacto Contable Final:** Se registra el ingreso neto de dinero en la Cuenta Financiera de la Empresa (Caja de Ahorro, Banco, Billetera Virtual seleccionada). El viaje/factura pasa a Estado: **Cobrado**.

---

## 5. DASHBOARD PRINCIPAL (PÁGINA DE INICIO)
Debe consolidar la información del Tenant de forma limpia y escaneable:
* **Métricas Rápidas:** Resumen de viajes realizados en el mes/semana.
* **Cuentas por Cobrar:** Listado o monto total de fletes pendientes de cobro (Estado 2).
* **Agenda de Pagos:** Panel cronológico que indique qué días ingresan los pagos estimados/pactados.