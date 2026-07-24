<?php
$active_company_id = $_SESSION['active_company_id'] ?? 0;

// --- MÉTRICAS ACCIONABLES (ESTADOS) ---

// 1. Viajes en curso (Sin descargar)
$v_en_viaje = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'en_viaje' AND activo = 1");
$v_en_viaje->execute([$active_company_id]);
$count_en_viaje = $v_en_viaje->fetchColumn();

// 2. Pendientes de Facturar (Descargados)
$v_desc = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'descargado' AND activo = 1");
$v_desc->execute([$active_company_id]);
$count_por_facturar = $v_desc->fetchColumn();

// 3. Pendientes de Cobro (Facturados)
$v_fact = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'facturado' AND activo = 1");
$v_fact->execute([$active_company_id]);
$count_por_cobrar = $v_fact->fetchColumn();

// 4. Pendientes de Liquidar al Chofer (Cualquier viaje descargado no acreditado)
$v_liq = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado != 'en_viaje' AND acreditado_chofer = 0 AND activo = 1");
$v_liq->execute([$active_company_id]);
$count_por_liquidar = $v_liq->fetchColumn();

// 5. Monto total de Cuentas por Cobrar
$monto_cxc = $pdo->prepare("SELECT COALESCE(SUM(total_flete_neto), 0) FROM viajes WHERE transportista_id = ? AND estado = 'facturado' AND activo = 1");
$monto_cxc->execute([$active_company_id]);
$total_cxc = $monto_cxc->fetchColumn();

// 6. Viajes realizados en el mes y semana en curso
$viajes_mes = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND MONTH(fecha_carga) = MONTH(CURDATE()) AND YEAR(fecha_carga) = YEAR(CURDATE()) AND activo = 1");
$viajes_mes->execute([$active_company_id]);
$count_viajes_mes = $viajes_mes->fetchColumn();

$viajes_semana = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND fecha_carga >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND activo = 1");
$viajes_semana->execute([$active_company_id]);
$count_viajes_semana = $viajes_semana->fetchColumn();

