<?php
/**
 * Módulo de Gestión de Comisionistas - Trans Cargo Hub
 */
$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- LÓGICA DE LA CUENTA CORRIENTE ---
if ($action === 'ctacte' && isset($params[0])) {
    include_once 'modules/comisionistas_ctacte.php';
    return;
}

// Consulta para obtener comisionistas y su saldo calculado
// Haber: Comisiones de viajes (según tipo % o fijo)
// Debe: Pagos realizados
$sql = "SELECT c.*, 
        (
            COALESCE((
                SELECT SUM(
                    CASE 
                        WHEN comision_tipo = 'porcentaje' THEN (total_flete_neto * comision_valor / 100)
                        WHEN comision_tipo = 'monto_fijo' THEN comision_valor
                        ELSE 0 
                    END
                ) 
                FROM viajes 
                WHERE comisionista_id = c.id AND estado != 'en_viaje'
            ), 0)
            -
            COALESCE((SELECT SUM(monto) FROM comisionista_pagos WHERE cliente_id = c.id), 0)
        ) as saldo
        FROM clientes c 
        WHERE c.transportista_id = ? AND c.es_comisionista = 1 
        ORDER BY c.razon_social ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$active_company_id]);
$comisionistas = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Gestión de Comisionistas</h1>
        <p>Seguimiento de saldos y pagos a intermediarios de carga.</p>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Razón Social</th>
                <th>CUIT</th>
                <th>Dirección / Teléfono</th>
                <th style="text-align:right">Saldo a Pagar</th>
                <th style="text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($comisionistas as $c): ?>
            <tr>
                <td style="font-weight:bold"><?= htmlspecialchars($c['razon_social']) ?></td>
                <td><?= $c['cuit'] ?></td>
                <td><?= htmlspecialchars($c['direccion']) ?></td>
                <td style="text-align:right; font-weight:bold; color: <?= $c['saldo'] > 0 ? '#e67e22' : 'inherit' ?>">
                    <?= formatMoney($c['saldo']) ?>
                </td>
                <td style="text-align:center">
                    <a href="comisionistas/ctacte/<?= $c['id'] ?>" class="btn-primary" style="padding: 5px 12px; font-size: 0.85rem; background: var(--primary);">
                        <i class="fas fa-file-invoice-dollar"></i> Cuenta Corriente
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($comisionistas)): ?>
                <tr><td colspan="5" style="text-align:center; opacity:0.6; padding: 30px;">No hay clientes marcados con el rol 'Comisionista'.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>