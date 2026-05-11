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

// --- OBTENER FACTURAS PENDIENTES DE COBRO ---
$sql = "SELECT v.*, c.razon_social as cliente 
        FROM viajes v JOIN clientes c ON v.cliente_id = c.id
        WHERE v.transportista_id = ? AND v.estado = 'facturado'
        ORDER BY v.factura_fecha ASC";
$stmt_p = $pdo->prepare($sql);
$stmt_p->execute([$active_company_id]);
$pendientes = $stmt_p->fetchAll();
?>

<h1>Gestión de Cobranzas</h1>
<p>Seguimiento de facturas emitidas y registro de ingresos por fletes.</p>

<?php if ($mensaje): ?>
    <div class="card" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?= $mensaje ?></div>
<?php endif; ?>

<div class="card">
    <h3>Fletes Facturados Pendientes de Cobro</h3>
    <?php if (empty($pendientes)): ?>
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
                <?php foreach($pendientes as $p): ?>
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

<!-- Formulario oculto para procesar el cobro -->
<form id="form-cobro-hidden" method="POST" style="display:none;">
    <input type="hidden" name="action" value="registrar_cobro">
    <input type="hidden" name="viaje_id" id="viaje_id_hidden">
    <input type="hidden" name="fecha_cobro" id="fecha_cobro_hidden">
</form>

<script>
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