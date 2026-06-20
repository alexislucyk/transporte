<?php
/**
 * Cobranzas - Fletes Facturados Pendientes de Cobro
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// Importante: el botón "Cobrado" usa el mismo endpoint POST que en cobros/cobranzas.php
// Ese endpoint no está centralizado acá, por lo que delegamos en el formulario/JS existente.
// El render es solamente la grilla de pendientes.

try {
    $pendientes_cobro = $pdo->prepare(
        "SELECT v.*, c.razon_social as cliente
         FROM viajes v
         JOIN clientes c ON v.cliente_id = c.id
         WHERE v.transportista_id = ?
           AND v.estado = 'facturado'
         ORDER BY v.factura_fecha ASC"
    );
    $pendientes_cobro->execute([$active_company_id]);
    $lista_cobro = $pendientes_cobro->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
    $lista_cobro = [];
}

?>

<div class="card" style="margin-bottom: 20px; padding: 12px 16px; border-top: 4px solid var(--accent);">
    <h1 style="margin:0;">Fletes Pendientes de Cobro</h1>
    <p style="margin:6px 0 0;">Facturas emitidas aún no cobradas.</p>
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
        <a class="btn-primary" href="?route=cobranzas" style="background:#34495e; padding:6px 14px; font-size:0.9rem; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
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

<div class="card" style="margin-bottom: 30px; border-top: 4px solid var(--accent);">
    <h3>Fletes Facturados Pendientes de Cobro</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>CP/Remito</th>
                    <th>Cliente</th>
                    <th>Emisión</th>
                    <th>Factura</th>
                    <th style="text-align:right">Importe</th>
                    <th style="text-align:center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista_cobro as $c): ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars($c['carta_porte_nro'] ?: ($c['otros_docs'] ?: 'S/D')) ?></span></td>
                    <td><?= htmlspecialchars($c['cliente']) ?></td>
                    <td><?= formatDate($c['factura_fecha']) ?></td>
                    <td><strong style="color:var(--accent)"><?= $c['factura_nro'] ?></strong></td>
                    <td style="text-align:right; font-weight:bold;"><?= formatMoney($c['total_flete_neto']) ?></td>
                    <td style="text-align:center;">
                        <button
                            onclick="registrarCobroManual(<?= (int)$c['id'] ?>, '<?= htmlspecialchars((string)$c['factura_nro'], ENT_QUOTES, 'UTF-8') ?>')"
                            class="btn-primary"
                            style="background:#2ecc71; padding:5px 12px; font-size:0.85rem;">
                            <i class="fas fa-money-bill-wave"></i> Cobrado
                        </button>
                    </td>
                </tr>
                <?php endforeach; if(empty($lista_cobro)): ?>
                    <tr><td colspan="6" style="text-align:center; opacity:0.6;">Sin facturas pendientes de cobro.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Copia mínima del submit que usa cobranzas.php (para que el botón funcione desde esta página)
function registrarCobroManual(id, factura) {
    if (typeof appConfirm === 'function') {
        appConfirm(`¿Confirmas el cobro de la factura ${factura}?`, () => {
            document.getElementById('viaje_id_hidden').value = id;
            document.getElementById('fecha_cobro_hidden').value = new Date().toISOString().split('T')[0];
            document.getElementById('form-cobro-hidden').submit();
        });
        return;
    }

    // fallback
    document.getElementById('viaje_id_hidden').value = id;
    document.getElementById('fecha_cobro_hidden').value = new Date().toISOString().split('T')[0];
    document.getElementById('form-cobro-hidden').submit();
}
</script>

<!-- Formulario oculto para acciones directas -->
<form id="form-cobro-hidden" method="POST" style="display:none;">
    <input type="hidden" name="action" value="registrar_cobro">
    <input type="hidden" name="viaje_id" id="viaje_id_hidden">
    <input type="hidden" name="fecha_cobro" id="fecha_cobro_hidden">
</form>

