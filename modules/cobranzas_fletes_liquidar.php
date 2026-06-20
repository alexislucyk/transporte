<?php
/**
 * Cobranzas - Fletes (Liquidación)
 * Pantalla nueva (antes era modal dentro de modules/cobranzas.php)
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;
$viaje_id = isset($_GET['viaje_id']) ? (int)$_GET['viaje_id'] : 0;

if ($viaje_id <= 0) {
    $error = 'No se recibió un viaje válido para liquidar.';
}

// --- API INTERNA PARA DETALLES (AJAX) ---
if (isset($_GET['get_viaje_info'])) {
    $id = (int)$_GET['get_viaje_info'];
    if (ob_get_length()) ob_clean();

    try {
        $stmtViaje = $pdo->prepare(
            "
            SELECT v.*, 
                CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
                cli.razon_social as cliente_razon_social,
                ccom.razon_social as comisionista_nombre,
                (SELECT COALESCE(SUM(monto),0) FROM viajes_adelantos WHERE viaje_id = v.id) as total_adelantos,
                (SELECT COALESCE(SUM(monto),0) FROM viajes_gastos WHERE viaje_id = v.id AND pagado_por = 'adelanto') as gastos_rendidos
            FROM viajes v 
            JOIN choferes ch ON v.chofer_id = ch.id 
            LEFT JOIN clientes cli ON v.cliente_id = cli.id 
            LEFT JOIN clientes ccom ON v.comisionista_id = ccom.id
            WHERE v.id = ? AND v.transportista_id = ?
            "
        );
        $stmtViaje->execute([$id, $active_company_id]);
        $viaje_data = $stmtViaje->fetch();

        if (!$viaje_data) {
            throw new Exception("Viaje no encontrado.");
        }

        $stmtG = $pdo->prepare("SELECT id, fecha, tipo_gasto, monto, pagado_por, descripcion FROM viajes_gastos WHERE viaje_id = ? ORDER BY fecha ASC");
        $stmtG->execute([$id]);
        $gastos = $stmtG->fetchAll();

        $stmtA = $pdo->prepare("SELECT id, fecha, monto, metodo_pago FROM viajes_adelantos WHERE viaje_id = ? ORDER BY fecha ASC");
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

// --- Selectores para “Editar Viaje / Comisiones” ---
$lista_pagadores = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_pagador = 1 ORDER BY razon_social ASC");
$lista_pagadores->execute([$active_company_id]);

$lista_comisionistas = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND es_comisionista = 1 ORDER BY razon_social ASC");
$lista_comisionistas->execute([$active_company_id]);

?>

<div class="card" style="margin-bottom:20px; border-top:4px solid var(--accent); padding:12px 16px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Liquidación de Flete (Viaje)</h1>
            <p style="margin:6px 0 0; opacity:0.8;">Cargar datos operativos, rendición, facturación y cobro.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn-primary" href="?route=cobranzas" style="background:#34495e; padding:6px 14px; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                <i class="fas fa-arrow-left"></i> Volver a Cobranzas
            </a>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background:#d4edda; color:#155724; border:1px solid #c3e6cb; margin-bottom:20px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($viaje_id > 0 && !$error): ?>
    <div class="card" style="margin-bottom: 30px; border-top: 4px solid var(--accent); padding: 12px; background: transparent;">
        <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #34495e; margin-bottom:15px; padding:12px;">
            <h3 style="margin:0 0 10px 0;"><i class="fas fa-info-circle"></i> Datos Operativos</h3>
            <p id="liq_viaje_resumen_text" style="font-size:0.95rem; margin:0 0 10px 0;"></p>
            <button onclick="prepararEditarViajeLiq()" class="btn-primary" style="width:100%; background:#34495e;">
                <i class="fas fa-edit"></i> Editar Viaje / Comisiones
            </button>
        </div>

        <div class="form-grid" style="margin-bottom:15px;">
            <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #2ecc71; padding:12px;">
                <h3 style="margin:0 0 10px 0;"><i class="fas fa-user"></i> Chofer</h3>
                <p id="liq_chofer_info" style="margin:0;"></p>
                <div id="btn_area_chofer" style="margin-top:10px;"></div>
            </div>

            <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #f39c12; padding:12px;">
                <h3 style="margin:0 0 10px 0;"><i class="fas fa-handshake"></i> Comisión</h3>
                <p id="liq_comision_info" style="margin:0;"></p>
                <div id="btn_area_comision" style="margin-top:10px;"></div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px; padding:12px; background:rgba(0,0,0,0.02);">
            <h3 style="margin:0 0 10px 0;"><i class="fas fa-wallet"></i> Rendición de Fondos (Chofer)</h3>
            <div id="liq_detalles_fondos" style="font-size:0.95rem; margin-bottom:15px; padding:10px; background:rgba(0,0,0,0.02); border-radius:5px;"></div>
            <div class="form-grid" style="margin-bottom:15px;">
                <button onclick="prepararNuevoGastoLiq()" class="btn-primary" style="background:#e67e22;">
                    <i class="fas fa-gas-pump"></i> Cargar Gasto
                </button>
                <button onclick="prepararNuevoAdelantoLiq()" class="btn-primary" style="background:#3498db;">
                    <i class="fas fa-hand-holding-usd"></i> Dar Adelanto
                </button>
            </div>
            <div id="liq_gastos_table_container"></div>
            <div id="liq_adelantos_table_container" style="margin-top:15px;"></div>
        </div>

        <div class="form-grid">
            <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #3498db; padding:12px;">
                <h3 style="margin:0 0 10px 0;"><i class="fas fa-file-invoice"></i> Facturación</h3>
                <p id="liq_factura_info" style="margin:0;"></p>
                <div id="btn_area_factura" style="margin-top:10px;"></div>
            </div>

            <div class="card" style="background:rgba(0,0,0,0.02); border-left:4px solid #2ecc71; padding:12px;">
                <h3 style="margin:0 0 10px 0;"><i class="fas fa-money-bill-wave"></i> Cobro</h3>
                <p id="liq_cobro_info" style="margin:0;"></p>
                <div id="btn_area_cobro" style="margin-top:10px;"></div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para acciones directas (delegamos a route=cobranzas que ya tiene los endpoints POST) -->
    <form id="form-liq-action" method="POST" action="?route=cobranzas" style="display:none;">
        <input type="hidden" name="action" id="liq_input_action">
        <input type="hidden" name="viaje_id" id="liq_input_viaje_id" value="<?= (int)$viaje_id ?>">
    </form>

    <form id="form-cobro-hidden" method="POST" action="?route=cobranzas" style="display:none;">
        <input type="hidden" name="action" value="registrar_cobro">
        <input type="hidden" name="viaje_id" id="viaje_id_hidden" value="<?= (int)$viaje_id ?>">
        <input type="hidden" name="fecha_cobro" id="fecha_cobro_hidden">
    </form>

    <!-- Modales -->

    <!-- Modal Gasto -->
    <div id="modal-gasto-liq" class="modal" style="z-index: 1002;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header"><h3 id="gasto-liq-title">Gasto de Viaje</h3><span class="close-modal" onclick="closeModal('modal-gasto-liq')">&times;</span></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="movimiento" value="gasto">
                    <input type="hidden" name="viaje_id_modal" id="gasto-liq-viaje-id">
                    <input type="hidden" name="id" id="gasto-liq-id">
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
                    <input type="hidden" name="movimiento" value="adelanto">
                    <input type="hidden" name="viaje_id_modal" id="adelanto-liq-viaje-id">
                    <input type="hidden" name="id" id="adelanto-liq-id">
                    <div class="form-group"><label>Monto</label><input type="number" step="0.01" name="monto" id="adelanto-liq-monto" class="input-field" required></div>
                    <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="adelanto-liq-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Método</label><select name="metodo" id="adelanto-liq-metodo" class="input-field"><option value="Efectivo">Efectivo</option><option value="Transferencia">Transferencia</option></select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn-primary">Guardar Adelanto</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Factura (migrado desde modules/cobranzas.php) -->
    <div id="modal-factura" class="modal" style="z-index: 1003;">

        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header"><h3>Registrar Facturación</h3><span class="close-modal" onclick="closeModal('modal-factura')">&times;</span></div>

            <form method="POST" id="form-factura-detallada">
                <div class="modal-body">
                    <input type="hidden" name="action" value="registrar_factura_detallada">

                    <input type="hidden" name="factura_cliente_id" id="factura_cliente_id">
                    <input type="hidden" name="factura_transportista_id" id="factura_transportista_id" value="<?= htmlspecialchars($active_company_id) ?>">

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

                    <input type="hidden" name="retenciones_json" id="retenciones_json" value="[]">
                </div>

                <div class="modal-footer"><button type="submit" class="btn-primary" style="background:var(--accent);"><i class="fas fa-save"></i> Confirmar Factura</button></div>
            </form>
        </div>
    </div>

    <div id="modal-editar-viaje-liq" class="modal" style="z-index: 1002;">


        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Editar Información del Viaje</h3>
                <span class="close-modal" onclick="closeModal('modal-editar-viaje-liq')">&times;</span>
            </div>
            <form method="POST" action="?route=cobranzas">
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar_viaje_liq">
                    <input type="hidden" name="viaje_id" id="ed-liq-viaje-id" value="<?= (int)$viaje_id ?>">

                    <div class="form-grid">
                        <div class="form-group"><label>Producto</label><input type="text" name="producto" id="ed-liq-producto" class="input-field"></div>
                        <div class="form-group"><label>Acoplado</label><input type="text" name="acoplado" id="ed-liq-acoplado" class="input-field"></div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label>Tarifa x Ton ($)</label><input type="number" step="0.01" name="tarifa" id="ed-liq-tarifa" class="input-field" required></div>
                        <div class="form-group"><label>% Chofer</label><input type="number" step="0.01" name="porcentaje" id="ed-liq-porcentaje" class="input-field" required></div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Comisión Tipo</label>
                            <select name="comision_tipo" id="ed-liq-com-tipo" class="input-field">
                                <option value="ninguna">No Paga</option>
                                <option value="porcentaje">Porcentaje (%)</option>
                                <option value="monto_fijo">Monto Fijo ($)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Valor Comisión</label><input type="number" step="0.01" name="comision_valor" id="ed-liq-com-valor" class="input-field"></div>
                        <div class="form-group">
                            <label>Comisionista</label>
                            <select name="comisionista_id" id="ed-liq-com-id" class="input-field">
                                <option value="">-- Sin comisión --</option>
                                <?php foreach ($lista_comisionistas as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Pagador del Flete</label>
                        <select name="pagador_id" id="ed-liq-pag-id" class="input-field">
                            <option value="">-- No especificado --</option>
                            <?php foreach ($lista_pagadores as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer"><button type="submit" class="btn-primary">Guardar Cambios</button></div>
            </form>
        </div>
    </div>

    <!-- JS: carga inicial del viaje y utilidades existentes -->
    <script>
    const formatMoneyJS = (amount) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount);
    const formatDateJS = (dateString) => dateString ? new Date(dateString + 'T12:00:00').toLocaleDateString('es-AR') : '-';

    let currentViajeData = null;

    function cargarViajeLiquidacion(id) {
        fetch(`?route=cobranzas_fletes_liquidar&get_viaje_info=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                const v = data.viaje;
                currentViajeData = v;

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

                if (v.comisionista_id) {
                    const mC = (v.comision_tipo === 'porcentaje') ? (v.total_flete_neto * v.comision_valor / 100) : v.comision_valor;
                    document.getElementById('liq_comision_info').innerHTML = `Intermediario: <strong>${v.comisionista_nombre}</strong><br>Monto: <strong>${formatMoneyJS(mC)}</strong>`;
                    document.getElementById('btn_area_comision').innerHTML = v.comision_pagada == 0
                        ? `<button onclick="ejecutarAccionLiq('pagar_comision', ${id})" class="btn-primary" style="width:100%; margin-top:10px; background:#f39c12;"><i class="fas fa-hand-holding-usd"></i> Pagar</button>`
                        : `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">PAGADA</span>`;
                } else {
                    document.getElementById('liq_comision_info').innerText = 'Sin comisión.';
                    document.getElementById('btn_area_comision').innerHTML = '';
                }

                document.getElementById('liq_factura_info').innerHTML = `Cliente: <strong>${v.cliente_razon_social}</strong><br>Flete Neto: <strong>${formatMoneyJS(v.total_flete_neto)}</strong>`;
                document.getElementById('btn_area_factura').innerHTML = v.factura_nro 
                    ? `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">FACTURA: ${v.factura_nro}</span>`
                    : `<button onclick="abrirModalFactura(${id}, '${v.cliente_razon_social.replace(/'/g, "\\'")}', ${v.total_flete_neto}, '${v.carta_porte_nro || 'S/D'}')" class="btn-primary" style="width:100%; margin-top:10px; background:#3498db;"><i class="fas fa-file-invoice"></i> Generar Factura</button>`;

                document.getElementById('liq_cobro_info').innerHTML = `Estado: <strong>${v.estado.toUpperCase()}</strong><br>Fecha: <strong>${formatDateJS(v.fecha_cobro)}</strong>`;
                document.getElementById('btn_area_cobro').innerHTML = v.estado === 'facturado'
                    ? `<button onclick="registrarCobroManual(${id}, '${String(v.factura_nro || '')}')" class="btn-primary" style="width:100%; margin-top:10px; background:#2ecc71;"><i class="fas fa-money-bill-wave"></i> Cobrar</button>`
                    : (v.estado === 'cobrado' || v.estado === 'liquidado' ? `<span class="badge badge-success" style="display:block; padding:8px; margin-top:10px;">COBRADO</span>` : `<span class="badge badge-secondary" style="display:block; padding:8px; margin-top:10px;">PENDIENTE FACTURA</span>`);

                // Tablas
                renderGastosTable(data.gastos);
                renderAdelantosTable(data.adelantos);
            })
            .catch(err => {
                console.error(err);
                alert('Error al conectar al endpoint de detalle.');
            });
    }

    function renderGastosTable(gastos) {
        let h = "<h4>Gastos</h4>";
        if (!gastos.length) {
            h += "<p style='opacity:0.7'>Sin gastos.</p>";
        } else {
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
        if (!adelantos.length) {
            h += "<p style='opacity:0.7'>Sin adelantos.</p>";
        } else {
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

    // Handlers mínimos para editar/eliminar (reutilizan los formularios/modales del backend)
    function prepararNuevoGastoLiq() {
        document.getElementById('gasto-liq-id').value = "";
        document.getElementById('gasto-liq-viaje-id').value = <?= (int)$viaje_id ?>;
        document.querySelector('#modal-gasto-liq form').reset();
        openModal('modal-gasto-liq');
    }

    function ejecutarAccionGasto() {}

    function editGastoLiq(g) {
        document.getElementById('gasto-liq-id').value = g.id;
        document.getElementById('gasto-liq-viaje-id').value = <?= (int)$viaje_id ?>;
        document.getElementById('gasto-liq-tipo').value = g.tipo_gasto;
        document.getElementById('gasto-liq-monto').value = g.monto;
        document.getElementById('gasto-liq-fecha').value = g.fecha;
        document.getElementById('gasto-liq-desc').value = g.descripcion;
        document.getElementById('gasto-liq-pagado-por').value = g.pagado_por;
        openModal('modal-gasto-liq');
    }

    function deleteGastoLiq(id) {
        if (typeof appConfirm === 'function') {
            appConfirm("¿Eliminar este gasto?", () => {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="delete_gasto"><input type="hidden" name="id_to_delete" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            });
        }
    }

    function prepararNuevoAdelantoLiq() {
        document.getElementById('adelanto-liq-id').value = "";
        document.getElementById('adelanto-liq-viaje-id').value = <?= (int)$viaje_id ?>;
        document.querySelector('#modal-adelanto-liq form').reset();
        openModal('modal-adelanto-liq');
    }

    function editAdelantoLiq(a) {
        document.getElementById('adelanto-liq-id').value = a.id;
        document.getElementById('adelanto-liq-viaje-id').value = <?= (int)$viaje_id ?>;
        document.getElementById('adelanto-liq-monto').value = a.monto;
        document.getElementById('adelanto-liq-fecha').value = a.fecha;
        document.getElementById('adelanto-liq-metodo').value = a.metodo_pago;
        openModal('modal-adelanto-liq');
    }

    function deleteAdelantoLiq(id) {
        if (typeof appConfirm === 'function') {
            appConfirm("¿Eliminar este adelanto?", () => {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="delete_adelanto"><input type="hidden" name="id_to_delete" value="${id}">`;
                document.body.appendChild(f);
                f.submit();
            });
        }
    }


    function ejecutarAccionLiq(action, id) {
        const txt = action === 'acreditar_chofer' ? 'acreditar la ganancia al chofer' : 'pagar la comisión';
        if (typeof appConfirm === 'function') {
            appConfirm(`¿Deseas ${txt}?`, () => {
                document.getElementById('liq_input_action').value = action;
                document.getElementById('liq_input_viaje_id').value = id;
                document.getElementById('form-liq-action').submit();
            });
        } else {
            document.getElementById('liq_input_action').value = action;
            document.getElementById('liq_input_viaje_id').value = id;
            document.getElementById('form-liq-action').submit();
        }
    }

    function registrarCobroManual(id, factura) {
        if (typeof appConfirm === 'function') {
            appConfirm(`¿Confirmas el cobro de la factura ${factura}?`, () => {
                document.getElementById('viaje_id_hidden').value = id;
                document.getElementById('fecha_cobro_hidden').value = new Date().toISOString().split('T')[0];
                document.getElementById('form-cobro-hidden').submit();
            });
            return;
        }

        document.getElementById('viaje_id_hidden').value = id;
        document.getElementById('fecha_cobro_hidden').value = new Date().toISOString().split('T')[0];
        document.getElementById('form-cobro-hidden').submit();
    }

    function prepararEditarViajeLiq() {
        const v = currentViajeData;
        if (!v) return;
        document.getElementById('ed-liq-viaje-id').value = v.id;
        document.getElementById('ed-liq-producto').value = v.producto;
        document.getElementById('ed-liq-acoplado').value = v.acoplado;
        document.getElementById('ed-liq-tarifa').value = v.tarifa_tonelada;
        document.getElementById('ed-liq-porcentaje').value = v.chofer_porcentaje;
        document.getElementById('ed-liq-com-tipo').value = v.comision_tipo;
        document.getElementById('ed-liq-com-valor').value = v.comision_valor;
        document.getElementById('ed-liq-com-id').value = v.comisionista_id || '';
        document.getElementById('ed-liq-pag-id').value = v.pagador_id || '';
        openModal('modal-editar-viaje-liq');
    }

    // --- Facturación (Modal) - migrado desde modules/cobranzas.php ---

    window.currentFacturaViajesData = [];

    function abrirModalFactura(id, cliente, monto, cp) {
        // Inicializa el modal con un viaje (luego permite agregar más si la UI futura lo permite).
        const viajeIds = [id];
        document.getElementById('factura_viaje_id').value = id;
        document.getElementById('factura_viaje_ids_csv').value = viajeIds.join(',');

        document.getElementById('factura_cliente_id').value = window.currentViajeData?.cliente_id || '';
        document.getElementById('factura_info').innerText = `${cliente} | Flete Neto: ${formatMoneyJS(monto)} | CP: ${cp}`;

        document.getElementById('factura_flete_neto_total').value = formatMoneyJS(monto);

        document.getElementById('factura_iva_onoff').value = 'si';
        document.getElementById('factura_iva_pct').value = '21';
        document.getElementById('factura_iva_monto').value = formatMoneyJS((monto * 21) / 100);

        document.getElementById('factura_retenciones_total').value = formatMoneyJS(0);
        document.getElementById('factura_total').value = formatMoneyJS(monto + (monto * 21) / 100);

        // Reset retenciones
        document.getElementById('retenciones_json').value = '[]';
        document.getElementById('retenciones_rows_container').innerHTML = '';

        document.getElementById('factura_viajes_table_container').innerHTML = `
            <div class="opacity-07">Cargando detalle del/los viaje(s)...</div>
        `;

        fetch(`?route=cobranzas_fletes_liquidar&get_viaje_info=${id}`)
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

    // arrancar
    cargarViajeLiquidacion(<?= (int)$viaje_id ?>);
    </script>
<?php endif; ?>


