<?php
/**
 * Módulo de Tesorería y Conciliación Global
 */
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- CÁLCULOS DE FLUJO DE CAJA ---

// 1. Ingresos Reales (Fletes Cobrados)
$stmt = $pdo->prepare("SELECT SUM(total_flete_neto) FROM viajes WHERE transportista_id = ? AND estado IN ('cobrado', 'liquidado')");
$stmt->execute([$active_company_id]);
$total_ingresos = $stmt->fetchColumn() ?: 0;

// 2. Egresos Reales: Pagos a Choferes (Salidas de dinero)
$stmt = $pdo->prepare("SELECT SUM(cp.monto) FROM chofer_pagos cp JOIN choferes c ON cp.chofer_id = c.id WHERE c.transportista_id = ? AND cp.tipo != 'liquidacion'");
$stmt->execute([$active_company_id]);
$pagos_choferes = $stmt->fetchColumn() ?: 0;

// 3. Egresos Reales: Gastos de Empresa pagados directamente
$stmt = $pdo->prepare("SELECT SUM(g.monto) FROM viajes_gastos g JOIN viajes v ON g.viaje_id = v.id WHERE v.transportista_id = ? AND g.pagado_por = 'empresa' AND g.activo = 1");
$stmt->execute([$active_company_id]);
$gastos_empresa = $stmt->fetchColumn() ?: 0;

// 4. Egresos Reales: Mantenimientos de flota
$stmt = $pdo->prepare("SELECT SUM(m.costo_total) FROM mantenimientos m JOIN vehiculos v ON m.vehiculo_id = v.id WHERE v.transportista_id = ?");
$stmt->execute([$active_company_id]);
$gastos_manto = $stmt->fetchColumn() ?: 0;

// 5. Egresos Reales: Pagos a Comisionistas
$pagos_comis = 0;
try {
    $stmt = $pdo->prepare("SELECT SUM(cp.monto) FROM comisionista_pagos cp JOIN clientes c ON cp.cliente_id = c.id WHERE c.transportista_id = ?");
    $stmt->execute([$active_company_id]);
    $pagos_comis = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) { /* Fallback */ }

$total_egresos = $pagos_choferes + $gastos_empresa + $gastos_manto + $pagos_comis;
$saldo_caja = $total_ingresos - $total_egresos;

// --- DEUDAS Y PENDIENTES (CONCILIACIÓN) ---

// Pendiente de Cobro a Clientes
$stmt = $pdo->prepare("SELECT SUM(total_flete_neto) FROM viajes WHERE transportista_id = ? AND estado IN ('descargado', 'facturado')");
$stmt->execute([$active_company_id]);
$por_cobrar = $stmt->fetchColumn() ?: 0;

// Saldo acumulado en Ctas Ctes de Choferes
$stmt = $pdo->prepare("SELECT id FROM choferes WHERE transportista_id = ?");
$stmt->execute([$active_company_id]);
$ids_choferes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$saldo_total_choferes = 0;
foreach($ids_choferes as $ch_id) {
    $sql_s = "SELECT (
        COALESCE((SELECT SUM(monto) FROM chofer_pagos WHERE chofer_id = ? AND tipo = 'liquidacion'), 0) 
        - 
        COALESCE((SELECT SUM(monto) FROM chofer_pagos WHERE chofer_id = ? AND tipo != 'liquidacion'), 0) 
        + 
        COALESCE((SELECT SUM(vg.monto) FROM viajes_gastos vg JOIN viajes v ON vg.viaje_id = v.id WHERE v.chofer_id = ? AND vg.pagado_por = 'adelanto' AND vg.activo = 1), 0)
        -
        COALESCE((SELECT SUM(va.monto) FROM viajes_adelantos va JOIN viajes v ON va.viaje_id = v.id WHERE v.chofer_id = ? AND va.activo = 1), 0)
    )";
    $st_s = $pdo->prepare($sql_s);
    $st_s->execute([$ch_id, $ch_id, $ch_id, $ch_id]);
    $saldo_total_choferes += $st_s->fetchColumn();
}
?>

<div style="margin-bottom: 30px;">
    <h1>Tesorería y Conciliación</h1>
    <p style="opacity: 0.7;">Análisis consolidado de caja y saldos pendientes de la empresa.</p>
</div>

<div class="stats-grid">
    <div class="stat-card" style="border-top-color: #2ecc71;">
        <p>Efectivo / Banco Real</p>
        <h3 style="color: #2ecc71;"><?= formatMoney($saldo_caja) ?></h3>
        <small>Dinero disponible en mano</small>
    </div>
    <div class="stat-card" style="border-top-color: #3498db;">
        <p>Pendiente de Cobro</p>
        <h3><?= formatMoney($por_cobrar) ?></h3>
        <small>Deuda de Clientes (Activo)</small>
    </div>
    <div class="stat-card" style="border-top-color: #e74c3c;">
        <p>Pasivo con Choferes</p>
        <h3 style="color: #e74c3c;"><?= formatMoney($saldo_total_choferes) ?></h3>
        <small>Deuda devengada (Pasivo)</small>
    </div>
</div>

<div class="responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;">
    <div class="card">
        <h3><i class="fas fa-wallet" style="color: #2ecc71;"></i> Ingresos (Últimos Cobros)</h3>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Cliente</th><th style="text-align:right">Monto</th></tr></thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("SELECT fecha_cobro, c.razon_social, total_flete_neto FROM viajes v JOIN clientes c ON v.cliente_id = c.id WHERE v.transportista_id = ? AND estado IN ('cobrado', 'liquidado') ORDER BY v.fecha_cobro DESC LIMIT 8");
                    $stmt->execute([$active_company_id]);
                    foreach($stmt->fetchAll() as $i): ?>
                    <tr><td><?= formatDate($i['fecha_cobro']) ?></td><td><?= htmlspecialchars($i['razon_social']) ?></td><td style="text-align:right; font-weight:bold;"><?= formatMoney($i['total_flete_neto']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3><i class="fas fa-money-bill-wave" style="color: #e74c3c;"></i> Egresos (Últimas Salidas)</h3>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Concepto</th><th style="text-align:right">Monto</th></tr></thead>
                <tbody>
                    <?php
                    $sql_out = "(SELECT cp.fecha, CONCAT('Chofer: ', c.apellido) as concepto, cp.monto FROM chofer_pagos cp JOIN choferes c ON cp.chofer_id = c.id WHERE c.transportista_id = ? AND cp.tipo != 'liquidacion') UNION (SELECT g.fecha, CONCAT('Gasto: ', g.tipo_gasto) as concepto, g.monto FROM viajes_gastos g JOIN viajes v ON g.viaje_id = v.id WHERE v.transportista_id = ? AND g.pagado_por = 'empresa' AND g.activo = 1) UNION (SELECT m.fecha, CONCAT('Manto: ', v.dominio) as concepto, m.costo_total as monto FROM mantenimientos m JOIN vehiculos v ON m.vehiculo_id = v.id WHERE v.transportista_id = ?) ORDER BY fecha DESC LIMIT 8";
                    $stmt = $pdo->prepare($sql_out);
                    $stmt->execute([$active_company_id, $active_company_id, $active_company_id]);
                    foreach($stmt->fetchAll() as $o): ?>
                    <tr><td><?= formatDate($o['fecha']) ?></td><td><?= htmlspecialchars($o['concepto']) ?></td><td style="text-align:right; color:#e74c3c; font-weight:bold;">- <?= formatMoney($o['monto']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>