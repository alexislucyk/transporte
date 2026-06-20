<?php
/**
 * Detalle Operativo y Financiero de un Viaje
 */
$viajeId = $params[0];

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

if (!$v) die("Viaje no encontrado o acceso denegado.");

// Definir permisos de edición globales para el módulo
$is_editable_viaje = ($v['estado'] === 'en_viaje');
$is_editable_gastos_adelantos = ($v['estado'] === 'en_viaje'); // Gastos/adelantos se gestionan desde Cobranzas post-descarga

// --- CÁLCULO DE RENTABILIDAD (Para vista y liquidación) ---
$gastos_empresa_query = $pdo->prepare("SELECT SUM(monto) FROM viajes_gastos WHERE viaje_id = ? AND pagado_por = 'empresa'");
$gastos_empresa_query->execute([$viajeId]);
$gastos_empresa = $gastos_empresa_query->fetchColumn() ?: 0;

$comision_monto = ($v['comision_tipo'] === 'porcentaje') ? ($v['total_flete_neto'] * $v['comision_valor'] / 100) : ($v['comision_tipo'] === 'monto_fijo' ? $v['comision_valor'] : 0);
$chofer_monto = ($v['total_flete_neto'] * $v['chofer_porcentaje'] / 100);
$ganancia_neta_empresa = $v['total_flete_neto'] - $comision_monto - $chofer_monto - $gastos_empresa;

