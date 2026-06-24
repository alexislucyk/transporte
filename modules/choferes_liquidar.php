<?php
/**
 * Módulo de Cobranzas y Liquidaciones - Trans Cargo Hub
 * Centro de control financiero post-descarga.
 */
$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- API INTERNA PARA DETALLES (AJAX) ---
if (isset($_GET['get_viaje_info'])) {
    $id = $_GET['get_viaje_info'];
    if (ob_get_length()) ob_clean();

    try {
        $stmtViaje = $pdo->prepare("
            SELECT v.*, 
                CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre, 
                cli.razon_social as cliente_razon_social, 
                ccom.razon_social as comisionista_nombre,
                (SELECT COALESCE(SUM(monto),0) FROM viajes_adelantos WHERE viaje_id = v.id AND activo = 1) as total_adelantos,
                (SELECT COALESCE(SUM(monto),0) FROM viajes_gastos WHERE viaje_id = v.id AND pagado_por = 'adelanto' AND activo = 1) as gastos_rendidos
            FROM viajes v 
            JOIN choferes ch ON v.chofer_id = ch.id 
            LEFT JOIN clientes cli ON v.cliente_id = cli.id
            LEFT JOIN clientes ccom ON v.comisionista_id = ccom.id
            WHERE v.id = ? AND v.transportista_id = ?
        ");
        $stmtViaje->execute([$id, $active_company_id]);
        $viaje_data = $stmtViaje->fetch();

        if (!$viaje_data) throw new Exception("Viaje no encontrado.");
        
        $stmtG = $pdo->prepare("SELECT id, fecha, tipo_gasto, monto, pagado_por, descripcion FROM viajes_gastos WHERE viaje_id = ? AND activo = 1 ORDER BY fecha ASC");
        $stmtG->execute([$id]);
        $gastos = $stmtG->fetchAll();
        
        $stmtA = $pdo->prepare("SELECT id, fecha, monto, metodo_pago FROM viajes_adelantos WHERE viaje_id = ? AND activo = 1 ORDER BY fecha ASC");
        $stmtA->execute([$id]);
        $adelantos = $stmtA->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode(['viaje' => $viaje_data, 'gastos' => $gastos, 'adelantos' => $adelantos]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Registrar Facturación (DETALLADA) - modelo A: factura puede contener varios viajes
        if (isset($_POST['action']) && $_POST['action'] === 'registrar_factura_detallada') {
            $facturaNro = trim($_POST['factura_nro'] ?? '');
            $facturaFecha = $_POST['factura_fecha'] ?? null;
            $facturaClienteId = $_POST['factura_cliente_id'] ?? null;
            $transportistaId = $_POST['factura_transportista_id'] ?? $active_company_id;
            $viajeIdsCsv = $_POST['viaje_ids_csv'] ?? '';

            if ($facturaNro === '' || !$facturaFecha || !$viajeIdsCsv) {
                throw new Exception('Faltan datos para registrar la factura.');
            }

            $viajeIds = array_values(array_filter(array_map('intval', explode(',', $viajeIdsCsv))));
            if (empty($viajeIds)) throw new Exception('No se seleccionaron viajes para la factura.');

            $ivaOn = ($_POST['factura_iva_onoff'] ?? 'si') === 'si';
            $ivaPct = (float)($_POST['factura_iva_pct'] ?? 0);

            $ivaMonto = (float)($_POST['factura_iva_monto'] ?? 0);
            $retencionesTotal = (float)($_POST['factura_retenciones_total'] ?? 0);

            $fleteNetoTotal = (float)($_POST['factura_flete_neto_total'] ?? 0);
            $totalFactura = (float)($_POST['factura_total'] ?? 0);

            $retencionesJson = $_POST['retenciones_json'] ?? '[]';
            $retencionesArr = json_decode($retencionesJson, true);
            if (!is_array($retencionesArr)) $retencionesArr = [];

            $pdo->beginTransaction();
            try {
                // Asegurar cabecera factura (una cabecera por factura_nro/fecha/transportista)
                // Si se repite, lo re-creamos/actualizamos limpiando el detalle.
                $stmtF = $pdo->prepare("SELECT id FROM facturas_fletes WHERE transportista_id = ? AND factura_nro = ? AND factura_fecha = ? LIMIT 1");
                $stmtF->execute([$transportistaId, $facturaNro, $facturaFecha]);
                $facturaId = $stmtF->fetchColumn();

                if (!$facturaId) {
                    $stmtIns = $pdo->prepare("INSERT INTO facturas_fletes (transportista_id, cliente_id, factura_nro, factura_fecha, moneda, flete_neto_total, iva_monto, retenciones_total, otros_descuentos_total, total_factura) VALUES (?, ?, ?, ?, 'ARS', ?, ?, ?, 0, ?)");
                    $stmtIns->execute([
                        $transportistaId,
                        $facturaClienteId ?: null,
                        $facturaNro,
                        $facturaFecha,
                        $fleteNetoTotal,
                        $ivaMonto,
                        $retencionesTotal,
                        $totalFactura
                    ]);
                    $facturaId = $pdo->lastInsertId();
                } else {
                    // Recalcular/actualizar cabecera
                    $pdo->prepare("UPDATE facturas_fletes SET cliente_id=?, flete_neto_total=?, iva_monto=?, retenciones_total=?, total_factura=? WHERE id=?")
                        ->execute([$facturaClienteId ?: null, $fleteNetoTotal, $ivaMonto, $retencionesTotal, $totalFactura, $facturaId]);
                    // Resetear detalle para evitar duplicados
                    $pdo->prepare("DELETE FROM facturas_fletes_detalle WHERE factura_id=?")->execute([$facturaId]);
                    // Resetear asociaciones viajes
                    $pdo->prepare("DELETE FROM facturas_fletes_viajes WHERE factura_id=?")->execute([$facturaId]);
                }

                // Insertar detalle retenciones/IVA (modelo simplificado: cada fila es un ítem)
                // Si el usuario no agregó filas, dejamos solo detalle vacío.
                foreach ($retencionesArr as $r) {
                    $tipo = $r['tipo'] ?? 'OTRA_RETENCION';
                    $desc = $r['descripcion'] ?? null;
                    $base = (float)($r['base'] ?? 0);
                    $porc = isset($r['porcentaje']) ? (float)$r['porcentaje'] : null;
                    $monto = (float)($r['monto'] ?? 0);

                    $pdo->prepare("INSERT INTO facturas_fletes_detalle (factura_id, tipo, descripcion, base_monto, porcentaje, monto) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$facturaId, $tipo, $desc, $base, $porc, $monto]);
                }

                // Asociar viajes a la factura y marcar facturado
                $stmtV = $pdo->prepare("UPDATE viajes SET factura_nro=?, factura_fecha=?, estado='facturado' WHERE id=? AND transportista_id=?");
                $stmtAssoc = $pdo->prepare("INSERT IGNORE INTO facturas_fletes_viajes (factura_id, viaje_id) VALUES (?, ?)");
                foreach ($viajeIds as $vid) {
                    $stmtV->execute([$facturaNro, $facturaFecha, $vid, $transportistaId]);
                    $stmtAssoc->execute([$facturaId, $vid]);
                }

                $pdo->commit();
                $mensaje = 'Factura registrada y detalle guardado correctamente.';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            // Nota: no ejecutar la ruta vieja registrar_factura.
        }

        // 1. Registrar Facturación (LEGADO: 1 viaje por factura)
        if (isset($_POST['action']) && $_POST['action'] === 'registrar_factura') {
            $sql = "UPDATE viajes SET factura_nro=?, factura_fecha=?, estado='facturado' WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['factura_nro'], $_POST['factura_fecha'], $_POST['viaje_id']]);
            $mensaje = "Factura registrada correctamente.";
        }


        // 2. Registrar Cobro
        if (isset($_POST['action']) && $_POST['action'] === 'registrar_cobro') {
            $sql = "UPDATE viajes SET fecha_cobro=?, estado='cobrado' WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['fecha_cobro'], $_POST['viaje_id']]);
            $mensaje = "Cobro registrado exitosamente.";
        }

        // 3. Acreditar Ganancia Chofer
        if (isset($_POST['action']) && $_POST['action'] === 'acreditar_chofer') {
            $viajeId = $_POST['viaje_id'];

            // IMPORTANTE:
            // Este endpoint NO debe cambiar el campo `estado`.
            // Si se marca como 'liquidado' antes de registrar el cobro,
            // el viaje puede dejar de aparecer en los listados de cobranzas.
            // (Se evita cualquier UPDATE a `estado` acá.)


            $stmtV = $pdo->prepare("SELECT v.*, (SELECT COALESCE(SUM(monto),0) FROM viajes_adelantos WHERE viaje_id = v.id AND activo = 1) as total_adelantos, (SELECT COALESCE(SUM(monto),0) FROM viajes_gastos WHERE viaje_id = v.id AND pagado_por = 'adelanto' AND activo = 1) as gastos_rendidos FROM viajes v WHERE v.id = ?");
            $stmtV->execute([$viajeId]);
            $v = $stmtV->fetch();

            $ganancia_total = ($v['total_flete_neto'] * $v['chofer_porcentaje']) / 100;
            $residual_adelanto = $v['total_adelantos'] - $v['gastos_rendidos'];
            $monto_neto = $ganancia_total - $residual_adelanto;
            $detalle = "Liquidación Flete Viaje #{$v['id']} (CP: {$v['carta_porte_nro']}). Ganancia: " . formatMoney($ganancia_total) . " - Sobrante Adelanto: " . formatMoney($residual_adelanto);

            $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, 'liquidacion', ?)")
                ->execute([$v['chofer_id'], date('Y-m-d'), $monto_neto, $detalle]);

            $pdo->prepare("UPDATE viajes SET acreditado_chofer = 1 WHERE id = ?")->execute([$viajeId]);

// No cerrar el viaje aquí.
            // El estado 'liquidado' se debería aplicar recién cuando el flete esté cobrado.
            $mensaje = "Saldo acreditado en la Cta Cte del chofer.";

            // Si por lógica de negocio antes se marcaba 'liquidado', ya no corresponde.
            // (Se evita que el viaje desaparezca de la grilla de cobros.)

        }


        // 4. Pagar Comisión
        if (isset($_POST['action']) && $_POST['action'] === 'pagar_comision') {
            $viajeId = $_POST['viaje_id'];
            $stmtV = $pdo->prepare("SELECT * FROM viajes WHERE id = ?");
            $stmtV->execute([$viajeId]);
            $v = $stmtV->fetch();

            $monto_c = ($v['comision_tipo'] === 'porcentaje') ? ($v['total_flete_neto'] * $v['comision_valor'] / 100) : $v['comision_valor'];
            $detalle = "Pago Comisión Viaje #{$v['id']} (CP: {$v['carta_porte_nro']})";

            $pdo->prepare("INSERT INTO comisionista_pagos (cliente_id, fecha, monto, detalle) VALUES (?, ?, ?, ?)")
                ->execute([$v['comisionista_id'], date('Y-m-d'), $monto_c, $detalle]);

            $pdo->prepare("UPDATE viajes SET comision_pagada = 1 WHERE id = ?")->execute([$viajeId]);
            $mensaje = "Comisión pagada al intermediario.";
        }

        // 5. Editar Viaje desde Cobranzas
        if (isset($_POST['action']) && $_POST['action'] === 'editar_viaje_liq') {
            $sql = "UPDATE viajes SET chofer_porcentaje=?, acoplado=?, producto=?, tarifa_tonelada=?, comision_tipo=?, comision_valor=?, comisionista_id=?, pagador_id=? WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['porcentaje'], $_POST['acoplado'], $_POST['producto'], $_POST['tarifa'], $_POST['comision_tipo'], $_POST['comision_valor'], $_POST['comisionista_id'] ?: null, $_POST['pagador_id'] ?: null, $_POST['viaje_id']]);
            $pdo->prepare("UPDATE viajes SET total_flete_neto = (peso_neto / 1000) * tarifa_tonelada WHERE id = ? AND estado != 'en_viaje'")->execute([$_POST['viaje_id']]);
            $mensaje = "Datos operativos actualizados.";
        }

        // 6. Gastos y Adelantos
        if (isset($_POST['movimiento'])) {
            $vId = $_POST['viaje_id_modal'];
            if ($_POST['movimiento'] === 'gasto') {
                if (!empty($_POST['id'])) {
                    $pdo->prepare("UPDATE viajes_gastos SET tipo_gasto=?, monto=?, descripcion=?, pagado_por=?, fecha=? WHERE id=?")->execute([$_POST['tipo'], $_POST['monto'], $_POST['desc'], $_POST['pagado_por'], $_POST['fecha'], $_POST['id']]);
                } else {
                    $pdo->prepare("INSERT INTO viajes_gastos (viaje_id, tipo_gasto, monto, descripcion, pagado_por, fecha) VALUES (?, ?, ?, ?, ?, ?)")->execute([$vId, $_POST['tipo'], $_POST['monto'], $_POST['desc'], $_POST['pagado_por'], $_POST['fecha']]);
                }
            } else {
                if (!empty($_POST['id'])) {
                    $pdo->prepare("UPDATE viajes_adelantos SET monto=?, fecha=?, metodo_pago=? WHERE id=?")->execute([$_POST['monto'], $_POST['fecha'], $_POST['metodo'], $_POST['id']]);
                } else {
                    $pdo->prepare("INSERT INTO viajes_adelantos (viaje_id, monto, fecha, metodo_pago) VALUES (?, ?, ?, ?)")->execute([$vId, $_POST['monto'], $_POST['fecha'], $_POST['metodo']]);
                }
            }
            $mensaje = "Movimiento guardado.";
        }

        // 7. Eliminaciones
        if (isset($_POST['action']) && ($_POST['action'] === 'delete_gasto' || $_POST['action'] === 'delete_adelanto')) {
            $table = ($_POST['action'] === 'delete_gasto') ? 'viajes_gastos' : 'viajes_adelantos';
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$_POST['id_to_delete']]);
            $mensaje = "Registro eliminado.";
        }

    } catch (Exception $e) { $error = $e->getMessage(); }
}

// --- OBTENER LISTADOS ---
$active_company_id = $_SESSION['active_company_id'];

// Viajes pendientes de cierre financiero (MOSTRAR HASTA QUE SE REGISTRE EL COBRO)
// Se prioriza que el viaje no “desaparezca” en cobranzas si ya se acreditó/pagó chofer y comisión.
$pendientes_cierre = $pdo->prepare(
    "SELECT v.*, c.razon_social as cliente
     FROM viajes v
     JOIN clientes c ON v.cliente_id = c.id
     WHERE v.transportista_id = ?
       AND v.estado IN ('descargado', 'facturado', 'cobrado')
       AND (v.fecha_cobro IS NULL OR v.estado != 'cobrado')
     ORDER BY v.fecha_carga ASC"
);
$pendientes_cierre->execute([$active_company_id]);
$lista_cierre = $pendientes_cierre->fetchAll();

// Facturas pendientes de cobro
$pendientes_cobro = $pdo->prepare("SELECT v.*, c.razon_social as cliente FROM viajes v JOIN clientes c ON v.cliente_id = c.id WHERE v.transportista_id = ? AND v.estado = 'facturado' ORDER BY v.factura_fecha ASC");
$pendientes_cobro->execute([$active_company_id]);
$lista_cobro = $pendientes_cobro->fetchAll();

// Historial de rentabilidad
$rentabilidad = $pdo->prepare("SELECT v.*, c.razon_social as cliente, (SELECT COALESCE(SUM(monto),0) FROM viajes_gastos WHERE viaje_id = v.id AND pagado_por = 'empresa' AND activo = 1) as total_gastos_empresa FROM viajes v JOIN clientes c ON v.cliente_id = c.id WHERE v.transportista_id = ? AND v.estado IN ('cobrado', 'liquidado', 'descargado', 'facturado') ORDER BY v.fecha_cobro DESC LIMIT 10");
$rentabilidad->execute([$active_company_id]);
$lista_rentabilidad = $rentabilidad->fetchAll();

// Selectores
$lista_pagadores = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1 ORDER BY razon_social ASC");
$lista_pagadores->execute([$active_company_id]);
$lista_comisionistas = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1 ORDER BY razon_social ASC");
$lista_comisionistas->execute([$active_company_id]);
?>

<h1>Gestión de Cobranzas y Liquidaciones</h1>
<p>Central de movimientos post-viaje.</p>

<?php if ($mensaje): ?><div class="card" style="background:#d4edda; color:#155724; border:1px solid #c3e6cb; margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= $mensaje ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div><?php endif; ?>

<div class="card" style="margin-bottom: 30px; border-top: 4px solid var(--accent);">
    <h3>Viajes Pendientes de Cierre</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>CP / Remito</th><th>Cliente</th><th style="text-align:right">Ganancia Chofer</th><th style="text-align:right">Comisión</th><th style="text-align:center">Acción</th></tr>
            </thead>
            <tbody>
                <?php foreach($lista_cierre as $p): $gan_est = ($p['total_flete_neto'] * $p['chofer_porcentaje']) / 100; ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars($p['carta_porte_nro'] ?: ($p['otros_docs'] ?: 'S/D')) ?></span><br><small><?= formatDate($p['fecha_carga']) ?></small></td>
                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                    <td style="text-align:right;"><?= $p['acreditado_chofer'] ? '<span class="badge badge-success">ACREDITADO</span>' : formatMoney($gan_est) ?></td>
                    <td style="text-align:right;"><?php if(!$p['comisionista_id']): echo '-'; elseif($p['comision_pagada']): echo '<span class="badge badge-success">PAGADA</span>'; else: echo formatMoney(($p['comision_tipo'] == 'porcentaje' ? ($p['total_flete_neto'] * $p['comision_valor'] / 100) : $p['comision_valor'])); endif; ?></td>
                    <td style="text-align:center;"><button onclick="abrirModalLiquidacion(<?= $p['id'] ?>)" class="btn-primary" style="padding:5px 12px; font-size:0.85rem;"><i class="fas fa-calculator"></i> Liquidar</button></td>
                </tr>
                <?php endforeach; if(empty($lista_cierre)): ?><tr><td colspan="5" style="text-align:center; opacity:0.6;">Sin cierres pendientes.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-bottom: 30px;">
    <h3>Fletes Facturados Pendientes de Cobro</h3>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>CP/Remito</th><th>Cliente</th><th>Emisión</th><th>Factura</th><th style="text-align:right">Importe</th><th style="text-align:center">Acción</th></tr></thead>
            
            <tbody>
                <?php foreach($lista_cobro as $c): ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars($c['carta_porte_nro'] ?: ($c['otros_docs'] ?: 'S/D')) ?></span></td>
                    <td><?= htmlspecialchars($c['cliente']) ?></td>
                    <td><?= formatDate($c['factura_fecha']) ?></td>
                    <td><strong style="color:var(--accent)"><?= $c['factura_nro'] ?></strong></td>
                    <td style="text-align:right; font-weight:bold;"><?= formatMoney($c['total_flete_neto']) ?></td>
                    <td style="text-align:center;"><button onclick="registrarCobroManual(<?= $c['id'] ?>, '<?= $c['factura_nro'] ?>')" class="btn-primary" style="background:#2ecc71; padding:5px 12px; font-size:0.85rem;"><i class="fas fa-money-bill-wave"></i> Cobrado</button></td>
                </tr>
                <?php endforeach; if(empty($lista_cobro)): ?><tr><td colspan="6" style="text-align:center; opacity:0.6;">Sin facturas pendientes de cobro.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Liquidación Detallada -->
<div id="modal-liquidacion" class="modal" style="z-index: 1001;">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header"><h3>Cierre Financiero de Viaje</h3><span class="close-modal" onclick="closeModal('modal-liquidacion')">&times;</span></div>
        <div class="modal-body">
            <div class="form-grid" style="margin-bottom: 20px;">
                <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #34495e;">
                    <h4 style="margin-top:0"><i class="fas fa-info-circle"></i> Datos Operativos</h4>
                    <p id="liq_viaje_resumen_text" style="font-size:0.9rem;"></p>
                    <button onclick="prepararEditarViajeLiq()" class="btn-primary" style="width:100%; background:#34495e;"><i class="fas fa-edit"></i> Editar Viaje / Comisiones</button>
                </div>
                <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #2ecc71;">
                    <h4 style="margin-top:0"><i class="fas fa-user"></i> Chofer</h4>
                    <p id="liq_chofer_info"></p><div id="btn_area_chofer"></div>
                </div>
                <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #f39c12;">
                    <h4 style="margin-top:0"><i class="fas fa-handshake"></i> Comisión</h4>
                    <p id="liq_comision_info"></p><div id="btn_area_comision"></div>
                </div>
            </div>
            
            <div class="card" style="margin-bottom: 20px;">
                <h4>Rendición de Fondos (Chofer)</h4>
                <div id="liq_detalles_fondos" style="font-size:0.9rem; margin-bottom:15px; padding:10px; background:rgba(0,0,0,0.02); border-radius:5px;"></div>
                <div class="form-grid" style="margin-bottom: 15px;">
                    <button onclick="prepararNuevoGastoLiq()" class="btn-primary" style="background:#e67e22;"><i class="fas fa-gas-pump"></i> Cargar Gasto</button>
                    <button onclick="prepararNuevoAdelantoLiq()" class="btn-primary" style="background:#3498db;"><i class="fas fa-hand-holding-usd"></i> Dar Adelanto</button>
                </div>
                <div id="liq_gastos_table_container"></div>
                <div id="liq_adelantos_table_container" style="margin-top:15px;"></div>
            </div>

            <div class="form-grid">
                <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #3498db;">
                    <h4 style="margin-top:0"><i class="fas fa-file-invoice"></i> Facturación</h4>
                    <p id="liq_factura_info"></p><div id="btn_area_factura"></div>
                </div>
                <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #2ecc71;">
                    <h4 style="margin-top:0"><i class="fas fa-money-bill-wave"></i> Cobro</h4>
                    <p id="liq_cobro_info"></p><div id="btn_area_cobro"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn-secondary" onclick="closeModal('modal-liquidacion')">Cerrar</button></div>
    </div>
</div>

<!-- Modal Editar Viaje -->
<div id="modal-editar-viaje-liq" class="modal" style="z-index: 1002;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>Editar Información del Viaje</h3><span class="close-modal" onclick="closeModal('modal-editar-viaje-liq')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="editar_viaje_liq"><input type="hidden" name="viaje_id" id="ed-liq-viaje-id">
                <div class="form-grid">
                    <div class="form-group"><label>Producto</label><input type="text" name="producto" id="ed-liq-producto" class="input-field"></div>
                    <div class="form-group"><label>Acoplado</label><input type="text" name="acoplado" id="ed-liq-acoplado" class="input-field"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Tarifa x Ton ($)</label><input type="number" step="0.01" name="tarifa" id="ed-liq-tarifa" class="input-field" required></div>
                    <div class="form-group"><label>% Chofer</label><input type="number" step="0.01" name="porcentaje" id="ed-liq-porcentaje" class="input-field" required></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Comisión Tipo</label><select name="comision_tipo" id="ed-liq-com-tipo" class="input-field"><option value="ninguna">No Paga</option><option value="porcentaje">Porcentaje (%)</option><option value="monto_fijo">Monto Fijo ($)</option></select></div>
                    <div class="form-group"><label>Valor Comisión</label><input type="number" step="0.01" name="comision_valor" id="ed-liq-com-valor" class="input-field"></div>
                    <div class="form-group"><label>Comisionista</label><select name="comisionista_id" id="ed-liq-com-id" class="input-field"><option value="">-- Sin comisión --</option><?php foreach($lista_comisionistas as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Pagador del Flete</label><select name="pagador_id" id="ed-liq-pag-id" class="input-field"><option value="">-- No especificado --</option><?php foreach($lista_pagadores as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['razon_social']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn-primary">Guardar Cambios</button></div>
        </form>
    </div>
</div>

<!-- Modal Gasto -->
<div id="modal-gasto-liq" class="modal" style="z-index: 1002;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3 id="gasto-liq-title">Gasto de Viaje</h3><span class="close-modal" onclick="closeModal('modal-gasto-liq')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="gasto"><input type="hidden" name="viaje_id_modal" id="gasto-liq-viaje-id"><input type="hidden" name="id" id="gasto-liq-id">
                <div class="form-group"><label>Tipo de Gasto</label><select name="tipo" id="gasto-liq-tipo" class="input-field"><option value="combustible">Combustible</option><option value="peaje">Peaje</option><option value="viaticos">Viáticos</option><option value="otros">Otros</option></select></div>
                <div class="form-group"><label>Pagado por</label><select name="pagado_por" id="gasto-liq-pagado-por" class="input-field"><option value="empresa">Empresa</option><option value="adelanto">Adelanto (Chofer)</option><option value="descuento_flete">Descuento Flete</option></select></div>
                <div class="form-group"><label>Monto</label><input type="number" step="0.01" name="monto" id="gasto-liq-monto" class="input-field" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="gasto-liq-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Descripción</label><input type="text" name="desc" id="gasto-liq-desc" class="input-field"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn-primary">Guardar Gasto</button></div>
        </form>
    </div>
</div>

<!-- Modal Adelanto -->
<div id="modal-adelanto-liq" class="modal" style="z-index: 1002;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header"><h3 id="adelanto-liq-title">Entregar Adelanto</h3><span class="close-modal" onclick="closeModal('modal-adelanto-liq')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="movimiento" value="adelanto"><input type="hidden" name="viaje_id_modal" id="adelanto-liq-viaje-id"><input type="hidden" name="id" id="adelanto-liq-id">
                <div class="form-group"><label>Monto</label><input type="number" step="0.01" name="monto" id="adelanto-liq-monto" class="input-field" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="adelanto-liq-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Método</label><select name="metodo" id="adelanto-liq-metodo" class="input-field"><option value="Efectivo">Efectivo</option><option value="Transferencia">Transferencia</option></select></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn-primary">Guardar Adelanto</button></div>
        </form>
    </div>
</div>

<!-- Modal Factura -->
<div id="modal-factura" class="modal" style="z-index: 1003;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header"><h3>Registrar Facturación</h3><span class="close-modal" onclick="closeModal('modal-factura')">&times;</span></div>

        <form method="POST" id="form-factura-detallada">
            <div class="modal-body">
                <!-- Nota: ahora guardamos facturas detalladas (IVA/retenciones) a nivel cabecera -->
                <input type="hidden" name="action" value="registrar_factura_detallada">

                <!-- Se mantiene compatibilidad: el modal inicia desde un viaje, pero permitimos asociar varios -->
                <input type="hidden" name="factura_cliente_id" id="factura_cliente_id">
                <input type="hidden" name="factura_transportista_id" id="factura_transportista_id" value="<?= htmlspecialchars($active_company_id) ?>">

                <!-- Viajes asociados (por ahora el modal agrega el viaje con el que se abrió) -->
                <input type="hidden" name="viaje_ids_csv" id="factura_viaje_ids_csv">
                <input type="hidden" name="viaje_id" id="factura_viaje_id" value="">

                <p id="factura_info" style="font-weight:bold; color:var(--accent); margin-bottom:15px;"></p>

                <div id="viaje_detalles_factura" style="font-size:0.85rem; margin-bottom:18px; padding:10px; background:rgba(0,0,0,0.02);">
                    <div style="font-weight:bold; margin-bottom:8px;">Detalle de viajes incluidos</div>
                    <div id="factura_viajes_table_container"></div>
                </div>

                <div class="card" style="margin-bottom:18px; background:rgba(0,0,0,0.02);">
                    <h4 style="margin-top:0"><i class="fas fa-file-invoice"></i> Totales</h4>
                    <div class="form-grid">
                        <div class="form-group"><label>Número Factura</label><input type="text" name="factura_nro" id="factura_nro" class="input-field" placeholder="0001-00001234" required></div>
                        <div class="form-group"><label>Fecha Emisión</label><input type="date" name="factura_fecha" id="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label>Flete Neto Total</label><input type="text" id="factura_flete_neto_total" class="input-field" value="0" disabled></div>
                        <div class="form-group"><label>IVA</label>
                            <select id="factura_iva_onoff" class="input-field" onchange="onIvaToggle()">
                                <option value="no">No aplica</option>
                                <option value="si" selected>Aplica</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid" id="iva_monto_block">
                        <div class="form-group"><label>Alícuota IVA (%)</label><input type="number" step="0.01" id="factura_iva_pct" class="input-field" value="21" onchange="recalcularTotalesFacturas()"></div>
                        <div class="form-group"><label>IVA Monto</label><input type="text" id="factura_iva_monto" class="input-field" value="0" disabled></div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label>Retenciones/Descuentos Totales</label><input type="text" id="factura_retenciones_total" class="input-field" value="0" disabled></div>
                        <div class="form-group"><label>Total Factura</label><input type="text" id="factura_total" class="input-field" value="0" disabled></div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:18px; background:rgba(0,0,0,0.02);">
                    <h4 style="margin-top:0"><i class="fas fa-cut"></i> Retenciones / Descuentos</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo</label>
                            <select id="ret_det_tipo" class="input-field">
                                <option value="RET_GANANCIAS">Ganancias</option>
                                <option value="RET_IIBB">IIBB</option>
                                <option value="DESCUENTO">Descuento</option>
                                <option value="OTRA_RETENCION">Otra retención</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Descripción</label><input type="text" id="ret_det_desc" class="input-field" placeholder="Detalle opcional"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Base (sobre qué se calcula)</label><input type="number" step="0.01" id="ret_det_base" class="input-field" value="0" onchange="recalcularRetencionPreview()"></div>
                        <div class="form-group"><label>% (opcional)</label><input type="number" step="0.01" id="ret_det_pct" class="input-field" value="0" onchange="recalcularRetencionPreview()"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Monto (se usa si no hay %)</label><input type="number" step="0.01" id="ret_det_monto" class="input-field" value="0" onchange="recalcularRetencionPreview()"></div>
                        <div class="form-group"><label>Preview Monto</label><input type="text" id="ret_det_monto_preview" class="input-field" value="0" disabled></div>
                    </div>
                    <div class="form-grid" style="align-items:end;">
                        <div class="form-group" style="grid-column: span 2;">
                            <button type="button" class="btn-primary" style="width:100%; background:#e74c3c;" onclick="agregarRetencionRow()"><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                    </div>

                    <div style="margin-top:14px;" id="retenciones_rows_container"></div>
                </div>

                <!-- Hidden: detalle retenciones en JSON simplificado -->
                <input type="hidden" name="retenciones_json" id="retenciones_json" value="[]">
            </div>

            <div class="modal-footer"><button type="submit" class="btn-primary" style="background:var(--accent);"><i class="fas fa-save"></i> Confirmar Factura</button></div>
        </form>
    </div>
</div>


<!-- Formulario oculto para acciones directas -->
<form id="form-liq-action" method="POST" style="display:none;"><input type="hidden" name="action" id="liq_input_action"><input type="hidden" name="viaje_id" id="liq_input_viaje_id"></form>
<form id="form-cobro-hidden" method="POST" style="display:none;"><input type="hidden" name="action" value="registrar_cobro"><input type="hidden" name="viaje_id" id="viaje_id_hidden"><input type="hidden" name="fecha_cobro" id="fecha_cobro_hidden"></form>

<script>
const formatMoneyJS = (amount) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount);
const formatDateJS = (dateString) => dateString ? new Date(dateString + 'T12:00:00').toLocaleDateString('es-AR') : '-';

let currentViajeId = null;
window.currentViajeData = null;

function abrirModalLiquidacion(id) {
    currentViajeId = id;
    document.getElementById('liq_input_viaje_id').value = id;
    openModal('modal-liquidacion');

    document.getElementById('liq_detalles_fondos').innerHTML = "<em>Cargando información...</em>";
    document.getElementById('liq_gastos_table_container').innerHTML = "";
    document.getElementById('liq_adelantos_table_container').innerHTML = "";

    fetch(`?route=cobranzas&get_viaje_info=${id}`)
        .then(res => res.json())
        .then(data => {
            if(data.error) { alert(data.error); return; }
            const v = data.viaje;
            window.currentViajeData = v;

            const residual = v.total_adelantos - v.gastos_rendidos;
            document.getElementById('liq_detalles_fondos').innerHTML = `
                Total Adelantos: <strong>${formatMoneyJS(v.total_adelantos)}</strong> | 
                Gastos del Adelanto: <strong>${formatMoneyJS(v.gastos_rendidos)}</strong> | 
                <span style="color:${residual > 0 ? '#e67e22' : '#2ecc71'}">Efectivo en mano: <strong>${formatMoneyJS(residual)}</strong></span>
            `;

            document.getElementById('liq_viaje_resumen_text').innerHTML = `Viaje #${v.id} (CP: ${v.carta_porte_nro || 'S/D'})<br>Producto: <strong>${v.producto}</strong> | Tarifa: <strong>${formatMoneyJS(v.tarifa_tonelada)}</strong>`;
            document.getElementById('liq_chofer_info').innerHTML = `Chofer: <strong>${v.chofer_nombre}</strong><br>Porcentaje: <strong>${v.chofer_porcentaje}%</strong>`;
            
            document.getElementById('btn_area_chofer').innerHTML = v.acreditado_chofer == 0
                ? `<button onclick="ejecutarAccionLiq('acreditar_chofer', ${id})" class="btn-primary" style="width:100%; margin-top:10px;"><i class="fas fa-check"></i> Acreditar Chofer</button>`
                : `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">ACREDITADO</span>`;

            if(v.comisionista_id) {
                const mC = (v.comision_tipo === 'porcentaje') ? (v.total_flete_neto * v.comision_valor / 100) : v.comision_valor;
                document.getElementById('liq_comision_info').innerHTML = `Intermediario: <strong>${v.comisionista_nombre}</strong><br>Monto: <strong>${formatMoneyJS(mC)}</strong>`;
                document.getElementById('btn_area_comision').innerHTML = v.comision_pagada == 0 
                    ? `<button onclick="ejecutarAccionLiq('pagar_comision', ${id})" class="btn-primary" style="width:100%; margin-top:10px; background:#f39c12;"><i class="fas fa-hand-holding-usd"></i> Pagar</button>`
                    : `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">PAGADA</span>`;
            } else {
                document.getElementById('liq_comision_info').innerText = "Sin comisión.";
                document.getElementById('btn_area_comision').innerHTML = "";
            }

            document.getElementById('liq_factura_info').innerHTML = `Cliente: <strong>${v.cliente_razon_social}</strong><br>Flete Neto: <strong>${formatMoneyJS(v.total_flete_neto)}</strong>`;
            document.getElementById('btn_area_factura').innerHTML = v.factura_nro 
                ? `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">FACTURA: ${v.factura_nro}</span>`
                : `<button onclick="abrirModalFactura(${id}, '${v.cliente_razon_social.replace(/'/g, "\\'")}', ${v.total_flete_neto}, '${v.carta_porte_nro || 'S/D'}')" class="btn-primary" style="width:100%; margin-top:10px; background:#3498db;"><i class="fas fa-file-invoice"></i> Generar Factura</button>`;

            document.getElementById('liq_cobro_info').innerHTML = `Estado: <strong>${v.estado.toUpperCase()}</strong><br>Fecha: <strong>${formatDateJS(v.fecha_cobro)}</strong>`;
            document.getElementById('btn_area_cobro').innerHTML = v.estado === 'facturado'
                ? `<button onclick="registrarCobroManual(${id}, '${v.factura_nro}')" class="btn-primary" style="width:100%; margin-top:10px; background:#2ecc71;"><i class="fas fa-money-bill-wave"></i> Cobrar</button>`
                : (v.estado === 'cobrado' || v.estado === 'liquidado' ? `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">COBRADO</span>` : `<span class="badge badge-secondary" style="display:block; padding:8px; margin-top:10px;">PENDIENTE FACTURA</span>`);

            renderGastosTable(data.gastos);
            renderAdelantosTable(data.adelantos);
        })
        .catch(err => { console.error(err); document.getElementById('liq_detalles_fondos').innerHTML = "Error al conectar."; });
}

function ejecutarAccionLiq(action, id) {
    const txt = action === 'acreditar_chofer' ? 'acreditar la ganancia al chofer' : 'pagar la comisión';
    appConfirm(`¿Deseas ${txt}?`, () => {
        document.getElementById('liq_input_action').value = action;
        document.getElementById('liq_input_viaje_id').value = id;
        document.getElementById('form-liq-action').submit();
    });
}

function abrirModalFactura(id, cliente, monto, cp) {
    // Inicializa el modal con un viaje (luego permite agregar más si el usuario lo hace desde la UI futura).
    const viajeIds = [id];
    document.getElementById('factura_viaje_id').value = id;
    document.getElementById('factura_viaje_ids_csv').value = viajeIds.join(',');

    // Cliente principal: por ahora tomamos el cliente asociado al viaje.
    // En el backend se validará y recalculará lo necesario.
    document.getElementById('factura_cliente_id').value = window.currentViajeData?.cliente_id || '';

    document.getElementById('factura_transportista_id').value = document.getElementById('factura_transportista_id')?.value || '';

    document.getElementById('factura_info').innerText = `${cliente} | Flete Neto: ${formatMoneyJS(monto)} | CP: ${cp}`;

    // Totales base
    document.getElementById('factura_flete_neto_total').value = formatMoneyJS(monto);

    // IVA/retenciones: por defecto
    document.getElementById('factura_iva_onoff').value = 'si';
    document.getElementById('factura_iva_pct').value = '21';
    document.getElementById('factura_iva_monto').value = formatMoneyJS((monto * 21) / 100);

    // Sin retenciones inicial
    document.getElementById('factura_retenciones_total').value = formatMoneyJS(0);
    document.getElementById('factura_total').value = formatMoneyJS(monto + (monto * 21) / 100);

    // Render de tabla de viajes incluidos
    document.getElementById('factura_viajes_table_container').innerHTML = `
        <div class="opacity-07">Cargando detalle del/los viaje(s)...</div>
    `;

    // Cargar detalle del viaje seleccionado
    fetch(`?route=cobranzas&get_viaje_info=${id}`)
        .then(res => res.json())
        .then(data => {
            window.currentFacturaViajesData = [data.viaje];

            const html = window.currentFacturaViajesData.map(v => {
                const kilos = (v.peso_neto ?? 0);
                return `
                    <div class='card' style='margin-bottom:10px;background:rgba(0,0,0,0.02);padding:10px;'>
                        <div><strong>Viaje #${v.id}</strong></div>
                        <div>CP: ${v.carta_porte_nro || v.otros_docs || 'S/D'}</div>
                        <div>Producto: ${v.producto || '-'}</div>
                        <div>Kilos netos: ${(kilos/1000).toFixed(2)} Ton</div>
                        <div>Flete neto: <strong>${formatMoneyJS(v.total_flete_neto)}</strong></div>
                    </div>
                `;
            }).join('');

            document.getElementById('factura_viajes_table_container').innerHTML = html;

            // Recalcular totales por sumatoria de viajes
            recacularTotalesDesdeViajes();
        });

    openModal('modal-factura');
}

function recacularTotalesDesdeViajes() {
    const viajes = window.currentFacturaViajesData || [];
    const fleteNetoTotal = viajes.reduce((acc, v) => acc + (parseFloat(v.total_flete_neto) || 0), 0);

    document.getElementById('factura_flete_neto_total').value = formatMoneyJS(fleteNetoTotal);

    const ivaOn = document.getElementById('factura_iva_onoff').value === 'si';
    const ivaPct = parseFloat(document.getElementById('factura_iva_pct').value) || 0;
    const ivaMonto = ivaOn ? (fleteNetoTotal * ivaPct / 100) : 0;

    document.getElementById('factura_iva_monto').value = formatMoneyJS(ivaMonto);

    // retenciones se calculan desde JSON que vamos llenando
    const retencionesTotal = calcularRetencionesTotalDesdeUI();
    document.getElementById('factura_retenciones_total').value = formatMoneyJS(retencionesTotal);

    const total = fleteNetoTotal + ivaMonto - retencionesTotal;
    document.getElementById('factura_total').value = formatMoneyJS(total);
}

function onIvaToggle() {
    recacularTotalesDesdeViajes();
}

function recalcularTotalesFacturas() {
    recacularTotalesDesdeViajes();
}

function recaLcularMontoBase(valor) {
    return parseFloat(valor) || 0;
}

function recalcularRetencionPreview() {
    const base = recaLcularMontoBase(document.getElementById('ret_det_base').value);
    const pct = recaLcularMontoBase(document.getElementById('ret_det_pct').value);
    const monto = recaLcularMontoBase(document.getElementById('ret_det_monto').value);

    const preview = (pct && pct > 0) ? (base * pct / 100) : monto;
    document.getElementById('ret_det_monto_preview').value = formatMoneyJS(preview);
}

function agregarRetencionRow() {
    recalcularRetencionPreview();

    const tipo = document.getElementById('ret_det_tipo').value;
    const descripcion = document.getElementById('ret_det_desc').value;
    const base = recaLcularMontoBase(document.getElementById('ret_det_base').value);
    const pct = recaLcularMontoBase(document.getElementById('ret_det_pct').value);
    const monto = recaLcularMontoBase(document.getElementById('ret_det_monto').value);

    const preview = (pct && pct > 0) ? (base * pct / 100) : monto;

    // Persistir en JSON hidden
    let arr = [];
    try { arr = JSON.parse(document.getElementById('retenciones_json').value || '[]'); } catch(e) { arr = []; }

    arr.push({ tipo, descripcion, base, porcentaje: pct || null, monto: preview });
    document.getElementById('retenciones_json').value = JSON.stringify(arr);

    renderRetencionesRows();
    recacularTotalesDesdeViajes();
}

function calcularRetencionesTotalDesdeUI() {
    let arr = [];
    try { arr = JSON.parse(document.getElementById('retenciones_json').value || '[]'); } catch(e) { arr = []; }
    return arr.reduce((acc, r) => acc + (parseFloat(r.monto) || 0), 0);
}

function renderRetencionesRows() {
    let arr = [];
    try { arr = JSON.parse(document.getElementById('retenciones_json').value || '[]'); } catch(e) { arr = []; }

    if (!arr.length) {
        document.getElementById('retenciones_rows_container').innerHTML = '<div style="opacity:0.7">Sin retenciones agregadas.</div>';
        return;
    }

    document.getElementById('retenciones_rows_container').innerHTML = arr.map((r, idx) => {
        return `
            <div class='card' style='margin-bottom:10px;background:rgba(0,0,0,0.02);padding:10px;'>
                <div style='display:flex;justify-content:space-between;gap:10px;'>
                    <div>
                        <div><strong>${r.tipo}</strong></div>
                        <div style='opacity:0.8;font-size:0.85rem;'>${r.descripcion || ''}</div>
                        <div style='font-size:0.85rem;opacity:0.85;'>Base: ${formatMoneyJS(r.base || 0)}${r.porcentaje ? ' | % ' + r.porcentaje : ''}</div>
                    </div>
                    <div style='text-align:right;'>
                        <div><strong>${formatMoneyJS(r.monto || 0)}</strong></div>
                        <button type='button' style='margin-top:8px;background:#e74c3c;border:none;color:white;padding:4px 8px;border-radius:4px;cursor:pointer;' onclick='eliminarRetencion(${idx})'>Eliminar</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function eliminarRetencion(idx) {
    let arr = [];
    try { arr = JSON.parse(document.getElementById('retenciones_json').value || '[]'); } catch(e) { arr = []; }
    arr.splice(idx, 1);
    document.getElementById('retenciones_json').value = JSON.stringify(arr);
    renderRetencionesRows();
    recacularTotalesDesdeViajes();
}


function registrarCobroManual(id, factura) {
    appConfirm(`¿Confirmas el cobro de la factura ${factura}?`, () => {
        document.getElementById('viaje_id_hidden').value = id;
        document.getElementById('fecha_cobro_hidden').value = new Date().toISOString().split('T')[0];
        document.getElementById('form-cobro-hidden').submit();
    });
}

function prepararEditarViajeLiq() {
    const v = window.currentViajeData;
    if(!v) return;
    document.getElementById('ed-liq-viaje-id').value = v.id;
    document.getElementById('ed-liq-producto').value = v.producto;
    document.getElementById('ed-liq-acoplado').value = v.acoplado;
    document.getElementById('ed-liq-tarifa').value = v.tarifa_tonelada;
    document.getElementById('ed-liq-porcentaje').value = v.chofer_porcentaje;
    document.getElementById('ed-liq-com-tipo').value = v.comision_tipo;
    document.getElementById('ed-liq-com-valor').value = v.comision_valor;
    document.getElementById('ed-liq-com-id').value = v.comisionista_id || "";
    document.getElementById('ed-liq-pag-id').value = v.pagador_id || "";
    openModal('modal-editar-viaje-liq');
}

function prepararNuevoGastoLiq() {
    document.getElementById('gasto-liq-id').value = "";
    document.getElementById('gasto-liq-viaje-id').value = currentViajeId;
    document.querySelector('#modal-gasto-liq form').reset();
    openModal('modal-gasto-liq');
}

function editGastoLiq(g) {
    document.getElementById('gasto-liq-id').value = g.id;
    document.getElementById('gasto-liq-viaje-id').value = currentViajeId;
    document.getElementById('gasto-liq-tipo').value = g.tipo_gasto;
    document.getElementById('gasto-liq-pagado-por').value = g.pagado_por;
    document.getElementById('gasto-liq-monto').value = g.monto;
    document.getElementById('gasto-liq-fecha').value = g.fecha;
    document.getElementById('gasto-liq-desc').value = g.descripcion;
    openModal('modal-gasto-liq');
}

function deleteGastoLiq(id) {
    appConfirm("¿Eliminar este gasto?", () => {
        const f = document.createElement('form'); f.method='POST';
        f.innerHTML = `<input type="hidden" name="action" value="delete_gasto"><input type="hidden" name="id_to_delete" value="${id}">`;
        document.body.appendChild(f); f.submit();
    });
}

function prepararNuevoAdelantoLiq() {
    document.getElementById('adelanto-liq-id').value = "";
    document.getElementById('adelanto-liq-viaje-id').value = currentViajeId;
    document.querySelector('#modal-adelanto-liq form').reset();
    openModal('modal-adelanto-liq');
}

function editAdelantoLiq(a) {
    document.getElementById('adelanto-liq-id').value = a.id;
    document.getElementById('adelanto-liq-viaje-id').value = currentViajeId;
    document.getElementById('adelanto-liq-monto').value = a.monto;
    document.getElementById('adelanto-liq-fecha').value = a.fecha;
    document.getElementById('adelanto-liq-metodo').value = a.metodo_pago;
    openModal('modal-adelanto-liq');
}

function deleteAdelantoLiq(id) {
    appConfirm("¿Eliminar este adelanto?", () => {
        const f = document.createElement('form'); f.method='POST';
        f.innerHTML = `<input type="hidden" name="action" value="delete_adelanto"><input type="hidden" name="id_to_delete" value="${id}">`;
        document.body.appendChild(f); f.submit();
    });
}

function renderGastosTable(gastos) {
    let h = "<h4>Gastos</h4>";
    if(!gastos.length) h += "<p style='opacity:0.7'>Sin gastos.</p>";
    else {
        h += "<table class='data-table'><thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th></th></tr></thead><tbody>";
        gastos.forEach(g => {
            h += `<tr><td>${formatDateJS(g.fecha)}</td><td>${g.tipo_gasto.toUpperCase()}</td><td>${formatMoneyJS(g.monto)}</td><td>
                <button onclick='editGastoLiq(${JSON.stringify(g).replace(/'/g, "\\'")})' style='background:none;border:none;color:var(--accent);cursor:pointer'><i class='fas fa-edit'></i></button>
                <button onclick='deleteGastoLiq(${g.id})' style='background:none;border:none;color:#e74c3c;cursor:pointer'><i class='fas fa-trash-alt'></i></button>
            </td></tr>`;
        });
        h += "</tbody></table>";
    }
    document.getElementById('liq_gastos_table_container').innerHTML = h;
}

function renderAdelantosTable(adelantos) {
    let h = "<h4>Adelantos</h4>";
    if(!adelantos.length) h += "<p style='opacity:0.7'>Sin adelantos.</p>";
    else {
        h += "<table class='data-table'><thead><tr><th>Fecha</th><th>Método</th><th>Monto</th><th></th></tr></thead><tbody>";
        adelantos.forEach(a => {
            h += `<tr><td>${formatDateJS(a.fecha)}</td><td>${a.metodo_pago}</td><td>${formatMoneyJS(a.monto)}</td><td>
                <button onclick='editAdelantoLiq(${JSON.stringify(a).replace(/'/g, "\\'")})' style='background:none;border:none;color:var(--accent);cursor:pointer'><i class='fas fa-edit'></i></button>
                <button onclick='deleteAdelantoLiq(${a.id})' style='background:none;border:none;color:#e74c3c;cursor:pointer'><i class='fas fa-trash-alt'></i></button>
            </td></tr>`;
        });
        h += "</tbody></table>";
    }
    document.getElementById('liq_adelantos_table_container').innerHTML = h;
}
</script>