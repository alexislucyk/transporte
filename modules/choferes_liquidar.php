<?php
/**
 * Módulo de Gestión de Cobranzas a Clientes
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

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
    
    try {
        $sql = "UPDATE viajes SET factura_nro = ?, factura_fecha = ?, estado = 'facturado' WHERE id = ?";
        $pdo->prepare($sql)->execute([$nro, $fecha, $viajeId]);
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
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha Carga</th>
                    <th>Cliente</th>
                    <th style="text-align:right">Importe a Facturar</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendientes_factura as $p): ?>
                <tr>
                    <td><?= formatDate($p['fecha_carga']) ?></td>
                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                    <td style="text-align:right; font-weight:bold;"><?= formatMoney($p['total_flete_neto']) ?></td>
                    <td style="text-align:center">
                        <button type="button" onclick="abrirModalFactura(<?= $p['id'] ?>, '<?= htmlspecialchars($p['cliente']) ?>', <?= $p['total_flete_neto'] ?>)" class="btn-primary" style="padding: 5px 12px; font-size: 0.85rem; background: #3498db;">
                            <i class="fas fa-file-invoice"></i> Facturar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Fletes Facturados Pendientes de Cobro</h3>
    <?php if (empty($pendientes_cobro)): ?>
        <p style="opacity: 0.6;">No hay facturas pendientes de cobro.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>F. Factura</th>
                    <th>Comprobante</th>
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
    <?php endif; ?>
</div>

<!-- Modal Facturación -->
<div id="modal-factura" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Registrar Facturación</h3><span class="close-modal" onclick="closeModal('modal-factura')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="registrar_factura">
                <input type="hidden" name="viaje_id" id="factura_viaje_id">
                <p id="factura_info" style="margin-bottom: 15px; font-weight: bold;"></p>
                <div class="form-group"><label>Número de Factura</label><input type="text" name="factura_nro" class="input-field" placeholder="0001-00001234" required></div>
                <div class="form-group"><label>Fecha de Emisión</label><input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
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
function abrirModalFactura(id, cliente, monto) {
    document.getElementById('factura_viaje_id').value = id;
    document.getElementById('factura_info').innerText = `Facturando a: ${cliente} - Importe: ${formatMoney(monto)}`;
    openModal('modal-factura');
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