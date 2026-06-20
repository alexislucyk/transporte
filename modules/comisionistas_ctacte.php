<?php
/**
 * Detalle de Cta Cte para Comisionistas
 */
$comisId = $params[0];
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND transportista_id = ?");
$stmt->execute([$comisId, $active_company_id]);
$comis = $stmt->fetch();

if (!$comis) die("Comisionista no encontrado.");

// Procesar Pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $stmt = $pdo->prepare("INSERT INTO comisionista_pagos (cliente_id, fecha, monto, detalle) VALUES (?, ?, ?, ?)");
    $stmt->execute([$comisId, $_POST['fecha'], $_POST['monto'], $_POST['detalle']]);
    header("Location: " . $base_path . "comisionistas/ctacte/" . $comisId); exit;
}

$movimientos = [];

// 1. Traer Comisiones de Viajes (HABER)
$viajes = $pdo->prepare("SELECT id, fecha_carga as fecha, origen, destino, total_flete_neto, total_flete_bruto, comision_tipo, comision_valor, estado FROM viajes WHERE comisionista_id = ?");
$viajes->execute([$comisId]);
foreach($viajes->fetchAll() as $v) {
    $base_flete = ($v['estado'] === 'en_viaje') ? $v['total_flete_bruto'] : $v['total_flete_neto'];
    $monto = ($v['comision_tipo'] === 'porcentaje') ? ($base_flete * $v['comision_valor'] / 100) : ($v['comision_tipo'] === 'monto_fijo' ? $v['comision_valor'] : 0);
    
    if ($monto > 0) {
        $movimientos[] = [
            'fecha' => $v['fecha'],
            'concepto' => "Comisión Viaje #{$v['id']} - {$v['origen']}/{$v['destino']}" . ($v['estado'] === 'en_viaje' ? ' (Est.)' : ''),
            'haber' => $monto,
            'debe' => 0
        ];
    }
}

// 2. Traer Pagos (DEBE)
$pagos = $pdo->prepare("SELECT fecha, detalle, monto FROM comisionista_pagos WHERE cliente_id = ?");
$pagos->execute([$comisId]);
foreach($pagos->fetchAll() as $p) {
    $movimientos[] = [
        'fecha' => $p['fecha'],
        'concepto' => "PAGO: " . $p['detalle'],
        'haber' => 0,
        'debe' => $p['monto']
    ];
}

usort($movimientos, function($a, $b) { return strtotime($a['fecha']) - strtotime($b['fecha']); });
?>

<div style="margin-bottom: 25px;">
    <a href="comisionistas" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; display: inline-block; margin-bottom: 10px;">
        <i class="fas fa-arrow-left"></i> Volver al listado
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin:0;">Cuenta Corriente</h1>
            <p style="margin:5px 0 0 0; opacity: 0.7;">Comisionista: <strong><?= htmlspecialchars($comis['razon_social']) ?></strong></p>
        </div>
        <button onclick="openModal('modal-pago-comis')" class="btn-primary" style="padding: 12px 24px; background: #2ecc71;">
            <i class="fas fa-hand-holding-usd"></i> Registrar Pago Realizado
        </button>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th style="text-align:right">Pagos (Debe)</th>
                <th style="text-align:right">Comisiones (Haber)</th>
                <th style="text-align:right">Saldo Acumulado</th>
            </tr>
        </thead>
        <tbody>
            <?php $saldo = 0; foreach($movimientos as $m): $saldo += ($m['haber'] - $m['debe']); ?>
            <tr>
                <td><?= formatDate($m['fecha']) ?></td>
                <td><?= htmlspecialchars($m['concepto']) ?></td>
                <td style="text-align:right; color: #e74c3c"><?= $m['debe'] > 0 ? formatMoney($m['debe']) : '-' ?></td>
                <td style="text-align:right; color: #2ecc71"><?= $m['haber'] > 0 ? formatMoney($m['haber']) : '-' ?></td>
                <td style="text-align:right; font-weight:bold"><?= formatMoney($saldo) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="modal-pago-comis" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header"><h3>Registrar Pago a Comisionista</h3><span class="close-modal" onclick="closeModal('modal-pago-comis')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="registrar_pago" value="1">
                <div class="form-group">
                    <label>Monto Entregado ($)</label>
                    <input type="number" step="0.01" name="monto" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>Fecha de Pago</label>
                    <input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Detalle / Observación</label>
                    <input type="text" name="detalle" class="input-field" placeholder="Ej: Pago comisiones quincena">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>