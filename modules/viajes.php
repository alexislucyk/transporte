<?php
/**
 * Modulo de Gestion de Viajes - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Core del sistema: registro y seguimiento de viajes.
 * Spec: base.md seccion 4, correcciones.md
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';

// ─── HELPERS ──────────────────────────────────────────────
function viajeOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

function generarCodigoSD(PDO $pdo, int $transportista_id): string {
    $stmt = $pdo->prepare("SELECT ctg_nro FROM viajes WHERE transportista_id = ? AND ctg_nro LIKE 'SD-%' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$transportista_id]);
    $last = $stmt->fetchColumn();
    $num = 1;
    if ($last && preg_match('/SD-(\d+)/', $last, $m)) {
        $num = (int)$m[1] + 1;
    }
    return 'SD-' . str_pad($num, 5, '0', STR_PAD_LEFT);
}

// ─── DATOS INICIALES (Selects del formulario) ──────────
$clientes = [];
$stmt = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comercial = 1 AND activo = 1 ORDER BY razon_social ASC");
$stmt->execute([$active_company_id]);
$clientes = $stmt->fetchAll();

$choferes = [];
$stmt = $pdo->prepare("SELECT id, CONCAT(apellido, ', ', nombre) as nombre, porcentaje_ganancia FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido, nombre ASC");
$stmt->execute([$active_company_id]);
$choferes = $stmt->fetchAll();

$vehiculos = [];
$stmt = $pdo->prepare("SELECT id, dominio, marca, modelo, chofer_id, acoplado FROM vehiculos WHERE transportista_id = ? AND activo = 1 ORDER BY dominio ASC");
$stmt->execute([$active_company_id]);
$vehiculos = $stmt->fetchAll();

$comisionistas = [];
$stmt = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1 AND activo = 1 ORDER BY razon_social ASC");
$stmt->execute([$active_company_id]);
$comisionistas = $stmt->fetchAll();

$pagadores = [];
$stmt = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1 AND activo = 1 ORDER BY razon_social ASC");
$stmt->execute([$active_company_id]);
$pagadores = $stmt->fetchAll();

// ─── PROCESAR ACCIONES (POST) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Capturar campos comunes a todas las acciones
    $cliente_id     = (int)($_POST['cliente_id'] ?? 0);
    $chofer_id      = (int)($_POST['chofer_id'] ?? 0);
    $vehiculo_id    = (int)($_POST['vehiculo_id'] ?? 0);
    $acoplado       = trim($_POST['acoplado'] ?? '');
    $origen         = trim($_POST['origen'] ?? '');
    $destino        = trim($_POST['destino'] ?? '');
    $producto       = trim($_POST['producto'] ?? '');
    $fecha_carga    = $_POST['fecha_carga'] ?? date('Y-m-d');
    $tarifa_tonelada = (float)($_POST['tarifa_tonelada'] ?? 0);
    $peso_bruto_est = (float)($_POST['peso_bruto'] ?? 0); // TN Estimadas (se guarda en peso_bruto inicial)
    $peso_tara      = (float)($_POST['peso_tara'] ?? 0);
    $chofer_porcentaje = (float)($_POST['chofer_porcentaje'] ?? 0);
    $comision_tipo  = $_POST['comision_tipo'] ?? 'ninguna';
    $comision_valor = (float)($_POST['comision_valor'] ?? 0);
    $comisionista_id = (int)($_POST['comisionista_id'] ?? 0);
    $ctg_nro        = trim($_POST['ctg_nro'] ?? '');
    $carta_porte_nro = trim($_POST['carta_porte_nro'] ?? '');
    $otros_docs     = trim($_POST['otros_docs'] ?? '');
    $pagador_id     = (int)($_POST['pagador_id'] ?? 0);
    $observaciones  = trim($_POST['observaciones'] ?? '');

    // ─── ACCION: NUEVO VIAJE ─────────────────────────────
    if ($_POST['action'] === 'nuevo') {
        // Validaciones obligatorias
        $campos_faltan = [];
        if (empty($cliente_id))    $campos_faltan[] = 'Cliente';
        if (empty($vehiculo_id))   $campos_faltan[] = 'Vehículo';
        if (empty($origen))        $campos_faltan[] = 'Origen';
        if (empty($destino))       $campos_faltan[] = 'Destino';
        if (empty($producto))      $campos_faltan[] = 'Producto';
        if (empty($fecha_carga))   $campos_faltan[] = 'Fecha de Carga';
        if ($peso_bruto_est <= 0)  $campos_faltan[] = 'TN Estimadas';
        if ($tarifa_tonelada <= 0) $campos_faltan[] = 'Tarifa por TN';
        if (empty($pagador_id))    $campos_faltan[] = 'Pagador de Flete';

        if (!empty($campos_faltan)) {
            $error = "Campos obligatorios: " . implode(', ', $campos_faltan) . ".";
        } else {
            // Si no se ingresó documentación, asignar SD-#####
            if (empty($ctg_nro) && empty($carta_porte_nro) && empty($otros_docs)) {
                $ctg_nro = generarCodigoSD($pdo, $active_company_id);
            }

            // Cálculos financieros iniciales
            $total_flete_bruto = $peso_bruto_est * $tarifa_tonelada;
            $total_flete_neto  = $total_flete_bruto; // Sin tara aún (es TN estimadas)

            try {
                $sql = "INSERT INTO viajes 
                    (transportista_id, cliente_id, chofer_id, vehiculo_id, acoplado,
                     origen, destino, producto, fecha_carga,
                     peso_bruto, peso_tara, tarifa_tonelada,
                     total_flete_bruto, total_flete_neto, chofer_porcentaje,
                     comision_tipo, comision_valor, comisionista_id,
                     ctg_nro, carta_porte_nro, otros_docs,
                     pagador_id, observaciones, activo)
                    VALUES (?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, 1)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $active_company_id,
                    $cliente_id,
                    $chofer_id > 0 ? $chofer_id : null,
                    $vehiculo_id,
                    $acoplado ?: null,
                    $origen,
                    $destino,
                    $producto ?: null,
                    $fecha_carga,
                    $peso_bruto_est,      // peso_bruto
                    $peso_tara,           // peso_tara (0 al inicio)
                    $tarifa_tonelada,
                    $total_flete_bruto,
                    $total_flete_neto,
                    $chofer_porcentaje,
                    $comision_tipo,
                    $comision_valor,
                    ($comisionista_id > 0 && $comision_tipo !== 'ninguna') ? $comisionista_id : null,
                    $ctg_nro ?: null,
                    $carta_porte_nro ?: null,
                    $otros_docs ?: null,
                    $pagador_id > 0 ? $pagador_id : null,
                    $observaciones ?: null
                ]);

                $mensaje = "Viaje registrado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al registrar viaje: " . $e->getMessage();
            }
        }
    }

    // ─── ACCION: EDITAR VIAJE ──────────────────────────
    if ($_POST['action'] === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !viajeOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el viaje no existe o pertenece a otro tenant.";
        } else {
            $campos_faltan = [];
            if (empty($cliente_id))    $campos_faltan[] = 'Cliente';
            if (empty($vehiculo_id))   $campos_faltan[] = 'Vehículo';
            if (empty($origen))        $campos_faltan[] = 'Origen';
            if (empty($destino))       $campos_faltan[] = 'Destino';
            if (empty($producto))      $campos_faltan[] = 'Producto';
            if (empty($fecha_carga))   $campos_faltan[] = 'Fecha de Carga';
            if ($peso_bruto_est <= 0)  $campos_faltan[] = 'TN Estimadas';
            if ($tarifa_tonelada <= 0) $campos_faltan[] = 'Tarifa por TN';
            if (empty($pagador_id))    $campos_faltan[] = 'Pagador de Flete';

            if (!empty($campos_faltan)) {
                $error = "Campos obligatorios: " . implode(', ', $campos_faltan) . ".";
            } else {
                // Recalcular totales (puede haber cambiado tarifa o peso estimado)
                $total_flete_bruto = $peso_bruto_est * $tarifa_tonelada;
                $total_flete_neto  = ($peso_bruto_est - $peso_tara) * $tarifa_tonelada;

                try {
                    $sql = "UPDATE viajes SET 
                        cliente_id = ?, chofer_id = ?, vehiculo_id = ?, acoplado = ?,
                        origen = ?, destino = ?, producto = ?, fecha_carga = ?,
                        peso_bruto = ?, peso_tara = ?, tarifa_tonelada = ?,
                        total_flete_bruto = ?, total_flete_neto = ?,
                        comision_tipo = ?, comision_valor = ?, comisionista_id = ?,
                        ctg_nro = ?, carta_porte_nro = ?, otros_docs = ?,
                        pagador_id = ?, observaciones = ?
                        WHERE id = ? AND transportista_id = ? AND activo = 1";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $cliente_id,
                        $chofer_id > 0 ? $chofer_id : null,
                        $vehiculo_id,
                        $acoplado ?: null,
                        $origen,
                        $destino,
                        $producto ?: null,
                        $fecha_carga,
                        $peso_bruto_est,
                        $peso_tara,
                        $tarifa_tonelada,
                        $total_flete_bruto,
                        $total_flete_neto,
                        $comision_tipo,
                        $comision_valor,
                        ($comisionista_id > 0 && $comision_tipo !== 'ninguna') ? $comisionista_id : null,
                        $ctg_nro ?: null,
                        $carta_porte_nro ?: null,
                        $otros_docs ?: null,
                        $pagador_id > 0 ? $pagador_id : null,
                        $observaciones ?: null,
                        $id,
                        $active_company_id
                    ]);

                    $mensaje = "Viaje actualizado exitosamente.";
                } catch (PDOException $e) {
                    $error = "Error al actualizar viaje: " . $e->getMessage();
                }
            }
        }
    }

    // ─── ACCION: BORRAR VIAJE (lógico) ──────────────────
    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !viajeOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el viaje no existe o pertenece a otro tenant.";
        } else {
            try {
                $pdo->prepare("UPDATE viajes SET activo = 0 WHERE id = ?")->execute([$id]);
                $mensaje = "Viaje eliminado (borrado lógico).";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }

    // ─── ACCION: ACTUALIZAR DESCARGA (peso real) ─────────
    if ($_POST['action'] === 'descargar') {
        $id = (int)($_POST['id'] ?? 0);
        $peso_bruto_real = (float)($_POST['peso_bruto_real'] ?? 0);
        $peso_tara_real  = (float)($_POST['peso_tara_real'] ?? 0);

        if ($id <= 0 || !viajeOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el viaje no existe o pertenece a otro tenant.";
        } elseif ($peso_bruto_real <= 0) {
            $error = "El Peso Bruto real debe ser mayor a 0.";
        } elseif ($peso_tara_real < 0) {
            $error = "La Tara no puede ser negativa.";
        } else {
            $peso_neto_real = max(0, $peso_bruto_real - $peso_tara_real);
            // Obtener la tarifa actual del viaje
            $stmt = $pdo->prepare("SELECT tarifa_tonelada, chofer_porcentaje FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
            $stmt->execute([$id, $active_company_id]);
            $viaje_data = $stmt->fetch();
            
            if (!$viaje_data) {
                $error = "Viaje no encontrado.";
            } else {
                $tarifa = (float)$viaje_data['tarifa_tonelada'];
                $chofer_pct = (float)$viaje_data['chofer_porcentaje'];
                $total_flete_bruto = $peso_bruto_real * $tarifa;
                $total_flete_neto  = $peso_neto_real * $tarifa;

                try {
                    $pdo->prepare("UPDATE viajes SET 
                        peso_bruto = ?, peso_tara = ?,
                        total_flete_bruto = ?, total_flete_neto = ?,
                        estado = 'descargado'
                        WHERE id = ? AND transportista_id = ? AND activo = 1")
                        ->execute([$peso_bruto_real, $peso_tara_real, $total_flete_bruto, $total_flete_neto, $id, $active_company_id]);

                    // Impacto contable: registrar en cuenta corriente del chofer
                    if (!empty($viaje_data['chofer_porcentaje']) && $viaje_data['chofer_porcentaje'] > 0) {
                        $stmt_ch = $pdo->prepare("SELECT chofer_id FROM viajes WHERE id = ?");
                        $stmt_ch->execute([$id]);
                        $choferId = $stmt_ch->fetchColumn();
                        if ($choferId) {
                            $ganancia_chofer = $total_flete_neto * ($chofer_pct / 100);
                            $stmt_pago = $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, 'liquidacion', ?)");
                            $stmt_pago->execute([$choferId, date('Y-m-d'), $ganancia_chofer, "Liquidación automática viaje #{$id}"]);
                        }
                    }

                    $mensaje = "Descarga registrada exitosamente. Peso Neto: " . number_format($peso_neto_real, 2, ',', '.') . " TN.";
                } catch (PDOException $e) {
                    $error = "Error al registrar descarga: " . $e->getMessage();
                }
            }
        }
    }
}

// ─── LISTADO DE VIAJES ────────────────────────────────
$filtro_estado = $_GET['estado'] ?? 'todos';
$where_estado = "";
$params = [$active_company_id];
switch ($filtro_estado) {
    case 'en_viaje':    $where_estado = "AND v.estado = 'en_viaje'"; break;
    case 'descargado':  $where_estado = "AND v.estado = 'descargado'"; break;
    case 'facturado':   $where_estado = "AND v.estado = 'facturado'"; break;
    case 'cobrado':     $where_estado = "AND v.estado = 'cobrado'"; break;
    case 'liquidado':   $where_estado = "AND v.estado = 'liquidado'"; break;
}

$sql = "SELECT v.*,
               c.razon_social as cliente_nombre,
               CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
               ve.dominio as vehiculo_dominio
        FROM viajes v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN choferes ch ON ch.id = v.chofer_id
        LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
        WHERE v.transportista_id = ? AND v.activo = 1 $where_estado
        ORDER BY v.fecha_carga DESC, v.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll();
?>
<!-- Vista del listado -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Operativa de Viajes</h1>
        <p>Registra y gestiona los viajes de la empresa activa.</p>
    </div>
    <button onclick="prepararNuevoViaje()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Viaje
    </button>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px; padding: 12px 16px;">
    <form method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <input type="hidden" name="route" value="viajes">
        <label style="font-weight: bold; opacity: 0.8;">Filtrar por estado:</label>
        <select name="estado" class="input-field" style="max-width: 220px;" onchange="this.form.submit()">
            <option value="todos"        <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="en_viaje"     <?= $filtro_estado === 'en_viaje' ? 'selected' : '' ?>>En Viaje</option>
            <option value="descargado"   <?= $filtro_estado === 'descargado' ? 'selected' : '' ?>>Descargado</option>
            <option value="facturado"    <?= $filtro_estado === 'facturado' ? 'selected' : '' ?>>Facturado</option>
            <option value="cobrado"      <?= $filtro_estado === 'cobrado' ? 'selected' : '' ?>>Cobrado</option>
            <option value="liquidado"    <?= $filtro_estado === 'liquidado' ? 'selected' : '' ?>>Liquidado</option>
        </select>
        <span style="opacity: 0.6; font-size: 0.9rem;"><?= count($viajes) ?> resultado(s)</span>
    </form>
</div>

<div class="card">
    <?php if (empty($viajes)): ?>
        <p style="text-align:center; padding: 40px; opacity:0.5;">No hay viajes registrados.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>CTG/CP</th>
                    <th>Cliente</th>
                    <th>Origen → Destino</th>
                    <th>Vehículo</th>
                    <th>Total Neto</th>
                    <th>Estado</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($viajes as $v):
                    $estado_badge = match($v['estado']) {
                        'en_viaje'   => '<span class="badge" style="background:#f39c12; color:#fff;">En Viaje</span>',
                        'descargado' => '<span class="badge" style="background:#3498db; color:#fff;">Descargado</span>',
                        'facturado'  => '<span class="badge" style="background:#9b59b6; color:#fff;">Facturado</span>',
                        'cobrado'    => '<span class="badge" style="background:#27ae60; color:#fff;">Cobrado</span>',
                        'liquidado'  => '<span class="badge" style="background:#95a5a6; color:#fff;">Liquidado</span>',
                        default      => htmlspecialchars($v['estado'])
                    };

                    // Determinar qué documentos tiene
                    $docs_parts = [];
                    if (!empty($v['ctg_nro']))          $docs_parts[] = 'CTG: ' . $v['ctg_nro'];
                    if (!empty($v['carta_porte_nro']))  $docs_parts[] = 'CP: ' . $v['carta_porte_nro'];
                    if (!empty($v['otros_docs']))       $docs_parts[] = $v['otros_docs'];
                    $docs_label = !empty($docs_parts) ? implode(' | ', $docs_parts) : '-';
                ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($v['fecha_carga'])) ?></td>
                    <td style="font-size:0.85rem;"><?= htmlspecialchars($docs_label) ?></td>
                    <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                    <td title="<?= htmlspecialchars($v['origen']) ?> → <?= htmlspecialchars($v['destino']) ?>">
                        <?= htmlspecialchars(mb_substr($v['origen'], 0, 20)) ?>… 
                        <i class="fas fa-arrow-right" style="opacity:0.4;font-size:0.8rem;"></i> 
                        <?= htmlspecialchars(mb_substr($v['destino'], 0, 20)) ?>…
                    </td>
                    <td><?= htmlspecialchars($v['vehiculo_dominio'] ?? '-') ?></td>
                    <td style="font-weight:bold;">$ <?= number_format($v['total_flete_neto'], 2, ',', '.') ?></td>
                    <td><?= $estado_badge ?></td>
                    <td style="text-align:center; white-space:nowrap;">
                        <!-- Botón Descargar (solo en_viaje) -->
                        <?php if ($v['estado'] === 'en_viaje'): ?>
                        <button onclick="prepararDescarga(<?= (int)$v['id'] ?>)" title="Registrar Descarga" style="background:none; border:none; color:#27ae60; cursor:pointer; margin-right:6px;">
                            <i class="fas fa-weight-hanging"></i>
                        </button>
                        <?php endif; ?>

                        <a href="viajes_detalle?viaje_id=<?= (int)$v['id'] ?>" title="Ver Detalle" style="background:none; border:none; color:var(--accent); cursor:pointer; margin-right:6px;">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button onclick='editViaje(<?= json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer; margin-right:6px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmarBorrarViaje(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($v['ctg_nro'] ?: 'Viaje #' . $v['id'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: NUEVO / EDITAR VIAJE
     ══════════════════════════════════════════════════════════ -->
<div id="modal-viaje" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="margin:0" id="viaje-modal-title">Registrar Viaje</h3>
            <span class="close-modal" onclick="closeModal('modal-viaje')">&times;</span>
        </div>
        <form method="POST" id="form-viaje">
            <div class="modal-body">
                <input type="hidden" name="action" id="viaje-action" value="nuevo">
                <input type="hidden" name="id" id="viaje-id">

                <!-- Fila 1: Cliente + Producto -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Cliente *</label>
                        <select name="cliente_id" id="viaje-cliente" class="input-field" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($clientes as $cl): ?>
                                <option value="<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Producto *</label>
                        <input type="text" name="producto" id="viaje-producto" class="input-field" required placeholder="Ej: Soja, Maíz, Trigo...">
                    </div>
                </div>

                <!-- Fila 2: Origen + Destino -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Origen *</label>
                        <input type="text" name="origen" id="viaje-origen" class="input-field" required placeholder="Ciudad / Provincia">
                    </div>
                    <div class="form-group">
                        <label>Destino *</label>
                        <input type="text" name="destino" id="viaje-destino" class="input-field" required placeholder="Ciudad / Provincia">
                    </div>
                </div>

                <!-- Fila 3: Vehículo (auto-selects chofer y acoplado) + Acoplado -->
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Vehículo *</label>
                        <select name="vehiculo_id" id="viaje-vehiculo" class="input-field" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($vehiculos as $v): ?>
                                <option value="<?= (int)$v['id'] ?>"
                                    data-chofer="<?= (int)($v['chofer_id'] ?? 0) ?>"
                                    data-acoplado="<?= htmlspecialchars($v['acoplado'] ?? '') ?>">
                                    <?= htmlspecialchars($v['dominio'] . ' (' . $v['marca'] . ' ' . $v['modelo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Acoplado</label>
                        <input type="text" name="acoplado" id="viaje-acoplado" class="input-field" placeholder="Patente / Semi">
                    </div>
                    <div class="form-group">
                        <label>Chofer</label>
                        <select name="chofer_id" id="viaje-chofer" class="input-field">
                            <option value="0">-- Sin asignar --</option>
                            <?php foreach($choferes as $ch): ?>
                                <option value="<?= (int)$ch['id'] ?>" data-porcentaje="<?= (float)$ch['porcentaje_ganancia'] ?>">
                                    <?= htmlspecialchars($ch['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="chofer_porcentaje" id="viaje-chofer-porcentaje" value="0">
                    </div>
                </div>

                <!-- Fila 4: Documentación -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>CTG N°</label>
                        <input type="text" name="ctg_nro" id="viaje-ctg" class="input-field" placeholder="CTG-#####">
                    </div>
                    <div class="form-group">
                        <label>Carta Porte N°</label>
                        <input type="text" name="carta_porte_nro" id="viaje-carta-porte" class="input-field" placeholder="CP-#####">
                    </div>
                    <div class="form-group">
                        <label>Otros Documentos</label>
                        <input type="text" name="otros_docs" id="viaje-otros-docs" class="input-field" placeholder="Remito, etc.">
                    </div>
                </div>

                <!-- Fila 5: Fecha + TN Estimadas + Tarifa -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Fecha de Carga *</label>
                        <input type="date" name="fecha_carga" id="viaje-fecha-carga" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label>TN Estimadas *</label>
                        <input type="number" step="0.01" min="0.01" name="peso_bruto" id="viaje-peso-bruto" class="input-field" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Tarifa por TN ($) *</label>
                        <input type="number" step="0.01" min="0.01" name="tarifa_tonelada" id="viaje-tarifa" class="input-field" required placeholder="0.00">
                    </div>
                </div>

                <!-- Fila 6: Tipo Comisión + Valor + Comisionista -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label>Tipo Comisión</label>
                        <select name="comision_tipo" id="viaje-comision-tipo" class="input-field">
                            <option value="ninguna">Ninguna</option>
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="monto_fijo">Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor Comisión</label>
                        <input type="number" step="0.01" min="0" name="comision_valor" id="viaje-comision-valor" class="input-field" value="0">
                    </div>
                    <div class="form-group">
                        <label>Comisionista</label>
                        <select name="comisionista_id" id="viaje-comisionista" class="input-field">
                            <option value="0">-- Seleccionar --</option>
                            <?php foreach($comisionistas as $cm): ?>
                                <option value="<?= (int)$cm['id'] ?>"><?= htmlspecialchars($cm['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 7: Pagador de Flete -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Pagador de Flete *</label>
                        <select name="pagador_id" id="viaje-pagador" class="input-field" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($pagadores as $pg): ?>
                                <option value="<?= (int)$pg['id'] ?>"><?= htmlspecialchars($pg['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Observaciones (opcional) -->
                <div style="margin-top: 12px;">
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" id="viaje-observaciones" class="input-field" style="resize:vertical; min-height:50px;" placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>

            </div><!-- /modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-viaje')">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Guardar Viaje</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DESCARGA (peso real)
     ══════════════════════════════════════════════════════════ -->
<div id="modal-descarga" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 style="margin:0">Registrar Descarga</h3>
            <span class="close-modal" onclick="closeModal('modal-descarga')">&times;</span>
        </div>
        <form method="POST" id="form-descarga">
            <div class="modal-body">
                <input type="hidden" name="action" value="descargar">
                <input type="hidden" name="id" id="descarga-viaje-id">
                <div class="form-group">
                    <label>Peso Bruto Real (TN) *</label>
                    <input type="number" step="0.01" min="0.01" name="peso_bruto_real" id="descarga-peso-bruto" class="input-field" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Tara (TN)</label>
                    <input type="number" step="0.01" min="0" name="peso_tara_real" id="descarga-peso-tara" class="input-field" value="0" placeholder="0.00">
                </div>
                <div style="background:#f8f9fa; padding:12px; border-radius:8px; margin-top:8px;">
                    <p style="margin:4px 0;"><strong>Peso Neto estimado:</strong> <span id="descarga-peso-neto-preview">0,00</span> TN</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-descarga')">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#27ae60;">
                    <i class="fas fa-check"></i> Confirmar Descarga
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario oculto para borrar -->
<form id="form-borrar-viaje" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-viaje-id">
</form>

<script>
// ─── MODAL NUEVO VIAJE ──────────────────────────────────
function prepararNuevoViaje() {
    document.getElementById('viaje-modal-title').innerText = "Registrar Nuevo Viaje";
    document.getElementById('viaje-action').value = "nuevo";
    document.getElementById('viaje-id').value = "";
    document.querySelector('#form-viaje').reset();
    document.getElementById('viaje-fecha-carga').value = new Date().toISOString().split('T')[0];
    document.getElementById('viaje-comision-tipo').value = 'ninguna';
    document.getElementById('viaje-comision-valor').value = 0;
    document.getElementById('viaje-comisionista').value = 0;
    document.getElementById('viaje-pagador').value = '';
    document.getElementById('viaje-chofer-porcentaje').value = 0;
    openModal('modal-viaje');
}

// ─── EDITAR VIAJE ───────────────────────────────────────
function editViaje(data) {
    document.getElementById('viaje-modal-title').innerText = "Editar Viaje #" + data.id;
    document.getElementById('viaje-action').value = "editar";
    document.getElementById('viaje-id').value = data.id;
    document.getElementById('viaje-cliente').value = data.cliente_id || '';
    document.getElementById('viaje-chofer').value = data.chofer_id || 0;
    document.getElementById('viaje-vehiculo').value = data.vehiculo_id || '';
    document.getElementById('viaje-acoplado').value = data.acoplado || '';
    document.getElementById('viaje-origen').value = data.origen || '';
    document.getElementById('viaje-destino').value = data.destino || '';
    document.getElementById('viaje-producto').value = data.producto || '';
    document.getElementById('viaje-fecha-carga').value = data.fecha_carga || '';
    document.getElementById('viaje-peso-bruto').value = data.peso_bruto || 0;
    document.getElementById('viaje-tarifa').value = data.tarifa_tonelada || 0;
    document.getElementById('viaje-ctg').value = data.ctg_nro || '';
    document.getElementById('viaje-carta-porte').value = data.carta_porte_nro || '';
    document.getElementById('viaje-otros-docs').value = data.otros_docs || '';
    document.getElementById('viaje-comision-tipo').value = data.comision_tipo || 'ninguna';
    document.getElementById('viaje-comision-valor').value = data.comision_valor || 0;
    document.getElementById('viaje-comisionista').value = data.comisionista_id || 0;
    document.getElementById('viaje-pagador').value = data.pagador_id || '';
    document.getElementById('viaje-observaciones').value = data.observaciones || '';

    // Recuperar porcentaje del chofer desde los options
    var choferSelect = document.getElementById('viaje-chofer');
    var pct = 0;
    for (var i = 0; i < choferSelect.options.length; i++) {
        if (choferSelect.options[i].value == data.chofer_id) {
            pct = choferSelect.options[i].getAttribute('data-porcentaje') || 0;
            break;
        }
    }
    document.getElementById('viaje-chofer-porcentaje').value = pct;
    openModal('modal-viaje');
}

// ─── MODAL DESCARGA ─────────────────────────────────────
function prepararDescarga(viajeId) {
    document.getElementById('descarga-viaje-id').value = viajeId;
    document.getElementById('descarga-peso-bruto').value = '';
    document.getElementById('descarga-peso-tara').value = 0;
    document.getElementById('descarga-peso-neto-preview').innerText = '0,00';
    openModal('modal-descarga');
}

// ─── BORRAR VIAJE ───────────────────────────────────────
function confirmarBorrarViaje(id, nombre) {
    appConfirm('¿Seguro que deseas eliminar el viaje "' + nombre + '"? (borrado lógico)', function() {
        document.getElementById('borrar-viaje-id').value = id;
        document.getElementById('form-borrar-viaje').submit();
    }, "Eliminar Viaje");
}

// ─── INICIALIZACIÓN ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Al seleccionar vehículo → auto-asignar chofer y acoplado
    var vehiculoSelect = document.getElementById('viaje-vehiculo');
    if (vehiculoSelect) {
        vehiculoSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var choferId = selectedOption.getAttribute('data-chofer');
            var acoplado = selectedOption.getAttribute('data-acoplado');
            document.getElementById('viaje-chofer').value = choferId || '0';
            document.getElementById('viaje-acoplado').value = acoplado || '';

            // Auto-completar porcentaje del chofer seleccionado
            actualizarPorcentajeChofer();
        });
    }

    // Al cambiar el chofer manualmente → actualizar porcentaje
    var choferSelect = document.getElementById('viaje-chofer');
    if (choferSelect) {
        choferSelect.addEventListener('change', function() {
            actualizarPorcentajeChofer();
        });
    }

    function actualizarPorcentajeChofer() {
        var select = document.getElementById('viaje-chofer');
        var selectedOption = select.options[select.selectedIndex];
        var pct = selectedOption ? (parseFloat(selectedOption.getAttribute('data-porcentaje')) || 0) : 0;
        document.getElementById('viaje-chofer-porcentaje').value = pct;
    }

    // Preview de peso neto en modal descarga
    var pesoBrutoInput = document.getElementById('descarga-peso-bruto');
    var pesoTaraInput = document.getElementById('descarga-peso-tara');
    function actualizarPreviewNeto() {
        var bruto = parseFloat(pesoBrutoInput.value) || 0;
        var tara = parseFloat(pesoTaraInput.value) || 0;
        var neto = Math.max(0, bruto - tara);
        document.getElementById('descarga-peso-neto-preview').innerText = neto.toFixed(2).replace('.', ',');
    }
    if (pesoBrutoInput) pesoBrutoInput.addEventListener('input', actualizarPreviewNeto);
    if (pesoTaraInput) pesoTaraInput.addEventListener('input', actualizarPreviewNeto);
});
</script>