// --- ALERTAS DE VENCIMIENTOS (Próximos 30 días) ---
$venc_vtv = $pdo->prepare("SELECT COUNT(*) FROM vehiculos WHERE transportista_id = ? AND vtv_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$venc_vtv->execute([$active_company_id]);
$alertas_vtv = $venc_vtv->fetchColumn();

$venc_lic = $pdo->prepare("SELECT COUNT(*) FROM choferes WHERE transportista_id = ? AND activo = 1 AND vencimiento_licencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$venc_lic->execute([$active_company_id]);
$alertas_lic = $venc_lic->fetchColumn();

// --- ACTIVIDAD RECIENTE ---
$stmt = $pdo->prepare("SELECT id, fecha_carga as fecha, origen, destino, estado, carta_porte_nro, total_flete_bruto FROM viajes WHERE transportista_id = ? AND activo = 1 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$active_company_id]);
$ultimos_movs = $stmt->fetchAll();

// --- AGENDA DE PAGOS ---
$stmt_agenda = $pdo->prepare("
    SELECT v.id, v.factura_nro, v.factura_fecha, v.total_flete_neto, c.razon_social AS cliente
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE v.transportista_id = ?
      AND v.estado = 'facturado'
      AND v.activo = 1
    ORDER BY v.factura_fecha ASC
    LIMIT 8
");
$stmt_agenda->execute([$active_company_id]);
$agenda_pagos = $stmt_agenda->fetchAll();
?>

<!-- ─── HEADER ─── -->
<div class="dashboard-header">
    <div class="header-card">
        <h1><i class="fas fa-chart-pie"></i> Panel de Control</h1>
        <p>Estado actual de la operativa · <?= date('d/m/Y') ?></p>
    </div>
</div>

<!-- ─── ALERTAS ─── -->
<?php if ($alertas_vtv > 0 || $alertas_lic > 0): ?>
    <div style="margin-bottom: 20px;">
        <?php if ($alertas_vtv > 0): ?>
            <div class="modern-alert danger">
                <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="alert-text">
                    Hay <strong><?= $alertas_vtv ?> unidad(es)</strong> con VTV vencida o por vencer en los próximos 30 días.
                    <a href="vehiculos" class="alert-link">Revisar flota</a>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($alertas_lic > 0): ?>
            <div class="modern-alert warning">
                <div class="alert-icon"><i class="fas fa-id-card"></i></div>
                <div class="alert-text">
                    Hay <strong><?= $alertas_lic ?> chofer(es)</strong> con licencia próxima a vencer.
                    <a href="choferes" class="alert-link">Ver legajos</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ─── STATS GRID ─── -->
<div class="stats-grid-modern">
    <a href="viajes" class="stat-card-modern blue">
        <i class="fas fa-truck-moving stat-bg-icon"></i>
        <div class="stat-top">
            <div class="stat-icon-wrap"><i class="fas fa-truck"></i></div>
            <span class="stat-badge">Operativos</span>
        </div>
        <div class="stat-value"><?= $count_en_viaje ?></div>
        <div class="stat-label">En Viaje</div>
        <div class="stat-footer"><i class="fas fa-circle"></i> Pendientes de descarga</div>
    </a>

    <a href="cobranzas" class="stat-card-modern orange">
        <i class="fas fa-file-invoice stat-bg-icon"></i>
        <div class="stat-top">
            <div class="stat-icon-wrap"><i class="fas fa-file-invoice"></i></div>
            <span class="stat-badge">Pendientes</span>
        </div>
        <div class="stat-value"><?= $count_por_facturar ?></div>
        <div class="stat-label">Por Facturar</div>
        <div class="stat-footer"><i class="fas fa-circle"></i> Descargas confirmadas</div>
    </a>

    <a href="cobranzas" class="stat-card-modern green">
        <i class="fas fa-hand-holding-usd stat-bg-icon"></i>
        <div class="stat-top">
            <div class="stat-icon-wrap"><i class="fas fa-hand-holding-usd"></i></div>
            <span class="stat-badge">Pendientes</span>
        </div>
        <div class="stat-value"><?= $count_por_cobrar ?></div>
        <div class="stat-label">Por Cobrar</div>
        <div class="stat-footer"><i class="fas fa-circle"></i> Facturas emitidas</div>
    </a>

    <div class="stat-card-modern purple" style="cursor: default;">
        <i class="fas fa-calendar-alt stat-bg-icon"></i>
        <div class="stat-top">
            <div class="stat-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
            <span class="stat-badge"><?= date('M') ?></span>
        </div>
        <div class="stat-value"><?= $count_viajes_mes ?></div>
        <div class="stat-label">Viajes del Mes</div>
        <div class="stat-footer"><i class="fas fa-circle"></i> Última semana: <?= $count_viajes_semana ?></div>
    </div>

    <div class="stat-card-modern teal" style="cursor: default;">
        <i class="fas fa-coins stat-bg-icon"></i>
        <div class="stat-top">
            <div class="stat-icon-wrap"><i class="fas fa-coins"></i></div>
            <span class="stat-badge">CxC</span>
        </div>
        <div class="stat-value"><?= formatMoney($total_cxc) ?></div>
        <div class="stat-label">Cuentas por Cobrar</div>
        <div class="stat-footer"><i class="fas fa-circle"></i> <?= $count_por_cobrar ?> factura(s) pendiente(s)</div>
    </div>
</div>

<!-- ─── CONTENIDO: Actividad + Agenda ─── -->
<div class="dashboard-grid">
    <!-- Actividad Reciente -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3><i class="fas fa-history"></i> Actividad de Viajes Recientes</h3>
            <a href="viajes" class="card-link">Ver todos <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <?php if(empty($ultimos_movs)): ?>
                <div class="dash-card-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No hay actividad reciente para mostrar.</p>
                </div>
            <?php else: ?>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>C. Porte / Ref</th>
                            <th>Ruta</th>
                            <th style="text-align:right">Importe</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($ultimos_movs as $m): ?>
                        <tr>
                            <td class="ref-cell"><?= htmlspecialchars($m['carta_porte_nro'] ?: '#'.$m['id']) ?></td>
                            <td class="route-cell">
                                <?= htmlspecialchars($m['origen']) ?>
                                <i class="fas fa-arrow-right"></i>
                                <?= htmlspecialchars($m['destino']) ?>
                            </td>
                            <td class="amount-cell"><?= formatMoney($m['total_flete_bruto']) ?></td>
                            <td><span class="status-badge <?= $m['estado'] ?>"><?= str_replace('_', ' ', $m['estado']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Agenda de Pagos -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Agenda de Pagos</h3>
            <a href="cobranzas" class="card-link">Ir a Cobranzas <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="dash-card-body">
            <?php if(empty($agenda_pagos)): ?>
                <div class="dash-card-empty">
                    <i class="fas fa-check-circle"></i>
                    <p>No hay pagos pendientes en agenda.</p>
                </div>
            <?php else: ?>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>F. Factura</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($agenda_pagos as $a): ?>
                        <tr>
                            <td class="ref-cell"><?= htmlspecialchars($a['factura_nro'] ?: '#'.$a['id']) ?></td>
                            <td class="client-cell"><?= htmlspecialchars($a['cliente'] ?? '-') ?></td>
                            <td class="date-cell"><?= formatDate($a['factura_fecha']) ?></td>
                            <td class="amount-cell"><?= formatMoney($a['total_flete_neto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>