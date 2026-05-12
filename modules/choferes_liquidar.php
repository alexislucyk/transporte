<?php
/**
 * Módulo de Gestión de Cobranzas a Clientes
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- API INTERNA PARA DETALLES (AJAX) ---
if (isset($_GET['get_viaje_info'])) {
    // Limpiamos el buffer de salida de index.php (el HTML previo) 
    // para que la respuesta sea únicamente el JSON.
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    $id = $_GET['get_viaje_info'];
    
    // Obtener Gastos
    $stmtG = $pdo->prepare("SELECT fecha, tipo_gasto, monto, pagado_por FROM viajes_gastos WHERE viaje_id = ?");
    $stmtG->execute([$id]);
    $gastos = $stmtG->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener Adelantos
    $stmtA = $pdo->prepare("SELECT fecha, monto, metodo_pago FROM viajes_adelantos WHERE viaje_id = ?");
    $stmtA->execute([$id]);
    $adelantos = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'gastos' => $gastos,
        'adelantos' => $adelantos
    ]);
    exit;
}

// --- PROCESAR COBRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_cobro') {
    $viajeId = $_POST['viaje_id'];
    $fecha = $_POST['fecha_cobro'] ?? date('Y-m-d');
    
    try {
        $sql = "UPDATE viajes SET fecha_cobro = ?, estado = 'cobrado' WHERE id = ?";
        $pdo->prepare($sql)->execute([$fecha, $viajeId]);
        $mensaje = "Cobro registrado exitosamente.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- PROCESAR FACTURACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_factura') {
    $viajeId = $_POST['viaje_id'];
    $nro = trim($_POST['factura_nro']);
    $fecha = $_POST['factura_fecha'];
    $plazo = $_POST['plazo_pago']; // Días
    $obs = "Plazo de pago: $plazo días. " . ($_POST['observaciones'] ?? '');
    
    try {
        $sql = "UPDATE viajes SET factura_nro = ?, factura_fecha = ?, estado = 'facturado', observaciones = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$nro, $fecha, $obs, $viajeId]);
        $mensaje = "Factura registrada exitosamente.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- OBTENER FLETES PENDIENTES DE FACTURAR ---
$sql_f = "SELECT v.*, c.razon_social as cliente 
        FROM viajes v JOIN clientes c ON v.cliente_id = c.id
        WHERE v.transportista_id = ? AND v.estado = 'descargado'
        ORDER BY v.fecha_carga ASC";
$stmt_f = $pdo->prepare($sql_f);
$stmt_f->execute([$active_company_id]);
$pendientes_factura = $stmt_f->fetchAll();

// --- OBTENER FACTURAS PENDIENTES DE COBRO ---
$sql_c = "SELECT v.*, c.razon_social as cliente 
        FROM viajes v JOIN clientes c ON v.cliente_id = c.id
        WHERE v.transportista_id = ? AND v.estado = 'facturado'
        ORDER BY v.factura_fecha ASC";
$stmt_c = $pdo->prepare($sql_c);
$stmt_c->execute([$active_company_id]);
$pendientes_cobro = $stmt_c->fetchAll();

// --- OBTENER VIAJES COBRADOS PARA RENTABILIDAD ---
$sql_r = "SELECT v.*, c.razon_social as cliente,
        (SELECT COALESCE(SUM(monto),0) FROM viajes_gastos WHERE viaje_id = v.id AND pagado_por = 'empresa') as total_gastos_empresa
        FROM viajes v JOIN clientes c ON v.cliente_id = c.id
        WHERE v.transportista_id = ? AND v.estado IN ('cobrado', 'liquidado')
        ORDER BY v.fecha_cobro DESC LIMIT 10";
$stmt_r = $pdo->prepare($sql_r);
$stmt_r->execute([$active_company_id]);
$viajes_rentabilidad = $stmt_r->fetchAll();
?>

<h1>Gestión de Cobranzas</h1>
<p>Seguimiento de facturas emitidas y registro de ingresos por fletes.</p>

<?php if ($mensaje): ?>
    <div class="card" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?= $mensaje ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin-bottom: 20px;"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 30px;">
    <h3>Fletes Descargados Pendientes de Facturar</h3>
    <?php if (empty($pendientes_factura)): ?>
        <p style="opacity: 0.6;">No hay fletes pendientes de facturar.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha Carga</th>
                    <th>C. Porte</th>
                    <th>Cliente</th>
                    <th style="text-align:right">Importe a Facturar</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendientes_factura as $p): ?>
                <tr>
                    <td><?= formatDate($p['fecha_carga']) ?></td>
                    <td><span class="badge badge-info" style="font-size: 0.75rem;"><?= htmlspecialchars($p['carta_porte_nro'] ?: 'S/N') ?></span></td>
                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                    <td style="text-align:right; font-weight:bold;"><?= formatMoney($p['total_flete_neto']) ?></td>
                    <td style="text-align:center">
                        <button type="button" onclick="abrirModalFactura(<?= $p['id'] ?>, '<?= htmlspecialchars($p['cliente']) ?>', '<?= formatMoney($p['total_flete_neto']) ?>', '<?= htmlspecialchars($p['carta_porte_nro'] ?: 'S/N') ?>')" class="btn-primary" style="padding: 5px 12px; font-size: 0.85rem; background: #3498db;">
                            <i class="fas fa-file-invoice"></i> Facturar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Fletes Facturados Pendientes de Cobro</h3>
    <?php if (empty($pendientes_cobro)): ?>
        <p style="opacity: 0.6;">No hay facturas pendientes de cobro.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>F. Factura</th>
                    <th>Comprobante</th>
                    <th>C. Porte</th>
                    <th>Cliente</th>
                    <th style="text-align:right">Importe a Cobrar</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendientes_cobro as $p): ?>
                <tr>
                    <td><?= formatDate($p['factura_fecha']) ?></td>
                    <td><strong><?= htmlspecialchars($p['factura_nro']) ?></strong></td>
                    <td><small><?= htmlspecialchars($p['carta_porte_nro'] ?: '-') ?></small></td>
                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                    <td style="text-align:right; font-weight:bold; color: var(--accent);"><?= formatMoney($p['total_flete_neto']) ?></td>
                    <td style="text-align:center">
                        <button type="button" onclick="registrarCobro(<?= $p['id'] ?>, '<?= htmlspecialchars($p['factura_nro']) ?>')" class="btn-primary" style="padding: 5px 12px; font-size: 0.85rem; background: #2ecc71;">
                            <i class="fas fa-money-bill-wave"></i> Cobrado
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 30px; border-top: 4px solid #2ecc71;">
    <h3>Historial de Cobros y Rentabilidad</h3>
    <p style="font-size: 0.85rem; opacity: 0.7;">Análisis de rentabilidad neta (Flete - Comisiones - Chofer - Gastos).</p>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>F. Cobro</th>
                <th>C. Porte</th>
                <th>Cliente</th>
                <th style="text-align:right">Flete Neto</th>
                <th style="text-align:right">Gastos/Comis.</th>
                <th style="text-align:right">Pago Chofer</th>
                <th style="text-align:right">Rentabilidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($viajes_rentabilidad as $r): 
                $comision = ($r['comision_tipo'] === 'porcentaje') ? ($r['total_flete_neto'] * $r['comision_valor'] / 100) : ($r['comision_tipo'] === 'monto_fijo' ? $r['comision_valor'] : 0);
                $pago_chofer = ($r['total_flete_neto'] * $r['chofer_porcentaje'] / 100);
                $costos_totales = $comision + $r['total_gastos_empresa'];
                $utilidad = $r['total_flete_neto'] - $costos_totales - $pago_chofer;
            ?>
            <tr>
                <td><?= formatDate($r['fecha_cobro']) ?></td>
                <td><small><?= htmlspecialchars($r['carta_porte_nro'] ?: '-') ?></small></td>
                <td><?= htmlspecialchars($r['cliente']) ?></td>
                <td style="text-align:right"><?= formatMoney($r['total_flete_neto']) ?></td>
                <td style="text-align:right; color: #e74c3c">
                    - <?= formatMoney($costos_totales) ?>
                </td>
                <td style="text-align:right; color: #e74c3c">
                    - <?= formatMoney($pago_chofer) ?>
                </td>
                <td style="text-align:right; font-weight:bold; background: rgba(46, 204, 113, 0.1);">
                    <?= formatMoney($utilidad) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Facturación -->
<div id="modal-factura" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>Registrar Facturación</h3><span class="close-modal" onclick="closeModal('modal-factura')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="registrar_factura">
                <input type="hidden" name="viaje_id" id="factura_viaje_id">
                
                <div style="background: rgba(0,0,0,0.03); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed var(--accent);">
                    <p id="factura_info" style="margin: 0 0 10px 0; font-weight: bold; font-size: 1.1rem; color: var(--accent);"></p>
                    <div id="viaje_detalles_area" style="font-size: 0.85rem; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <!-- Se llena vía JS -->
                        <div id="detalles_gastos">Cargando gastos...</div>
                        <div id="detalles_adelantos">Cargando adelantos...</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group"><label>Número de Factura</label><input type="text" name="factura_nro" class="input-field" placeholder="0001-00001234" required></div>
                    <div class="form-group"><label>Fecha de Emisión</label><input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Plazo de Pago (Días)</label><input type="number" name="plazo_pago" class="input-field" value="30" required></div>
                    <div class="form-group"><label>Observaciones</label><input type="text" name="observaciones" class="input-field" placeholder="Notas adicionales..."></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Confirmar Facturación</button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario oculto para procesar el cobro -->
<form id="form-cobro-hidden" method="POST" style="display:none;">
    <input type="hidden" name="action" value="registrar_cobro">
    <input type="hidden" name="viaje_id" id="viaje_id_hidden">
    <input type="hidden" name="fecha_cobro" id="fecha_cobro_hidden">
</form>

<script>
const formatMoneyJS = (amount) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount);
};

function abrirModalFactura(id, cliente, monto, cp) {
    document.getElementById('factura_viaje_id').value = id;
    document.getElementById('factura_info').innerText = `Facturando CP: ${cp} | Cliente: ${cliente} | Total: ${monto}`;
    
    // Limpiar y cargar detalles
    document.getElementById('detalles_gastos').innerHTML = "<em>Cargando gastos...</em>";
    document.getElementById('detalles_adelantos').innerHTML = "<em>Cargando adelantos...</em>";
    
    openModal('modal-factura');

    fetch(`?route=cobranzas&get_viaje_info=${id}`)
        .then(res => res.json())
        .then(data => {
            let gastosHtml = "<strong>Gastos:</strong><br>";
            if(data.gastos.length === 0) gastosHtml += "Sin gastos registrados";
            data.gastos.forEach(g => {
                gastosHtml += `- ${g.tipo_gasto.toUpperCase()}: ${formatMoneyJS(g.monto)} (${g.pagado_por})<br>`;
            });
            document.getElementById('detalles_gastos').innerHTML = gastosHtml;

            let adelantosHtml = "<strong>Adelantos:</strong><br>";
            if(data.adelantos.length === 0) adelantosHtml += "Sin adelantos entregados";
            data.adelantos.forEach(a => {
                adelantosHtml += `- ${formatMoneyJS(a.monto)} (${a.metodo_pago})<br>`;
            });
            document.getElementById('detalles_adelantos').innerHTML = adelantosHtml;
        })
        .catch(err => {
            console.error("Error al cargar detalles:", err);
            document.getElementById('detalles_gastos').innerText = "Error al cargar";
            document.getElementById('detalles_adelantos').innerText = "Error al cargar";
        });
}

function registrarCobro(id, factura) {
    const hoy = new Date().toISOString().split('T')[0];
    const mensaje = `¿Confirmas que has recibido el pago de la factura ${factura}? \n\nEl estado del viaje pasará a 'COBRADO'.`;
    
    appConfirm(mensaje, function() {
        document.getElementById('viaje_id_hidden').value = id;
        document.getElementById('fecha_cobro_hidden').value = hoy;
        document.getElementById('form-cobro-hidden').submit();
    }, "Confirmar Cobro de Flete");
}
</script>