// 2. Procesar Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bloqueo de seguridad: Si el viaje ya fue cobrado o liquidado, no procesar modificaciones
    if (!$is_editable_viaje && (isset($_POST['action']) && ($_POST['action'] === 'editar_viaje' || $_POST['action'] === 'actualizar_docs'))) {
        $error = "El viaje ya está descargado y no permite modificaciones de datos principales o documentos desde aquí.";
    } else {

    // --- Registrar Descarga (Cierre de kilos) ---
    if (isset($_POST['action']) && $_POST['action'] === 'finalizar_descarga') {
        $bruto_kg = (int)$_POST['peso_bruto'];
        $tara_kg = (int)$_POST['peso_tara']; // Asegurarse de que sea int
        $neto_kg = max(0, $bruto_kg - $tara_kg);
        $flete_neto = ($neto_kg / 1000) * $v['tarifa_tonelada']; // Convertir Kg a Ton para el cálculo del flete
        
        try {
            // Actualizamos también el peso_neto (columna virtual o física según versión MySQL)
            $sql = "UPDATE viajes SET peso_bruto=?, peso_tara=?, total_flete_neto=?, estado='descargado' WHERE id=?"; 
            $pdo->prepare($sql)->execute([$bruto_kg, $tara_kg, $flete_neto, $viajeId]);
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

    // --- Editar Datos del Viaje ---
    if (isset($_POST['action']) && $_POST['action'] === 'editar_viaje') {
        try {
            $sql = "UPDATE viajes SET cliente_id=?, chofer_id=?, vehiculo_id=?, acoplado=?, origen=?, destino=?, producto=?, fecha_carga=?, tarifa_tonelada=?, chofer_porcentaje=?, comision_tipo=?, comision_valor=?, comisionista_id=?, pagador_id=?, ctg_nro=?, carta_porte_nro=?, otros_docs=? WHERE id=?";
            $pdo->prepare($sql)->execute([
                $_POST['cliente_id'], $_POST['chofer_id'], $_POST['vehiculo_id'], $_POST['acoplado'],
                $_POST['origen'], $_POST['destino'], $_POST['producto'], $_POST['fecha_carga'],
                $_POST['tarifa'], $_POST['porcentaje'], $_POST['comision_tipo'], $_POST['comision_valor'],
                $_POST['comisionista_id'] ?: null, $_POST['pagador_id'] ?: null,
                $_POST['ctg_nro'], $_POST['carta_porte_nro'], $_POST['otros_docs'],
                $viajeId
            ]);
            // Si el viaje ya estaba descargado, debemos recalcular el flete neto con la nueva tarifa
            // También se recalcula el flete bruto por si la tarifa o peso estimado (que no se edita aquí) cambió.
            // Para mantener la consistencia, recargamos el flete bruto si el estado es en_viaje
            $stmt_recalc = $pdo->prepare("SELECT peso_bruto, peso_tara, total_flete_bruto FROM viajes WHERE id = ?");
            $stmt_recalc->execute([$viajeId]);
            $current_data = $stmt_recalc->fetch();

            $new_total_flete_bruto = ($current_data['peso_bruto'] / 1000) * $_POST['tarifa']; // Asumiendo que peso_bruto es el "peso_estimado" inicial

            if ($v['estado'] === 'en_viaje') {
                 $pdo->prepare("UPDATE viajes SET total_flete_bruto = ? WHERE id = ?")->execute([$new_total_flete_bruto, $viajeId]);
            } else {
                 $pdo->prepare("UPDATE viajes SET total_flete_neto = (peso_neto / 1000) * ? WHERE id = ?")->execute([$_POST['tarifa'], $viajeId]);
            }
            header("Location: " . $base_path . "viajes/detalle/" . $viajeId); exit;
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }

    // --- Procesar Eliminaciones ---
    if ($is_editable_gastos_adelantos && isset($_POST['action']) && ($_POST['action'] === 'delete_gasto' || $_POST['action'] === 'delete_adelanto')) {
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
    if ($is_editable_gastos_adelantos && isset($_POST['movimiento'])) {
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
}

// 3. Obtener listados
$gastos = $pdo->prepare("SELECT * FROM viajes_gastos WHERE viaje_id = ?"); $gastos->execute([$viajeId]);
$adelantos = $pdo->prepare("SELECT * FROM viajes_adelantos WHERE viaje_id = ?"); $adelantos->execute([$viajeId]);

// 4. Obtener listas para los selectores de edición
$stmt_pag = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1"); $stmt_pag->execute([$active_company_id]);
$lista_pagadores = $stmt_pag->fetchAll();
$stmt_com = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1"); $stmt_com->execute([$active_company_id]);
$lista_comisionistas = $stmt_com->fetchAll();

// Nuevas listas para el modal de edición de viaje
$stmt_cli_all = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comercial = 1"); $stmt_cli_all->execute([$active_company_id]);
$lista_comerciales = $stmt_cli_all->fetchAll();

$stmt_cho_all = $pdo->prepare("SELECT id, nombre, apellido FROM choferes WHERE transportista_id = ? AND activo = 1"); $stmt_cho_all->execute([$active_company_id]);
$lista_choferes = $stmt_cho_all->fetchAll();

$stmt_veh_all = $pdo->prepare("SELECT id, dominio, chofer_id, acoplado FROM vehiculos WHERE transportista_id = ?"); $stmt_veh_all->execute([$active_company_id]);
$lista_camiones = $stmt_veh_all->fetchAll();
?>

<div style="margin-bottom: 25px;">
    <a href="viajes" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; display: inline-block; margin-bottom: 10px;">
        <i class="fas fa-arrow-left"></i> Volver a Operativa
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Viaje #<?= $v['id'] ?> <small style="font-size: 1rem; opacity: 0.6;">(<?= strtoupper($v['estado']) ?>)</small></h1>
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

<?php if (isset($error) && $error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
    </div>
<?php endif; ?>

 <div class="responsive-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
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
                <?php
                $peso_neto_real = $v['peso_neto'] ?? 0;
                $flete_bruto_estimado = $v['total_flete_bruto'] ?? 0;
                $tarifa_tonelada = $v['tarifa_tonelada'] ?? 0;

                $peso_estimado_kg = 0;
                if ($tarifa_tonelada > 0) {
                    $peso_estimado_toneladas = $flete_bruto_estimado / $tarifa_tonelada;
                    $peso_estimado_kg = $peso_estimado_toneladas * 1000;
                }
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

                if ($v['comisionista_nombre']) echo " (a " . htmlspecialchars($v['comisionista_nombre']) . ")";
                ?>
            </p>

            <p><strong>Pagador Flete:</strong> <?= htmlspecialchars($v['pagador_nombre'] ?: 'No especificado') ?></p>
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

    <!-- Columna Derecha: Tablas de Movimientos -->
    <div style="display: flex; flex-direction: column; gap: 25px;">
        <div class="card">
            <h3>Gastos de Viaje (Rendición)</h3>
            <div class="table-container">
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
                                <?php if ($is_editable_gastos_adelantos): ?>
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
                    </div>
        <div class="card">
            <h3>Adelantos entregados al Chofer</h3>
            <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Método</th><th style="text-align:right">Monto</th><th style="width: 50px;"></th></tr></thead>
                <tbody>
                    <?php $totalA = 0; foreach($adelantos->fetchAll() as $a): $totalA += $a['monto']; ?>
                        <tr>
                            <td><?= formatDate($a['fecha']) ?></td>
                            <td><?= strtoupper($a['metodo_pago']) ?></td>
                            <td style="text-align:right"><?= formatMoney($a['monto']) ?></td>
                            <td>
                                <?php if ($is_editable_gastos_adelantos): ?>
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
                </div>

<!-- Modal Gasto -->
<div id="modal-gasto" class="modal">
    <div class="modal-content" style="max-width: 500px;">
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

<!-- Modal Adelanto -->
<div id="modal-adelanto" class="modal">
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
    <div class="modal-content" style="max-width: 500px;">
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
    <div class="modal-content" style="max-width: 500px;">
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

<!-- Modal Editar Datos del Viaje -->
<div id="modal-editar-viaje" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>Editar Información del Viaje</h3><span class="close-modal" onclick="closeModal('modal-editar-viaje')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="editar_viaje">
                <div class="form-grid">
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
                                <option value="<?= $cam['id'] ?>" data-chofer="<?= $cam['chofer_id'] ?>" data-acoplado="<?= htmlspecialchars($cam['acoplado']) ?>" <?= $v['vehiculo_id'] == $cam['id'] ? 'selected' : '' ?>><?= $cam['dominio'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Acoplado</label>
                        <input type="text" name="acoplado" id="edit-aco-input" class="input-field" value="<?= htmlspecialchars($v['acoplado']) ?>" placeholder="Patente acoplado">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Chofer</label>
                        <select name="chofer_id" id="edit-ch-select" class="input-field" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($lista_choferes as $ch): ?>
                                <option value="<?= $ch['id'] ?>" <?= $v['chofer_id'] == $ch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['apellido'] . " " . $ch['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Producto</label>
                        <input type="text" name="producto" class="input-field" value="<?= htmlspecialchars($v['producto']) ?>" placeholder="Ej: Soja, Maíz, Arena">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Origen</label><input type="text" name="origen" class="input-field" value="<?= htmlspecialchars($v['origen']) ?>" required></div>
                    <div class="form-group"><label>Destino</label><input type="text" name="destino" class="input-field" value="<?= htmlspecialchars($v['destino']) ?>" required></div>
                    <div class="form-group"><label>Fecha Carga</label><input type="date" name="fecha_carga" class="input-field" value="<?= htmlspecialchars($v['fecha_carga']) ?>" required></div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Tarifa por Tonelada ($)</label><input type="number" step="0.01" name="tarifa" class="input-field" value="<?= $v['tarifa_tonelada'] ?>" required></div>
                    <div class="form-group"><label>% Ganancia Chofer</label><input type="number" step="0.01" name="porcentaje" class="input-field" value="<?= $v['chofer_porcentaje'] ?>" required></div>
                </div>

                <div class="form-grid">
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
                        <label>Comisionista</label>
                        <select name="comisionista_id" class="input-field">
                            <option value="">-- Sin comisión --</option>
                            <?php foreach($lista_comisionistas as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $v['comisionista_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>CTG (Granos)</label><input type="text" name="ctg_nro" class="input-field" value="<?= htmlspecialchars($v['ctg_nro'] ?? '') ?>" placeholder="Nro de CTG"></div>
                    <div class="form-group"><label>Carta de Porte</label><input type="text" name="carta_porte_nro" class="input-field" value="<?= htmlspecialchars($v['carta_porte_nro'] ?? '') ?>" placeholder="Nro CP-E"></div>
                    <div class="form-group"><label>Otros Documentos</label><input type="text" name="otros_docs" class="input-field" value="<?= htmlspecialchars($v['otros_docs'] ?? '') ?>" placeholder="Remitos / Guías"></div>
                </div>
                <div class="form-group">
                    <label>Pagador del Flete</label>
                    <select name="pagador_id" class="input-field">
                        <option value="">-- No especificado --</option>
                        <?php foreach($lista_pagadores as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $v['pagador_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Actualizar Viaje</button>
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

<script>
// ... existing code ...

function sugerirChoferEdit(select) {
    const choferId = select.options[select.selectedIndex].getAttribute('data-chofer');
    const acopladoDato = select.options[select.selectedIndex].getAttribute('data-acoplado');

    if (choferId) {
        document.getElementById('edit-ch-select').value = choferId;
    }
    if (acopladoDato) {
        document.getElementById('edit-aco-input').value = acopladoDato;
    } else {
        document.getElementById('edit-aco-input').value = ""; // Clear if no acoplado
    }
}
</script>


