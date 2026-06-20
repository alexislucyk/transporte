<?php
$active_company_id = $_SESSION['active_company_id'] ?? 0;

// --- MÉTRICAS ACCIONABLES (ESTADOS) ---

// 1. Viajes en curso (Sin descargar)
$v_en_viaje = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'en_viaje'");
$v_en_viaje->execute([$active_company_id]);
$count_en_viaje = $v_en_viaje->fetchColumn();

// 2. Pendientes de Facturar (Descargados)
$v_desc = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'descargado'");
$v_desc->execute([$active_company_id]);
$count_por_facturar = $v_desc->fetchColumn();

// 3. Pendientes de Cobro (Facturados)
$v_fact = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado = 'facturado'");
$v_fact->execute([$active_company_id]);
$count_por_cobrar = $v_fact->fetchColumn();

// 4. Pendientes de Liquidar al Chofer (Cualquier viaje descargado no acreditado)
$v_liq = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND estado != 'en_viaje' AND acreditado_chofer = 0");
$v_liq->execute([$active_company_id]);
$count_por_liquidar = $v_liq->fetchColumn();

// --- ALERTAS DE VENCIMIENTOS (Próximos 30 días) ---
$venc_vtv = $pdo->prepare("SELECT COUNT(*) FROM vehiculos WHERE transportista_id = ? AND vtv_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$venc_vtv->execute([$active_company_id]);
$alertas_vtv = $venc_vtv->fetchColumn();

$venc_lic = $pdo->prepare("SELECT COUNT(*) FROM choferes WHERE transportista_id = ? AND activo = 1 AND vencimiento_licencia <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$venc_lic->execute([$active_company_id]);
$alertas_lic = $venc_lic->fetchColumn();

// --- ACTIVIDAD RECIENTE ---
$stmt = $pdo->prepare("SELECT id, fecha_carga as fecha, origen, destino, estado, carta_porte_nro, total_flete_bruto FROM viajes WHERE transportista_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$active_company_id]);
$ultimos_movs = $stmt->fetchAll();
?>

<div style="margin-bottom: 30px;">
    <h1 style="margin:0;">Panel de Control</h1>
    <p style="margin:5px 0 0 0; opacity: 0.7;">Estado actual de la operativa.</p>
</div>

<style>
    .stat-card { transition: transform 0.2s; border-bottom: none !important; border-top: 4px solid var(--accent); position: relative; cursor: pointer; text-decoration: none; color: inherit; display: block; }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-card h3 { font-size: 1.8rem; margin: 10px 0 5px 0; }
    .stat-card p { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; font-weight: bold; }
    .alert-box { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; }
    .alert-box.danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
</style>

<!-- Sección de Alertas -->
<?php if ($alertas_vtv > 0 || $alertas_lic > 0): ?>
    <div style="margin-bottom: 25px;">
        <?php if ($alertas_vtv > 0): ?>
            <div class="alert-box danger">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <span>Hay <strong><?= $alertas_vtv ?> unidad(es)</strong> con VTV vencida o por vencer en los próximos 30 días. <a href="vehiculos" style="color:inherit; font-weight:bold;">Revisar flota</a></span>
            </div>
        <?php endif; ?>
        <?php if ($alertas_lic > 0): ?>
            <div class="alert-box">
                <i class="fas fa-id-card fa-lg"></i>
                <span>Hay <strong><?= $alertas_lic ?> chofer(es)</strong> con licencia próxima a vencer. <a href="choferes" style="color:inherit; font-weight:bold;">Ver legajos</a></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <a href="viajes" class="stat-card" style="border-top-color: #3498db;">
        <p>En Viaje</p>
        <h3><?= $count_en_viaje ?></h3>
        <small>Pendientes de descarga</small>
    </a>
    <a href="cobranzas" class="stat-card" style="border-top-color: #f39c12;">
        <p>Por Facturar</p>
        <h3><?= $count_por_facturar ?></h3>
        <small>Descargas confirmadas</small>
    </a>
    <a href="cobranzas" class="stat-card" style="border-top-color: #2ecc71;">
        <p>Por Cobrar</p>
        <h3><?= $count_por_cobrar ?></h3>
        <small>Facturas emitidas</small>
    </a>
    <a href="cobranzas" class="stat-card" style="border-top-color: #e74c3c;">
        <p>Por Liquidar</p>
        <h3><?= $count_por_liquidar ?></h3>
        <small>Saldos choferes pendientes</small>
    </a>
</div>

<div class="responsive-grid" style="margin-top: 30px;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin:0;"><i class="fas fa-history"></i> Actividad de Viajes Recientes</h3>
            <a href="viajes" style="font-size: 0.85rem; color: var(--accent); text-decoration: none;">Ver todos <i class="fas fa-external-link-alt"></i></a>
        </div>
        
        <?php if(empty($ultimos_movs)): ?>
            <p style="text-align:center; padding: 40px; opacity:0.5;">No hay actividad reciente para mostrar.</p>
        <?php else: ?>
            <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>C. Porte / Ref</th>
                        <th>Ruta</th>
                        <th style="text-align:right">Importe Bruto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($ultimos_movs as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['carta_porte_nro'] ?: '#'.$m['id']) ?></strong></td>
                        <td><small><?= htmlspecialchars($m['origen']) ?> <i class="fas fa-arrow-right" style="font-size: 10px;"></i> <?= htmlspecialchars($m['destino']) ?></small></td>
                        <td style="text-align:right; font-weight:bold;"><?= formatMoney($m['total_flete_bruto']) ?></td>
                        <td><span class="badge" style="background: rgba(0,0,0,0.05); color: var(--text); font-size: 0.7rem;"><?= str_replace('_', ' ', strtoupper($m['estado'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>