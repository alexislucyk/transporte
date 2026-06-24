<?php
/**
 * Detalle Operativo y Financiero de un Viaje
 */
$viajeId = $params[0] ?? null;

if (!$viajeId) {
    die("ID de viaje no especificado.");
}

// 1. Obtener datos del viaje
$stmt = $pdo->prepare("SELECT v.*, c.razon_social as cliente, CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre, ve.dominio as patente, cp.razon_social as pagador_nombre, ccom.razon_social as comisionista_nombre 
                       FROM viajes v 
                       JOIN clientes c ON v.cliente_id = c.id 
                       JOIN choferes ch ON v.chofer_id = ch.id 
                       JOIN vehiculos ve ON v.vehiculo_id = ve.id 
                       LEFT JOIN clientes cp ON v.pagador_id = cp.id
                       LEFT JOIN clientes ccom ON v.comisionista_id = ccom.id
                       WHERE v.id = ? AND v.transportista_id = ?");
$stmt->execute([$viajeId, $active_company_id]);
$v = $stmt->fetch();

if (!$v) {
    die("Viaje no encontrado o acceso denegado.");
}

// Definir permisos de edición globales para el módulo
$is_editable_viaje = ($v['estado'] === 'en_viaje');
$is_editable_gastos_adelantos = ($v['estado'] === 'en_viaje'); 

// --- CÁLCULO DE RENTABILIDAD (Para vista y liquidación) ---
$gastos_empresa_query = $pdo->prepare("SELECT SUM(monto) FROM viajes_gastos WHERE viaje_id = ? AND pagado_por = 'empresa' AND activo = 1");
$gastos_empresa_query->execute([$viajeId]);
$gastos_empresa = $gastos_empresa_query->fetchColumn() ?: 0;

$comision_monto = ($v['comision_tipo'] === 'porcentaje') ? ($v['total_flete_neto'] * $v['comision_valor'] / 100) : ($v['comision_tipo'] === 'monto_fijo' ? $v['comision_valor'] : 0);
$chofer_monto = ($v['total_flete_neto'] * $v['chofer_porcentaje'] / 100);
$ganancia_neta_empresa = $v['total_flete_neto'] - $comision_monto - $chofer_monto - $gastos_empresa;

$error = '';

// 2. Procesar Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bloqueo de seguridad
    if (!$is_editable_viaje && (isset($_POST['action']) && ($_POST['action'] === 'editar_viaje' || $_POST['action'] === 'actualizar_docs'))) {
        $error = "El viaje ya está descargado y no permite modificaciones de datos principales o documentos desde aquí.";
    } else {
        // --- Registrar Descarga (Cierre de kilos) ---
        if (isset($_POST['action']) && $_POST['action'] === 'finalizar_descarga') {
            $neto_ton = isset($_POST['peso_neto_descarga']) ? (float)$_POST['peso_neto_descarga'] : 0;
            $bruto_ton = !empty($_POST['peso_bruto']) ? (float)$_POST['peso_bruto'] : null;
            $tara_ton = !empty($_POST['peso_tara']) ? (float)$_POST['peso_tara'] : null;

            if ($bruto_ton !== null && $tara_ton !== null) {
                $neto_ton = max(0, $bruto_ton - $tara_ton);
            }

            $neto_kg = $neto_ton * 1000;
            $bruto_kg = ($bruto_ton !== null) ? ($bruto_ton * 1000) : 0;
            $tara_kg = ($tara_ton !== null) ? ($tara_ton * 1000) : 0;
            $flete_neto = $neto_ton * $v['tarifa_tonelada'];
            
            try {
                $sql = "UPDATE viajes SET peso_bruto=?, peso_tara=?, peso_neto=?, total_flete_neto=?, estado='descargado' WHERE id=?"; 
                $pdo->prepare($sql)->execute([$bruto_kg, $tara_kg, $neto_kg, $flete_neto, $viajeId]);
                header("Location: " . $base_path . "viajes/detalle/" . $viajeId); 
                exit;
            } catch (PDOException $e) { 
                $error = $e->getMessage(); 
            }
        }

        // --- Actualizar Documentación ---
        if (isset($_POST['action']) && $_POST['action'] === 'actualizar_docs') {
            try {
                $sql = "UPDATE viajes SET ctg_nro=?, carta_porte_nro=?, otros_docs=? WHERE id=?";
                $pdo->prepare($sql)->execute([$_POST['ctg_nro'], $_POST['carta_porte_nro'], $_POST['otros_docs'], $viajeId]);
                header("Location: " . $base_path . "viajes/detalle/" . $viajeId); 
                exit;
            } catch (PDOException $e) { 
                $error = $e->getMessage(); 
            }
        }

        // --- Editar Datos del Viaje ---
        if (isset($_POST['action']) && $_POST['action'] === 'editar_viaje') {
            try {
                $pdo->beginTransaction();
                
                $sql = "UPDATE viajes SET cliente_id=?, chofer_id=?, vehiculo_id=?, acoplado=?, origen=?, destino=?, producto=?, fecha_carga=?, tarifa_tonelada=?, chofer_porcentaje=?, comision_tipo=?, comision_valor=?, comisionista_id=?, pagador_id=?, ctg_nro=?, carta_porte_nro=?, otros_docs=? WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $_POST['cliente_id'], $_POST['chofer_id'], $_POST['vehiculo_id'], $_POST['acoplado'],
                    $_POST['origen'], $_POST['destino'], $_POST['producto'], $_POST['fecha_carga'],
                    $_POST['tarifa'], $_POST['porcentaje'], $_POST['comision_tipo'], $_POST['comision_valor'],
                    $_POST['comisionista_id'] ?: null, $_POST['pagador_id'] ?: null,
                    $_POST['ctg_nro'], $_POST['carta_porte_nro'], $_POST['otros_docs'],
                    $viajeId
                ]);

                $stmt_recalc = $pdo->prepare("SELECT peso_bruto, peso_tara, peso_neto, total_flete_bruto, estado FROM viajes WHERE id = ?");
                $stmt_recalc->execute([$viajeId]);
                $current_data = $stmt_recalc->fetch();

                if ($current_data['estado'] === 'en_viaje') {
                    $new_total_flete_bruto = ($current_data['peso_bruto'] / 1000) * $_POST['tarifa'];
                    $pdo->prepare("UPDATE viajes SET total_flete_bruto = ? WHERE id = ?")->execute([$new_total_flete_bruto, $viajeId]);
                } else {
                    $new_total_flete_neto = ($current_data['peso_neto'] / 1000) * $_POST['tarifa'];
                    $pdo->prepare("UPDATE viajes SET total_flete_neto = ? WHERE id = ?")->execute([$new_total_flete_neto, $viajeId]);
                }
                
                $pdo->commit();
                header("Location: " . $base_path . "viajes/detalle/" . $viajeId); 
                exit;
            } catch (PDOException $e) { 
                $pdo->rollBack();
                $error = $e->getMessage(); 
            }
        }

        // --- Procesar Eliminaciones ---
        if ($is_editable_gastos_adelantos && isset($_POST['action']) && ($_POST['action'] === 'delete_gasto' || $_POST['action'] === 'delete_adelanto')) {
            $id_to_delete = $_POST['id_to_delete'];
            try {
                if ($_POST['action'] === 'delete_gasto') {
                    $sql = "UPDATE viajes_gastos SET activo = 0 WHERE id = ? AND viaje_id = ?";
                } else {
                    $sql = "UPDATE viajes_adelantos SET activo = 0 WHERE id = ? AND viaje_id = ?";
                }
                $pdo->prepare($sql)->execute([$id_to_delete, $viajeId]);
                header("Location: " . $base_path . "viajes/detalle/" . $viajeId); 
                exit;
            } catch (PDOException $e) { 
                $error = $e->getMessage(); 
            }
        }

        // --- Procesar Gastos/Adelantos (Nuevo/Editar) ---
        if ($is_editable_gastos_adelantos && isset($_POST['movimiento'])) {
            try {
                if ($_POST['movimiento'] === 'gasto') {
                    $tipo = $_POST['tipo'] ?? null;
                    $desc = $_POST['desc'] ?? null;
                    $pagado_por = $_POST['pagado_por'] ?? null;
                    $monto = $_POST['monto'] ?? null;
                    $fecha = $_POST['fecha'] ?? null;

                    if (empty($tipo) || empty($pagado_por)) {
                        throw new Exception('Faltan datos obligatorios para el gasto.');
                    }

                    if (!empty($_POST['id'])) {
                        $sql = "UPDATE viajes_gastos SET tipo_gasto=?, monto=?, descripcion=?, pagado_por=?, fecha=? WHERE id=? AND viaje_id=?";
                        $pdo->prepare($sql)->execute([$tipo, $monto, $desc, $pagado_por, $fecha, $_POST['id'], $viajeId]);
                    } else {
                        $sql = "INSERT INTO viajes_gastos (viaje_id, tipo_gasto, monto, descripcion, pagado_por, fecha, activo) VALUES (?, ?, ?, ?, ?, ?, 1)";
                        $pdo->prepare($sql)->execute([$viajeId, $tipo, $monto, $desc, $pagado_por, $fecha]);
                    }
                } elseif ($_POST['movimiento'] === 'adelanto') {
                    $monto = $_POST['monto'] ?? null;
                    $fecha = $_POST['fecha'] ?? null;
                    $metodo = $_POST['metodo'] ?? null;

                    if (!empty($_POST['id'])) {
                        $sql = "UPDATE viajes_adelantos SET monto=?, fecha=?, metodo_pago=? WHERE id=? AND viaje_id=?";
                        $pdo->prepare($sql)->execute([$monto, $fecha, $metodo, $_POST['id'], $viajeId]);
                    } else {
                        $sql = "INSERT INTO viajes_adelantos (viaje_id, monto, fecha, metodo_pago, activo) VALUES (?, ?, ?, ?, 1)";
                        $pdo->prepare($sql)->execute([$viajeId, $monto, $fecha, $metodo]);
                    }
                }

                header("Location: " . $base_path . "viajes/detalle/" . $viajeId); 
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// 3. Obtener listados actualizados
$gastos_stmt = $pdo->prepare("SELECT * FROM viajes_gastos WHERE viaje_id = ? AND activo = 1"); 
$gastos_stmt->execute([$viajeId]);
$lista_gastos = $gastos_stmt->fetchAll();

$adelantos_stmt = $pdo->prepare("SELECT * FROM viajes_adelantos WHERE viaje_id = ? AND activo = 1"); 
$adelantos_stmt->execute([$viajeId]);
$lista_adelantos = $adelantos_stmt->fetchAll();

// 4. Obtener listados para selectores de formularios
$stmt_pag = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1"); 
$stmt_pag->execute([$active_company_id]);
$lista_pagadores = $stmt_pag->fetchAll();

$stmt_com = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1"); 
$stmt_com->execute([$active_company_id]);
$lista_comisionistas = $stmt_com->fetchAll();

$stmt_cli_all = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comercial = 1"); 
$stmt_cli_all->execute([$active_company_id]);
$lista_comerciales = $stmt_cli_all->fetchAll();

$stmt_cho_all = $pdo->prepare("SELECT id, nombre, apellido FROM choferes WHERE transportista_id = ? AND activo = 1"); 
$stmt_cho_all->execute([$active_company_id]);
$lista_choferes = $stmt_cho_all->fetchAll();

$stmt_veh_all = $pdo->prepare("SELECT id, dominio, chofer_id, acoplado FROM vehiculos WHERE transportista_id = ?"); 
$stmt_veh_all->execute([$active_company_id]);
$lista_camiones = $stmt_veh_all->fetchAll();
?>

<div style="margin-bottom: 25px;">
    <a href="viajes" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; display: inline-block; margin-bottom: 10px;">
        <i class="fas fa-arrow-left"></i> Volver a Operativa
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Viaje #<?= htmlspecialchars($v['id']) ?> <small style="font-size: 1rem; opacity: 0.6;">(<?= strtoupper(htmlspecialchars($v['estado'])) ?>)</small></h1>
        <div>
            <?php if ($is_editable_viaje): ?>
                <button onclick="openModal('modal-editar-viaje')" class="btn-primary" style="background:#34495e; margin-left:5px;"><i class="fas fa-edit"></i> Editar Viaje</button>
                <button onclick="prepararNuevoGasto()" class="btn-primary" style="background:#e67e22; margin-left:5px;"><i class="fas fa-gas-pump"></i> Cargar Gasto</button>
                <button onclick="prepararNuevoAdelanto()" class="btn-primary" style="margin-left:5px;"><i class="fas fa-hand-holding-usd"></i> Dar Adelanto</button>
                <button onclick="openModal('modal-descarga')" class="btn-primary" style="background:#2ecc71"><i class="fas fa-balance-scale"></i> Finalizar Descarga</button>
            <?php else: ?>
                <span class="badge badge-secondary" style="padding: 10px 20px; font-size: 1rem;"><i class="fas fa-lock"></i> FLETE EN CIERRE FINANCIERO</span>
                <a href="cobranzas" class="btn-primary" style="background:var(--accent); margin-left:10px;"><i class="fas fa-calculator"></i> Ir a Cobranzas</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="responsive-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
    <div>
        <div class="card" style="border-left: 4px solid var(--accent);">
            <h3>Datos del Flete</h3>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($v['cliente']) ?></p>
            <p><strong>Chofer:</strong> <?= htmlspecialchars($v['chofer_nombre']) ?> (<?= (float)$v['chofer_porcentaje'] ?>%)</p>
            <p><strong>Unidad:</strong> <?= htmlspecialchars($v['patente']) ?></p>
            <p><strong>Acoplado:</strong> <?= htmlspecialchars($v['acoplado'] ?? '-') ?></p>
            <hr style="opacity: 0.1; margin: 15px 0;">

            <h4>Documentación</h4>
            <p><strong>CTG:</strong> <?= htmlspecialchars($v['ctg_nro'] ?: '-') ?></p>
            <p><strong>Carta de Porte:</strong> <?= htmlspecialchars($v['carta_porte_nro'] ?: '-') ?></p>
            <?php if (!empty($v['otros_docs'])): ?>
                <p><strong>Otros:</strong> <?= htmlspecialchars($v['otros_docs']) ?></p>
            <?php endif; ?>

            <p><strong>Ruta:</strong> <?= htmlspecialchars($v['origen']) ?> <i class="fas fa-arrow-right"></i> <?= htmlspecialchars($v['destino']) ?></p>
            <hr style="opacity: 0.1; margin: 15px 0;">

            <?php if ($v['estado'] === 'en_viaje'): ?>
                <p><strong>Flete Est. (Bruto):</strong> <?= formatMoney($v['total_flete_bruto']) ?></p>
                <p><strong>Ganancia Est. Chofer:</strong> <?= formatMoney($v['total_flete_bruto'] * $v['chofer_porcentaje'] / 100) ?></p>
                <p style="color: #e67e22; font-size: 0.85rem;"><i class="fas fa-clock"></i> Pendiente de pesaje en destino.</p>
            <?php else: ?>
                <?php
                $peso_neto_real = $v['peso_neto'] ?? 0;
                $flete_bruto_estimado = $v['total_flete_bruto'] ?? 0;
                $tarifa_tonelada = $v['tarifa_tonelada'] ?? 0;

                $peso_estimado_kg = ($tarifa_tonelada > 0) ? ($flete_bruto_estimado / $tarifa_tonelada) * 1000 : 0;
                $diferencia_kilos = $peso_neto_real - $peso_estimado_kg;

                $kilos_color = ($diferencia_kilos > 0) ? '#2ecc71' : (($diferencia_kilos < 0) ? '#e74c3c' : 'inherit');
                $kilos_texto = number_format($diferencia_kilos, 0, '', '.') . ' Kg';
                ?>
                <p><strong>Peso Neto Descargado:</strong> <?= number_format(($peso_neto_real) / 1000, 2) ?> Ton. (<?= number_format($peso_neto_real, 0, '', '.') ?> Kg)</p>
                <p><strong>Flete Final (Neto):</strong> <span style="color: #2ecc71; font-weight:bold;"><?= formatMoney($v['total_flete_neto']) ?></span></p>
                <p><strong>Ganancia Real Chofer:</strong> <span style="color: #2ecc71; font-weight:bold;"><?= formatMoney(($v['total_flete_neto'] * $v['chofer_porcentaje']) / 100) ?></span></p>
                <p><strong>Diferencia vs Est.:</strong> <span style="color: <?= $kilos_color ?>; font-weight: bold;"><?= $kilos_texto ?></span></p>
            <?php endif; ?>

            <hr style="opacity: 0.1; margin: 15px 0;">
            <p><strong>Comisión Dador:</strong>
                <?php
                if ($v['comision_tipo'] === 'porcentaje') echo htmlspecialchars($v['comision_valor']) . '% ';
                elseif ($v['comision_tipo'] === 'monto_fijo') echo formatMoney($v['comision_valor']) . ' ';
                else echo 'No paga ';

                if (!empty($v['comisionista_nombre'])) echo " (a " . htmlspecialchars($v['comisionista_nombre']) . ")";
                ?>
            </p>

            <p><strong>Pagador Flete:</strong> <?= htmlspecialchars($v['pagador_nombre'] ?: 'No especificado') ?></p>
            
            <?php if (!empty($v['factura_nro'])): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: var(--accent);"><strong>Comprobante:</strong> <?= htmlspecialchars($v['factura_nro']) ?></p>
                <p><strong>Fecha Factura:</strong> <?= formatDate($v['factura_fecha'] ?? null) ?></p>
            <?php endif; ?>

            <?php if (!empty($v['fecha_cobro'])): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: #2ecc71;"><strong>Fecha Cobro:</strong> <?= formatDate($v['fecha_cobro']) ?></p>
            <?php endif; ?>

            <?php if ($v['estado'] === 'liquidado'): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: #2ecc71; font-weight: bold;"><i class="fas fa-check-circle"></i> Saldo cerrado con el chofer.</p>
            <?php endif; ?>

            <?php if ($v['estado'] !== 'en_viaje'): ?>
                <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.03); border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                    <h4 style="margin-top:0; color: var(--primary);"><i class="fas fa-chart-pie"></i> Liquidación Empresa</h4>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 5px;">
                        <span>Comisión Terceros:</span> <span>- <?= formatMoney($comision_monto) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 5px;">
                        <span>Pago Chofer:</span> <span>- <?= formatMoney($chofer_monto) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 5px;">
                        <span>Gastos Empresa:</span> <span>- <?= formatMoney($gastos_empresa) ?></span>
                    </div>
                    <hr style="border: 0; border-top: 1px solid #ccc; margin: 10px 0;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; color: var(--accent);">
                        <span>Rentabilidad Neta:</span> <span><?= formatMoney($ganancia_neta_empresa) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 25px;">
        <div class="card">
            <h3>Gastos de Viaje (Rendición)</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th style="text-align:right">Monto</th><th style="width: 50px;"></th></tr>
                    </thead>
                    <tbody>
                        <?php $totalG = 0; foreach($lista_gastos as $g): $totalG += $g['monto']; ?>
                            <tr>
                                <td><?= formatDate($g['fecha']) ?></td>
                                <td>
                                    <?= strtoupper(htmlspecialchars($g['tipo_gasto'])) ?> <br>
                                    <small style="opacity:0.7; font-size: 0.75rem;"><?= $g['pagado_por'] === 'adelanto' ? 'PAGADO CON ADELANTO' : 'PAGADO POR EMPRESA' ?></small>
                                </td>
                                <td><?= htmlspecialchars($g['descripcion'] ?? '') ?></td>
                                <td style="text-align:right"><?= formatMoney($g['monto']) ?></td>
                                <td>
                                    <?php if ($is_editable_gastos_adelantos): ?>
                                        <button onclick='editGasto(<?= json_encode($g) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                                        <button onclick='deleteGasto(<?= (int)$g['id'] ?>)' title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" style="text-align:right">Total Gastos:</th><th style="text-align:right"><?= formatMoney($totalG) ?></th><th></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>Adelantos entregados al Chofer</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr><th>Fecha</th><th>Método</th><th style="text-align:right">Monto</th><th style="width: 50px;"></th></tr>
                    </thead>
                    <tbody>
                        <?php $totalA = 0; foreach($lista_adelantos as $a): $totalA += $a['monto']; ?>
                            <tr>
                                <td><?= formatDate($a['fecha']) ?></td>
                                <td><?= strtoupper(htmlspecialchars($a['metodo_pago'])) ?></td>
                                <td style="text-align:right"><?= formatMoney($a['monto']) ?></td>
                                <td>
                                    <?php if ($is_editable_gastos_adelantos): ?>
                                        <button onclick='editAdelanto(<?= json_encode($a) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                                        <button onclick='deleteAdelanto(<?= (int)$a['id'] ?>)' title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2" style="text-align:right">Total Adelantos:</th><th style="text-align:right"><?= formatMoney($totalA) ?></th><th></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<form id="form-delete-gasto" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_gasto">
    <input type="hidden" name="id_to_delete" id="delete-gasto-id">
</form>
<form id="form-delete-adelanto" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_adelanto">
    <input type="hidden" name="id_to_delete" id="delete-adelanto-id">
</form>

<div id="modal-gasto" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3 id="gasto-title">Cargar Gasto de Viaje</h3><span class="close-modal" onclick="closeModal('modal-gasto')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="gasto">
                <input type="hidden" name="id" id="gasto-id">
                <div class="form-group">
                    <label>Tipo de Gasto</label>
                    <select name="tipo" id="gasto-tipo" class="input-field" required>
                        <option value="combustible">Combustible</option>
                        <option value="peaje">Peaje</option>
                        <option value="playa">Playa</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pagado por</label>
                    <select name="pagado_por" id="gasto-pagado-por" class="input-field" required>
                        <option value="adelanto">Adelanto del Viaje (Chofer)</option>
                        <option value="empresa">Empresa</option>
                        <option value="descuento_flete">Descuento del Flete (Cliente)</option>
                    </select>
                </div>
                <div class="form-group"><label>Monto</label><input type="number" step="0.01" name="monto" id="gasto-monto" class="input-field" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="gasto-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Descripción</label><input type="text" name="desc" id="gasto-desc" class="input-field"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Registrar Gasto</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-adelanto" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3 id="adelanto-title">Registrar Adelanto</h3><span class="close-modal" onclick="closeModal('modal-adelanto')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="adelanto">
                <input type="hidden" name="id" id="adelanto-id">
                <div class="form-group"><label>Monto a entregar</label><input type="number" step="0.01" name="monto" id="adelanto-monto" class="input-field" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="adelanto-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="metodo" id="adelanto-metodo" class="input-field" required>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Entregar Adelanto</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-descarga" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3>Finalizar Descarga</h3><span class="close-modal" onclick="closeModal('modal-descarga')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="finalizar_descarga">
                <p style="margin-bottom: 20px; font-size: 0.9rem; opacity: 0.8;">Ingresa los valores del ticket de balanza de destino.</p>
                
                <div class="form-group">
                    <label>Peso Neto Descarga (Ton)</label>
                    <input type="number" step="0.001" min="0" name="peso_neto_descarga" id="peso_neto_descarga_input" class="input-field" required>
                </div>

                <div class="form-group" style="margin-top:14px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" id="mostrar_bruto_tara_checkbox" onchange="toggleBrutoTara(this)">
                        Registrar también Bruto y Tara (opcional)
                    </label>
                </div>

                <div id="bruto_tara_block" style="display: none; border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px;">
                    <div class="form-group"><label>Peso Bruto (Ton)</label><input type="number" step="0.001" min="0" name="peso_bruto" id="peso_bruto_input" class="input-field" oninput="calcularNetoDesdeBloque()"></div>
                    <div class="form-group"><label>Peso Tara (Ton)</label><input type="number" step="0.001" min="0" name="peso_tara" id="peso_tara_input" class="input-field" oninput="calcularNetoDesdeBloque()"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" id="btn-confirmar-descarga">Confirmar Descarga</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-editar-viaje" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>Editar Información del Viaje</h3><span class="close-modal" onclick="closeModal('modal-editar-viaje')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="editar_viaje">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Cliente</label>
                        <select name="cliente_id" class="input-field" required>
                            <?php foreach($lista_comerciales as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $v['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Camión</label>
                        <select name="vehiculo_id" id="edit-v-select" class="input-field" onchange="sugerirChoferEdit(this)" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($lista_camiones as $cam): ?>
                                <option value="<?= $cam['id'] ?>" data-chofer="<?= $cam['chofer_id'] ?>" data-acoplado="<?= htmlspecialchars($cam['acoplado'] ?? '') ?>" <?= $v['vehiculo_id'] == $cam['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cam['dominio']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Acoplado</label>
                        <input type="text" name="acoplado" id="edit-aco-input" class="input-field" value="<?= htmlspecialchars($v['acoplado'] ?? '') ?>" placeholder="Patente acoplado">
                    </div>
                    <div class="form-group">
                        <label>Chofer</label>
                        <select name="chofer_id" id="edit-ch-select" class="input-field" required>
                            <?php foreach($lista_choferes as $ch): ?>
                                <option value="<?= $ch['id'] ?>" <?= $v['chofer_id'] == $ch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['apellido'] . ", " . $ch['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group"><label>Origen</label><input type="text" name="origen" class="input-field" value="<?= htmlspecialchars($v['origen']) ?>" required></div>
                    <div class="form-group"><label>Destino</label><input type="text" name="destino" class="input-field" value="<?= htmlspecialchars($v['destino']) ?>" required></div>
                    <div class="form-group"><label>Producto</label><input type="text" name="producto" class="input-field" value="<?= htmlspecialchars($v['producto']) ?>" required></div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group"><label>Fecha Carga</label><input type="date" name="fecha_carga" class="input-field" value="<?= htmlspecialchars($v['fecha_carga']) ?>" required></div>
                    <div class="form-group"><label>Tarifa por Tonelada ($)</label><input type="number" step="0.01" name="tarifa" class="input-field" value="<?= (float)$v['tarifa_tonelada'] ?>" required></div>
                    <div class="form-group"><label>% Chofer</label><input type="number" step="0.01" name="porcentaje" class="input-field" value="<?= (float)$v['chofer_porcentaje'] ?>" required></div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Tipo Comisión</label>
                        <select name="comision_tipo" class="input-field">
                            <option value="ninguno" <?= $v['comision_tipo'] === 'ninguno' ? 'selected' : '' ?>>Ninguno</option>
                            <option value="porcentaje" <?= $v['comision_tipo'] === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                            <option value="monto_fijo" <?= $v['comision_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto Fijo</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Valor Comisión</label><input type="number" step="0.01" name="comision_valor" class="input-field" value="<?= (float)$v['comision_valor'] ?>"></div>
                    <div class="form-group">
                        <label>Comisionista</label>
                        <select name="comisionista_id" class="input-field">
                            <option value="">Ninguno</option>
                            <?php foreach($lista_comisionistas as $com): ?>
                                <option value="<?= $com['id'] ?>" <?= $v['comisionista_id'] == $com['id'] ? 'selected' : '' ?>><?= htmlspecialchars($com['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Pagador del Flete Alternativo</label>
                        <select name="pagador_id" class="input-field">
                            <option value="">El mismo Cliente principal</option>
                            <?php foreach($lista_pagadores as $pag): ?>
                                <option value="<?= $pag['id'] ?>" <?= $v['pagador_id'] == $pag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pag['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Nro CTG</label><input type="text" name="ctg_nro" class="input-field" value="<?= htmlspecialchars($v['ctg_nro'] ?? '') ?>"></div>
                    <div class="form-group"><label>Nro Carta Porte</label><input type="text" name="carta_porte_nro" class="input-field" value="<?= htmlspecialchars($v['carta_porte_nro'] ?? '') ?>"></div>
                    <div class="form-group"><label>Otros Documentos</label><input type="text" name="otros_docs" class="input-field" value="<?= htmlspecialchars($v['otros_docs'] ?? '') ?>"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const target = document.getElementById(id);
    if(target) target.style.display = 'block';
}

function closeModal(id) {
    const target = document.getElementById(id);
    if(target) target.style.display = 'none';
}

function prepararNuevoGasto() {
    document.getElementById('gasto-title').innerText = 'Cargar Gasto de Viaje';
    document.getElementById('gasto-id').value = '';
    document.getElementById('gasto-monto').value = '';
    document.getElementById('gasto-desc').value = '';
    openModal('modal-gasto');
}

function editGasto(gasto) {
    document.getElementById('gasto-title').innerText = 'Editar Gasto de Viaje';
    document.getElementById('gasto-id').value = gasto.id;
    document.getElementById('gasto-tipo').value = gasto.tipo_gasto;
    document.getElementById('gasto-pagado-por').value = gasto.pagado_por;
    document.getElementById('gasto-monto').value = gasto.monto;
    document.getElementById('gasto-fecha').value = gasto.fecha;
    document.getElementById('gasto-desc').value = gasto.descripcion;
    openModal('modal-gasto');
}

function deleteGasto(id) {
    if(confirm('¿Estás seguro de que deseas eliminar este gasto?')) {
        document.getElementById('delete-gasto-id').value = id;
        document.getElementById('form-delete-gasto').submit();
    }
}

function prepararNuevoAdelanto() {
    document.getElementById('adelanto-title').innerText = 'Registrar Adelanto';
    document.getElementById('adelanto-id').value = '';
    document.getElementById('adelanto-monto').value = '';
    openModal('modal-adelanto');
}

function editAdelanto(adelanto) {
    document.getElementById('adelanto-title').innerText = 'Editar Adelanto';
    document.getElementById('adelanto-id').value = adelanto.id;
    document.getElementById('adelanto-monto').value = adelanto.monto;
    document.getElementById('adelanto-fecha').value = adelanto.fecha;
    document.getElementById('adelanto-metodo').value = adelanto.metodo_pago;
    openModal('modal-adelanto');
}

function deleteAdelanto(id) {
    if(confirm('¿Estás seguro de que deseas eliminar este adelanto?')) {
        document.getElementById('delete-adelanto-id').value = id;
        document.getElementById('form-delete-adelanto').submit();
    }
}

function toggleBrutoTara(chk) {
    const block = document.getElementById('bruto_tara_block');
    const brutoInput = document.getElementById('peso_bruto_input');
    const taraInput = document.getElementById('peso_tara_input');
    const netoInput = document.getElementById('peso_neto_descarga_input');

    if(chk.checked) {
        block.style.display = 'block';
        brutoInput.required = true;
        taraInput.required = true;
        netoInput.readOnly = true;
        calcularNetoDesdeBloque();
    } else {
        block.style.display = 'none';
        brutoInput.required = false;
        taraInput.required = false;
        netoInput.readOnly = false;
        brutoInput.value = '';
        taraInput.value = '';
    }
}

function calcularNetoDesdeBloque() {
    const bruto = parseFloat(document.getElementById('peso_bruto_input').value) || 0;
    const tara = parseFloat(document.getElementById('peso_tara_input').value) || 0;
    const netoInput = document.getElementById('peso_neto_descarga_input');
    
    if(document.getElementById('mostrar_bruto_tara_checkbox').checked) {
        netoInput.value = Math.max(0, bruto - tara).toFixed(3);
    }
}

function sugerirChoferEdit(selectElement) {
    const option = selectElement.options[selectElement.selectedIndex];
    if(!option || !option.value) return;

    const choferId = option.getAttribute('data-chofer');
    const acoplado = option.getAttribute('data-acoplado');

    if(choferId) {
        document.getElementById('edit-ch-select').value = choferId;
    }
    if(acoplado) {
        document.getElementById('edit-aco-input').value = acoplado;
    }
}
</script>