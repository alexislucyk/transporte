<?php
/**
 * Módulo de Operativa de Viajes - Trans Cargo Hub
 */
$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- LÓGICA DE DETALLE DE VIAJE ---
if ($action === 'detalle' && isset($params[0])) {
    include_once 'modules/viajes_detalle.php';
    return;
}

// --- PROCESAR ALTA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo') {
    try {
        $stmt_ch = $pdo->prepare("SELECT porcentaje_ganancia FROM choferes WHERE id = ?");
        $stmt_ch->execute([$_POST['chofer_id']]);
        $porcentaje_actual = $stmt_ch->fetchColumn() ?: 0;

        $total_flete = $_POST['peso_estimado'] * $_POST['tarifa_tonelada'];
        $sql = "INSERT INTO viajes (transportista_id, cliente_id, chofer_id, vehiculo_id, acoplado, origen, destino, producto, fecha_carga, tarifa_tonelada, total_flete_bruto, chofer_porcentaje, comision_tipo, comision_valor, comisionista_id, pagador_id, ctg_nro, carta_porte_nro, otros_docs, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_viaje')";
        $pdo->prepare($sql)->execute([
            $active_company_id, $_POST['cliente_id'], $_POST['chofer_id'], $_POST['vehiculo_id'], 
            $_POST['acoplado'], $_POST['origen'], $_POST['destino'], $_POST['producto'], 
            $_POST['fecha_carga'], $_POST['tarifa_tonelada'], $total_flete, $porcentaje_actual,
            $_POST['comision_tipo'], $_POST['comision_valor'], $_POST['comisionista_id'] ?: null, $_POST['pagador_id'] ?: null,
            $_POST['ctg_nro'], $_POST['carta_porte_nro'], $_POST['otros_docs']
        ]);
        $mensaje = "Viaje iniciado correctamente.";
    } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

// --- PROCESAR ELIMINACIÓN LÓGICA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_viaje' && isset($_POST['viaje_id'])) {
    try {
        $pdo->beginTransaction();
        
        $viajeId = (int)$_POST['viaje_id'];
        
        // 1. Marcar gastos como inactivos
        $pdo->prepare("UPDATE viajes_gastos SET activo = 0 WHERE viaje_id = ?")->execute([$viajeId]);
        
        // 2. Marcar adelantos como inactivos
        $pdo->prepare("UPDATE viajes_adelantos SET activo = 0 WHERE viaje_id = ?")->execute([$viajeId]);
        
        // 3. Eliminar vínculos con facturas (pero no borrar la factura en sí)
        $pdo->prepare("DELETE FROM facturas_fletes_viajes WHERE viaje_id = ?")->execute([$viajeId]);
        
        // 4. Marcar el viaje como inactivo
        $pdo->prepare("UPDATE viajes SET activo = 0 WHERE id = ? AND transportista_id = ?")->execute([$viajeId, $active_company_id]);
        
        $pdo->commit();
        $mensaje = "Viaje eliminado correctamente. Se ocultaron gastos, adelantos y se desvincularon facturas relacionadas.";
    } catch (PDOException $e) { 
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage(); 
    }
}

// --- CARGAR SELECTORES ---
$stmt_cli = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comercial = 1"); $stmt_cli->execute([$active_company_id]);
$lista_comerciales = $stmt_cli->fetchAll();

$stmt_pag = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1"); $stmt_pag->execute([$active_company_id]);
$lista_pagadores = $stmt_pag->fetchAll();
$stmt_com = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1"); $stmt_com->execute([$active_company_id]);
$lista_comisionistas = $stmt_com->fetchAll();

$stmt_cho = $pdo->prepare("SELECT id, nombre, apellido FROM choferes WHERE transportista_id = ? AND activo = 1"); $stmt_cho->execute([$active_company_id]);
$lista_choferes = $stmt_cho->fetchAll();

$stmt_cam = $pdo->prepare("SELECT id, dominio, chofer_id, acoplado FROM vehiculos WHERE transportista_id = ?"); $stmt_cam->execute([$active_company_id]);
$lista_camiones = $stmt_cam->fetchAll();

