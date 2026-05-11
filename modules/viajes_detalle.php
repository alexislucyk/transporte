<?php
/**
 * Detalle Operativo y Financiero de un Viaje
 */
$viajeId = $params[0];

// 1. Obtener datos del viaje
$stmt = $pdo->prepare("SELECT v.*, c.razon_social as cliente, CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre, ve.dominio as patente 
                       FROM viajes v 
                       JOIN clientes c ON v.cliente_id = c.id 
                       JOIN choferes ch ON v.chofer_id = ch.id 
                       JOIN vehiculos ve ON v.vehiculo_id = ve.id 
                       WHERE v.id = ? AND v.transportista_id = ?");
$stmt->execute([$viajeId, $active_company_id]);
$v = $stmt->fetch();

if (!$v) die("Viaje no encontrado o acceso denegado.");

// 2. Procesar Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Registrar Descarga (Cierre de kilos) ---
    if (isset($_POST['action']) && $_POST['action'] === 'finalizar_descarga') {
        $bruto_kg = (int)$_POST['peso_bruto'];
        $tara_kg = (int)$_POST['peso_tara'];
        $neto_kg = $bruto_kg - $tara_kg;
        $flete_neto = ($neto_kg / 1000) * $v['tarifa_tonelada']; // Convertir Kg a Ton para el cálculo del flete
        
        try {
            $sql = "UPDATE viajes SET peso_bruto=?, peso_tara=?, total_flete_neto=?, estado='descargado' WHERE id=?"; // peso_bruto y peso_tara almacenan Kg
            $pdo->prepare($sql)->execute([$bruto_kg, $tara_kg, $flete_neto, $viajeId]);
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Registrar Facturación ---
    if (isset($_POST['action']) && $_POST['action'] === 'registrar_factura') {
        $nro = trim($_POST['factura_nro']);
        $fecha = $_POST['factura_fecha'];
        
        try {
            $sql = "UPDATE viajes SET factura_nro=?, factura_fecha=?, estado='facturado' WHERE id=?";
            $pdo->prepare($sql)->execute([$nro, $fecha, $viajeId]);
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Actualizar Documentación ---
    if (isset($_POST['action']) && $_POST['action'] === 'actualizar_docs') {
        try {
            $sql = "UPDATE viajes SET ctg_nro=?, carta_porte_nro=?, otros_docs=? WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['ctg_nro'], $_POST['carta_porte_nro'], $_POST['otros_docs'], $viajeId]);
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Registrar Cobro ---
    if (isset($_POST['action']) && $_POST['action'] === 'registrar_cobro') {
        $fecha = $_POST['fecha_cobro'];
        
        try {
            $sql = "UPDATE viajes SET fecha_cobro=?, estado='cobrado' WHERE id=?";
            $pdo->prepare($sql)->execute([$fecha, $viajeId]);
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Editar Datos del Viaje ---
    if (isset($_POST['action']) && $_POST['action'] === 'editar_viaje') {
        try {
            $sql = "UPDATE viajes SET chofer_porcentaje=?, acoplado=?, producto=?, tarifa_tonelada=?, comision_tipo=?, comision_valor=?, comision_receptor=?, pagador_flete=? WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['porcentaje'], $_POST['acoplado'], $_POST['producto'], $_POST['tarifa'], $_POST['comision_tipo'], $_POST['comision_valor'], $_POST['comision_receptor'], $_POST['pagador_flete'], $viajeId]);
            
            // Si el viaje ya estaba descargado, debemos recalcular el flete neto con la nueva tarifa
            $pdo->prepare("UPDATE viajes SET total_flete_neto = (peso_neto / 1000) * tarifa_tonelada WHERE id = ? AND estado != 'en_viaje'")->execute([$viajeId]);
            
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Procesar Eliminaciones ---
    if (isset($_POST['action']) && ($_POST['action'] === 'delete_gasto' || $_POST['action'] === 'delete_adelanto')) {
        $id_to_delete = $_POST['id_to_delete'];
        try {
            if ($_POST['action'] === 'delete_gasto') {
                $sql = "DELETE FROM viajes_gastos WHERE id = ? AND viaje_id = ?";
            } else {
                $sql = "DELETE FROM viajes_adelantos WHERE id = ? AND viaje_id = ?";
            }
            $pdo->prepare($sql)->execute([$id_to_delete, $viajeId]);
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Procesar Gastos/Adelantos (Nuevo/Editar) ---
    if (isset($_POST['movimiento'])) {
        try {
        if ($_POST['movimiento'] === 'gasto') {
            if (!empty($_POST['id'])) {
                $sql = "UPDATE viajes_gastos SET tipo_gasto=?, monto=?, descripcion=?, pagado_por=?, fecha=? WHERE id=? AND viaje_id=?";
                $pdo->prepare($sql)->execute([$_POST['tipo'], $_POST['monto'], $_POST['desc'], $_POST['pagado_por'], $_POST['fecha'], $_POST['id'], $viajeId]);
            } else {
                $sql = "INSERT INTO viajes_gastos (viaje_id, tipo_gasto, monto, descripcion, pagado_por, fecha) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$viajeId, $_POST['tipo'], $_POST['monto'], $_POST['desc'], $_POST['pagado_por'], $_POST['fecha']]);
            }
        } else {
            if (!empty($_POST['id'])) {
                $sql = "UPDATE viajes_adelantos SET monto=?, fecha=?, metodo_pago=? WHERE id=? AND viaje_id=?";
                $pdo->prepare($sql)->execute([$_POST['monto'], $_POST['fecha'], $_POST['metodo'], $_POST['id'], $viajeId]);
            } else {
                $sql = "INSERT INTO viajes_adelantos (viaje_id, monto, fecha, metodo_pago) VALUES (?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$viajeId, $_POST['monto'], $_POST['fecha'], $_POST['metodo']]);
            }
        }
        header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

// 3. Obtener listados
$gastos = $pdo->prepare("SELECT * FROM viajes_gastos WHERE viaje_id = ?"); $gastos->execute([$viajeId]);
$adelantos = $pdo->prepare("SELECT * FROM viajes_adelantos WHERE viaje_id = ?"); $adelantos->execute([$viajeId]);
?>

<div style="margin-bottom: 25px;">
    <a href="viajes" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; display: inline-block; margin-bottom: 10px;">
        <i class="fas fa-arrow-left"></i> Volver a Operativa
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Viaje #<?= $v['id'] ?> <small style="font-size: 1rem; opacity: 0.6;">(<?= strtoupper($v['estado']) ?>)</small></h1>
        <div>
            <?php if ($v['estado'] !== 'liquidado'): ?>
                <?php if ($v['estado'] === 'en_viaje'): ?>
                    <button onclick="openModal('modal-descarga')" class="btn-primary" style="background:#2ecc71"><i class="fas fa-balance-scale"></i> Finalizar Descarga</button>
                <?php endif; ?>
                <?php if ($v['estado'] === 'descargado'): ?>
                    <button onclick="openModal('modal-factura')" class="btn-primary" style="background:#3498db"><i class="fas fa-file-invoice"></i> Registrar Factura</button>
                <?php endif; ?>
                <?php if ($v['estado'] === 'facturado'): ?>
                    <button onclick="openModal('modal-cobro')" class="btn-primary" style="background:#2ecc71"><i class="fas fa-money-bill-wave"></i> Registrar Cobro</button>
                <?php endif; ?>
            <button onclick="openModal('modal-editar-viaje')" class="btn-primary" style="background:#34495e"><i class="fas fa-edit"></i> Editar Viaje</button>
            <button onclick="openModal('modal-docs')" class="btn-primary" style="background:#7f8c8d"><i class="fas fa-file-alt"></i> Documentos</button>
                <button onclick="prepararNuevoGasto()" class="btn-primary" style="background:#e67e22; margin-left:10px;"><i class="fas fa-gas-pump"></i> Cargar Gasto</button>
                <button onclick="prepararNuevoAdelanto()" class="btn-primary" style="margin-left:10px;"><i class="fas fa-hand-holding-usd"></i> Dar Adelanto</button>
            <?php else: ?>
                <span class="badge badge-secondary" style="padding: 10px 20px; font-size: 1rem;"><i class="fas fa-lock"></i> VIAJE LIQUIDADO</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($error) && $error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
    <!-- Columna Izquierda: Info General -->
    <div>
        <div class="card" style="border-left: 4px solid var(--accent);">
            <h3>Datos del Flete</h3>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($v['cliente']) ?></p>
            <p><strong>Chofer:</strong> <?= htmlspecialchars($v['chofer_nombre']) ?> (<?= $v['chofer_porcentaje'] ?>%)</p>
            <p><strong>Unidad:</strong> <?= $v['patente'] ?></p>
            <p><strong>Acoplado:</strong> <?= htmlspecialchars($v['acoplado'] ?? '-') ?></p>
            <hr style="opacity: 0.1; margin: 15px 0;">
            
            <h4>Documentación</h4>
            <p><strong>CTG:</strong> <?= htmlspecialchars($v['ctg_nro'] ?: '-') ?></p>
            <p><strong>Carta de Porte:</strong> <?= htmlspecialchars($v['carta_porte_nro'] ?: '-') ?></p>
            <?php if($v['otros_docs']): ?>
                <p><strong>Otros:</strong> <?= htmlspecialchars($v['otros_docs']) ?></p>
            <?php endif; ?>

            <p><strong>Ruta:</strong> <?= htmlspecialchars($v['origen']) ?> <i class="fas fa-arrow-right"></i> <?= htmlspecialchars($v['destino']) ?></p>
            <hr style="opacity: 0.1; margin: 15px 0;">
            
            <?php if ($v['estado'] === 'en_viaje'): ?>
                <p><strong>Flete Est. (Bruto):</strong> <?= formatMoney($v['total_flete_bruto']) ?></p>
                <p><strong>Ganancia Est. Chofer:</strong> <?= formatMoney($v['total_flete_bruto'] * $v['chofer_porcentaje'] / 100) ?></p>
                <p style="color: #e67e22; font-size: 0.85rem;"><i class="fas fa-clock"></i> Pendiente de pesaje en destino.</p>
            <?php else: ?>
                <p><strong>Peso Neto Descargado:</strong> <?= number_format(($v['peso_neto'] ?? 0) / 1000, 2) ?> Ton. (<?= number_format($v['peso_neto'] ?? 0, 0, '', '.') ?> Kg)</p>
                <p><strong>Flete Final (Neto):</strong> <span style="color: #2ecc71; font-weight:bold;"><?= formatMoney($v['total_flete_neto']) ?></span></p>
                <p><strong>Ganancia Real Chofer:</strong> <span style="color: #2ecc71; font-weight:bold;"><?= formatMoney(($v['total_flete_neto'] * $v['chofer_porcentaje']) / 100) ?></span></p>
                <p><strong>Diferencia vs Est.:</strong> <?= formatMoney($v['total_flete_neto'] - $v['total_flete_bruto']) ?></p>
            <?php endif; ?>

            <hr style="opacity: 0.1; margin: 15px 0;">
            <p><strong>Comisión Dador:</strong> 
                <?php 
                if ($v['comision_tipo'] === 'porcentaje') echo htmlspecialchars($v['comision_valor']) . '%';
                elseif ($v['comision_tipo'] === 'monto_fijo') echo formatMoney($v['comision_valor']);
                else echo 'No paga';
                
                if ($v['comision_receptor']) echo " (a " . htmlspecialchars($v['comision_receptor']) . ")";
                ?>
            </p>

            <p><strong>Pagador Flete:</strong> <?= htmlspecialchars($v['pagador_flete'] ?: 'No especificado') ?></p>

            <?php if (isset($v['factura_nro']) && $v['factura_nro']): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: var(--accent);"><strong>Comprobante:</strong> <?= htmlspecialchars($v['factura_nro']) ?></p>
                <p><strong>Fecha Factura:</strong> <?= formatDate($v['factura_fecha'] ?? null) ?></p>
            <?php endif; ?>

            <?php if (isset($v['fecha_cobro']) && $v['fecha_cobro']): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: #2ecc71;"><strong>Fecha Cobro:</strong> <?= formatDate($v['fecha_cobro']) ?></p>
            <?php endif; ?>

            <?php if ($v['estado'] === 'liquidado'): ?>
                <hr style="opacity: 0.1; margin: 15px 0;">
                <p style="color: #2ecc71; font-weight: bold;"><i class="fas fa-check-circle"></i> Saldo cerrado con el chofer.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Columna Derecha: Tablas de Movimientos -->
    <div style="display: flex; flex-direction: column; gap: 25px;">
        <div class="card">
            <h3>Gastos de Viaje (Rendición)</h3>
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th style="text-align:right">Monto</th><th style="width: 50px;"></th></tr></thead>
                <tbody>
                    <?php $totalG = 0; foreach($gastos->fetchAll() as $g): $totalG += $g['monto']; ?>
                        <tr>
                            <td><?= formatDate($g['fecha']) ?></td>
                            <td>
                                <?= strtoupper($g['tipo_gasto']) ?> <br>
                                <small style="opacity:0.7; font-size: 0.75rem;"><?= $g['pagado_por'] == 'adelanto' ? 'PAGADO CON ADELANTO' : 'PAGADO POR EMPRESA' ?></small>
                            </td>
                            <td><?= htmlspecialchars($g['descripcion']) ?></td>
                            <td style="text-align:right"><?= formatMoney($g['monto']) ?></td>
                            <td>
                                <?php if ($v['estado'] !== 'liquidado'): ?>
                                    <button onclick='editGasto(<?= json_encode($g) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                                    <button onclick='deleteGasto(<?= $g['id'] ?>)' title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3" style="text-align:right">Total Gastos:</th><th style="text-align:right"><?= formatMoney($totalG) ?></th><th></th></tr></tfoot>
            </table>
        </div>

        <div class="card">
            <h3>Adelantos entregados al Chofer</h3>
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Método</th><th style="text-align:right">Monto</th><th style="width: 50px;"></th></tr></thead>
                <tbody>
                    <?php $totalA = 0; foreach($adelantos->fetchAll() as $a): $totalA += $a['monto']; ?>
                        <tr>
                            <td><?= formatDate($a['fecha']) ?></td>
                            <td><?= strtoupper($a['metodo_pago']) ?></td>
                            <td style="text-align:right"><?= formatMoney($a['monto']) ?></td>
                            <td>
                                <?php if ($v['estado'] !== 'liquidado'): ?>
                                    <button onclick='editAdelanto(<?= json_encode($a) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                                    <button onclick='deleteAdelanto(<?= $a['id'] ?>)' title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="2" style="text-align:right">Total Adelantos:</th><th style="text-align:right"><?= formatMoney($totalA) ?></th><th></th></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Gasto -->
<div id="modal-gasto" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3 id="gasto-title">Cargar Gasto de Viaje</h3><span class="close-modal" onclick="closeModal('modal-gasto')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="gasto">
                <input type="hidden" name="id" id="gasto-id">
                <div class="form-group">
                    <label>Tipo de Gasto</label>
                    <select name="tipo" id="gasto-tipo" class="input-field">
                        <option value="combustible">Combustible</option>
                        <option value="peaje">Peaje</option>
                        <option value="viaticos">Viáticos</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pagado por</label>
                    <select name="pagado_por" id="gasto-pagado-por" class="input-field">
                        <option value="empresa">Empresa</option>
                        <option value="adelanto">Adelanto del Viaje (Chofer)</option>
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

<!-- Modal Adelanto -->
<div id="modal-adelanto" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3 id="adelanto-title">Registrar Adelanto</h3><span class="close-modal" onclick="closeModal('modal-adelanto')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="adelanto">
                <input type="hidden" name="id" id="adelanto-id">
                <div class="form-group"><label>Monto a entregar</label><input type="number" step="0.01" name="monto" id="adelanto-monto" class="input-field" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="adelanto-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="metodo" id="adelanto-metodo" class="input-field">
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

<!-- Modal Descarga (Pesaje) -->
<div id="modal-descarga" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Finalizar Descarga</h3><span class="close-modal" onclick="closeModal('modal-descarga')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="finalizar_descarga">
                <p style="margin-bottom: 20px; font-size: 0.9rem; opacity: 0.8;">Ingresa los valores del ticket de balanza de destino.</p>
                <div class="form-group"><label>Peso Bruto (Kg)</label><input type="number" step="1" min="0" name="peso_bruto" id="peso_bruto_input" class="input-field" required></div>
                <div class="form-group"><label>Peso Tara (Kg)</label><input type="number" step="1" min="0" name="peso_tara" id="peso_tara_input" class="input-field" required></div>
                <p id="preview-neto" style="font-weight: bold; color: var(--accent);"></p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary" id="btn-confirmar-descarga" disabled>Confirmar Descarga</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Documentación -->
<div id="modal-docs" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Actualizar Documentación</h3><span class="close-modal" onclick="closeModal('modal-docs')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="actualizar_docs">
                <div class="form-group"><label>CTG (Granos)</label><input type="text" name="ctg_nro" class="input-field" value="<?= htmlspecialchars($v['ctg_nro'] ?? '') ?>"></div>
                <div class="form-group"><label>Carta de Porte</label><input type="text" name="carta_porte_nro" class="input-field" value="<?= htmlspecialchars($v['carta_porte_nro'] ?? '') ?>"></div>
                <div class="form-group"><label>Otros (Remitos/Guías)</label><input type="text" name="otros_docs" class="input-field" value="<?= htmlspecialchars($v['otros_docs'] ?? '') ?>"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Facturación -->
<div id="modal-factura" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Registrar Facturación</h3><span class="close-modal" onclick="closeModal('modal-factura')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="registrar_factura">
                <div class="form-group"><label>Número de Factura</label><input type="text" name="factura_nro" class="input-field" placeholder="0001-00001234" required></div>
                <div class="form-group"><label>Fecha de Emisión</label><input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Confirmar Facturación</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Datos del Viaje -->
<div id="modal-editar-viaje" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3>Editar Información del Viaje</h3><span class="close-modal" onclick="closeModal('modal-editar-viaje')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="editar_viaje">
                <div class="form-group">
                    <label>Producto</label>
                    <input type="text" name="producto" class="input-field" value="<?= htmlspecialchars($v['producto']) ?>">
                </div>
                <div class="form-group">
                    <label>Acoplado</label>
                    <input type="text" name="acoplado" class="input-field" value="<?= htmlspecialchars($v['acoplado']) ?>">
                </div>
                <div class="form-group"><label>Tarifa por Tonelada ($)</label><input type="number" step="0.01" name="tarifa" class="input-field" value="<?= $v['tarifa_tonelada'] ?>" required></div>
                <div class="form-group"><label>% Ganancia Chofer</label><input type="number" step="0.01" name="porcentaje" class="input-field" value="<?= $v['chofer_porcentaje'] ?>" required></div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Comisión Dador</label>
                        <select name="comision_tipo" class="input-field">
                            <option value="ninguna" <?= $v['comision_tipo'] == 'ninguna' ? 'selected' : '' ?>>No Paga</option>
                            <option value="porcentaje" <?= $v['comision_tipo'] == 'porcentaje' ? 'selected' : '' ?>>Porcentaje (%)</option>
                            <option value="monto_fijo" <?= $v['comision_tipo'] == 'monto_fijo' ? 'selected' : '' ?>>Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor Comisión</label>
                        <input type="number" step="0.01" name="comision_valor" class="input-field" value="<?= $v['comision_valor'] ?>">
                    </div>
                    <div class="form-group">
                        <label>A nombre de (Comisión)</label>
                        <input type="text" name="comision_receptor" class="input-field" value="<?= htmlspecialchars($v['comision_receptor'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Pagador del Flete</label>
                    <input type="text" name="pagador_flete" class="input-field" value="<?= htmlspecialchars($v['pagador_flete'] ?? '') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Actualizar Viaje</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cobro -->
<div id="modal-cobro" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Registrar Cobro al Cliente</h3><span class="close-modal" onclick="closeModal('modal-cobro')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="registrar_cobro">
                <div class="form-group"><label>Fecha de Cobro</label><input type="date" name="fecha_cobro" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Confirmar Cobro</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevoGasto() {
    document.getElementById('gasto-title').innerText = "Cargar Gasto de Viaje";
    document.getElementById('gasto-id').value = "";
    document.querySelector('#modal-gasto form').reset();
    openModal('modal-gasto');
}
function editGasto(data) {
    document.getElementById('gasto-title').innerText = "Editar Gasto";
    document.getElementById('gasto-id').value = data.id;
    document.getElementById('gasto-tipo').value = data.tipo_gasto;
    document.getElementById('gasto-pagado-por').value = data.pagado_por;
    document.getElementById('gasto-monto').value = data.monto;
    document.getElementById('gasto-fecha').value = data.fecha;
    document.getElementById('gasto-desc').value = data.descripcion;
    openModal('modal-gasto');
}
function prepararNuevoAdelanto() {
    document.getElementById('adelanto-title').innerText = "Registrar Adelanto";
    document.getElementById('adelanto-id').value = "";
    document.querySelector('#modal-adelanto form').reset();
    openModal('modal-adelanto');
}
function editAdelanto(data) {
    document.getElementById('adelanto-title').innerText = "Editar Adelanto";
    document.getElementById('adelanto-id').value = data.id;
    document.getElementById('adelanto-monto').value = data.monto;
    document.getElementById('adelanto-fecha').value = data.fecha;
    document.getElementById('adelanto-metodo').value = data.metodo_pago;
    openModal('modal-adelanto');
}

function deleteGasto(id) {
    appConfirm("¿Estás seguro de eliminar este gasto? Esta acción no se puede deshacer.", function() {
        // Crear un formulario dinámicamente para enviar la solicitud POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Enviar al mismo script
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_gasto';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id_to_delete';
        idInput.value = id;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
    }, "Eliminar Gasto");
}

function deleteAdelanto(id) {
    appConfirm("¿Estás seguro de eliminar este adelanto? Esta acción no se puede deshacer.", function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.innerHTML = `<input type="hidden" name="action" value="delete_adelanto"><input type="hidden" name="id_to_delete" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }, "Eliminar Adelanto");
}
</script>

<script>
document.getElementById('peso_bruto_input').addEventListener('input', updateNeto);
document.getElementById('peso_tara_input').addEventListener('input', updateNeto);

const btnConfirmarDescarga = document.getElementById('btn-confirmar-descarga');

function updateNeto() {
    const bruto = parseInt(document.getElementById('peso_bruto_input').value) || 0;
    const tara = parseInt(document.getElementById('peso_tara_input').value) || 0;
    const neto = bruto - tara;

    document.getElementById('preview-neto').innerText = `Peso Neto a liquidar: ${neto} Kg.`;

    // Validación: Bruto > Tara y Neto > 0
    if (bruto > tara && neto > 0) {
        btnConfirmarDescarga.disabled = false;
        btnConfirmarDescarga.style.opacity = 1;
    } else {
        btnConfirmarDescarga.disabled = true;
        btnConfirmarDescarga.style.opacity = 0.5; // Indicar visualmente que está deshabilitado
    }
}

// Ejecutar al cargar el modal por si ya hay valores
updateNeto();
</script>