// --- LISTADO (solo activos) ---
$viajes = $pdo->prepare("SELECT v.*, c.razon_social as cliente, CONCAT(ch.apellido, ', ', ch.nombre) as chofer, ve.dominio as patente 
                         FROM viajes v 
                         JOIN clientes c ON v.cliente_id = c.id 
                         JOIN choferes ch ON v.chofer_id = ch.id 
                         JOIN vehiculos ve ON v.vehiculo_id = ve.id 
                         WHERE v.transportista_id = ? AND (v.activo IS NULL OR v.activo = 1) 
                         ORDER BY v.fecha_carga DESC");
$viajes->execute([$active_company_id]);
$lista_viajes = $viajes->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>Operativa de Viajes</h1>
    <button onclick="openModal('modal-viaje')" class="btn-primary"><i class="fas fa-route"></i> Nuevo Viaje</button>
</div>

<?php if ($mensaje): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $mensaje ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>CTG / CP / Remito</th>
                <th>Patente</th>
                <th>Ruta</th>
                <th>Flete Est.</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lista_viajes as $v): ?>
            <tr>
                <td><?= formatDate($v['fecha_carga']) ?></td>
                <td><?= htmlspecialchars($v['cliente']) ?></td>
                <td>
                    <?php
                    $doc = '';
                    if (!empty($v['ctg_nro'])) {
                        $doc = 'CTG: ' . $v['ctg_nro'];
                    } elseif (!empty($v['carta_porte_nro'])) {
                        $doc = 'CP: ' . $v['carta_porte_nro'];
                    } elseif (!empty($v['otros_docs'])) {
                        $doc = 'Remito: ' . $v['otros_docs'];
                    } else {
                        $doc = '-';
                    }
                    ?>
                    <?= htmlspecialchars($doc) ?>
                </td>
                <td style="font-weight:bold"><?= $v['patente'] ?></td>
                <td><?= htmlspecialchars($v['origen'] . " -> " . $v['destino']) ?></td>
                <td><?= formatMoney($v['total_flete_bruto']) ?></td>
                <td>
                    <?php
                    $badgeClass = 'badge-info';
                    if ($v['estado'] == 'descargado') $badgeClass = 'badge-success';
                    if ($v['estado'] == 'facturado') $badgeClass = 'badge-warning';
                    if ($v['estado'] == 'cobrado') $badgeClass = 'badge-primary';
                    if ($v['estado'] == 'liquidado') $badgeClass = 'badge-secondary';
                    ?>
                    <style>
                        .badge-warning { background: #f39c12 !important; color: white !important; }
                        .badge-secondary { background: #95a5a6 !important; color: white !important; }
                        .badge-primary { background: var(--accent) !important; color: white !important; }
                    </style>
                    <span class="badge <?= $badgeClass ?>">
                        <?= str_replace('_', ' ', strtoupper($v['estado'] ?? 'PENDIENTE')) ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                        <a href="viajes/detalle/<?= $v['id'] ?>" class="liq-icon-btn liq-icon-btn--edit" title="Gestionar viaje">
                        <i class="fa-solid fa-sliders"></i>
                    </a>
                    <button onclick="confirmarEliminarViaje(<?= $v['id'] ?>)" class="liq-icon-btn liq-icon-btn--delete" title="Eliminar viaje">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="modal-viaje" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header"><h3>Registrar Nuevo Viaje</h3><span class="close-modal" onclick="closeModal('modal-viaje')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="nuevo">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Cliente</label>
                        <select name="cliente_id" class="input-field" required>
                            <?php foreach($lista_comerciales as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Camión</label>
                        <select name="vehiculo_id" id="v-select" class="input-field" onchange="sugerirChofer(this)" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($lista_camiones as $cam): ?>
                                <option value="<?= $cam['id'] ?>" data-chofer="<?= $cam['chofer_id'] ?>" data-acoplado="<?= htmlspecialchars($cam['acoplado']) ?>"><?= $cam['dominio'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Acoplado</label>
                        <input type="text" name="acoplado" id="aco-input" class="input-field" placeholder="Patente acoplado">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Chofer</label>
                        <select name="chofer_id" id="ch-select" class="input-field" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($lista_choferes as $ch): ?><option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['apellido'] . " " . $ch['nombre']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Producto</label>
                        <input type="text" name="producto" class="input-field" placeholder="Ej: Soja, Maíz, Arena">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Comisión Dador de Carga</label>
                        <select name="comision_tipo" class="input-field">
                            <option value="ninguna">No Paga</option>
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="monto_fijo">Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor Comisión</label>
                        <input type="number" step="0.01" name="comision_valor" class="input-field" value="0">
                    </div>
                    <div class="form-group">
                        <label>Comisionista</label>
                        <select name="comisionista_id" class="input-field">
                            <option value="">-- Seleccionar si corresponde --</option>
                            <?php foreach($lista_comisionistas as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>CTG (Granos)</label>
                        <input type="text" name="ctg_nro" class="input-field" placeholder="Nro de CTG">
                    </div>
                    <div class="form-group">
                        <label>Carta de Porte</label>
                        <input type="text" name="carta_porte_nro" class="input-field" placeholder="Nro CP-E">
                    </div>
                    <div class="form-group">
                        <label>Otros Documentos</label>
                        <input type="text" name="otros_docs" class="input-field" placeholder="Remitos / Guías">
                    </div>
                    <div class="form-group">
                        <label>Pagador del Flete</label>
                        <select name="pagador_id" class="input-field">
                            <option value="">-- Seleccionar Pagador --</option>
                            <?php foreach($lista_pagadores as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['razon_social']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Origen</label><input type="text" name="origen" class="input-field" required></div>
                    <div class="form-group"><label>Destino</label><input type="text" name="destino" class="input-field" required></div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Fecha Carga</label><input type="date" name="fecha_carga" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Tarifa x Ton.</label><input type="number" step="0.01" name="tarifa_tonelada" class="input-field" required></div>
                    <div class="form-group"><label>Peso Estimado (Ton.)</label><input type="number" step="0.01" name="peso_estimado" class="input-field" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Iniciar Viaje</button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario oculto para eliminación -->
<form id="form-eliminar-viaje" method="POST" style="display:none;">
    <input type="hidden" name="action" value="eliminar_viaje">
    <input type="hidden" name="viaje_id" id="eliminar-viaje-id">
</form>

<script>
function sugerirChofer(select) {
    const choferId = select.options[select.selectedIndex].getAttribute('data-chofer');
    const acopladoDato = select.options[select.selectedIndex].getAttribute('data-acoplado');
    
    if (choferId) {
        document.getElementById('ch-select').value = choferId;
    }
    if (acopladoDato) {
        document.getElementById('aco-input').value = acopladoDato;
    }
}

function confirmarEliminarViaje(id) {
    if (typeof appConfirm === 'function') {
        appConfirm("¿Estás seguro de eliminar este viaje? Se ocultará del listado.", () => {
            document.getElementById('eliminar-viaje-id').value = id;
            document.getElementById('form-eliminar-viaje').submit();
        }, "Eliminar Viaje");
    }
}
</script>