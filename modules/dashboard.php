<?php
$active_company_id = $_SESSION['active_company_id'] ?? 0;
$primer_dia_mes = date('Y-m-01');

// --- MÉTRICAS OPERATIVAS ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND fecha_carga >= ?");
$stmt->execute([$active_company_id, $primer_dia_mes]);
$viajes_mes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM choferes WHERE transportista_id = ? AND activo = 1");
$stmt->execute([$active_company_id]);
$choferes_activos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM vehiculos WHERE transportista_id = ?");
$stmt->execute([$active_company_id]);
$vehiculos_total = $stmt->fetchColumn();

// --- MÉTRICAS FINANCIERAS ---
// Pendiente de Facturar (Viajes descargados)
$stmt = $pdo->prepare("SELECT SUM(total_flete_neto) FROM viajes WHERE transportista_id = ? AND estado = 'descargado'");
$stmt->execute([$active_company_id]);
$pend_factura = $stmt->fetchColumn() ?: 0;

// Pendiente de Cobro (Facturas emitidas)
$stmt = $pdo->prepare("SELECT SUM(total_flete_neto) FROM viajes WHERE transportista_id = ? AND estado = 'facturado'");
$stmt->execute([$active_company_id]);
$pend_cobro = $stmt->fetchColumn() ?: 0;

// Gastos Operativos Empresa del mes
$stmt = $pdo->prepare("SELECT SUM(g.monto) FROM viajes_gastos g JOIN viajes v ON g.viaje_id = v.id WHERE v.transportista_id = ? AND g.pagado_por = 'empresa' AND g.fecha >= ?");
$stmt->execute([$active_company_id, $primer_dia_mes]);
$gastos_mes = $stmt->fetchColumn() ?: 0;

// --- ACTIVIDAD RECIENTE ---
$stmt = $pdo->prepare("SELECT id, fecha_carga as fecha, origen, destino, estado, carta_porte_nro, total_flete_bruto FROM viajes WHERE transportista_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$active_company_id]);
$ultimos_movs = $stmt->fetchAll();
?>

<div style="margin-bottom: 30px;">
    <h1 style="margin:0;">Panel de Control</h1>
    <p style="margin:5px 0 0 0; opacity: 0.7;">Resumen operativo y financiero del mes en curso.</p>
</div>

<style>
    .stat-card { transition: transform 0.2s; border-bottom: none !important; border-top: 4px solid var(--accent); }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-card h3 { font-size: 1.8rem; margin: 10px 0 5px 0; }
    .stat-card p { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; font-weight: bold; }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <i class="fas fa-route fa-2x" style="color: #3498db"></i>
        <h3><?= $viajes_mes ?></h3>
        <p>Viajes este mes</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check fa-2x" style="color: #2ecc71"></i>
        <h3><?= $choferes_activos ?></h3>
        <p>Choferes Activos</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-truck fa-2x" style="color: #9b59b6"></i>
        <h3><?= $vehiculos_total ?></h3>
        <p>Flota Total</p>
    </div>
</div>

<div class="stats-grid" style="margin-top: 20px;">
    <div class="stat-card" style="border-top-color: #f39c12;">
        <i class="fas fa-file-invoice-dollar fa-2x" style="color: #f39c12"></i>
        <h3><?= formatMoney($pend_factura) ?></h3>
        <p>Por Facturar</p>
    </div>
    <div class="stat-card" style="border-top-color: #e67e22;">
        <i class="fas fa-clock fa-2x" style="color: #e67e22"></i>
        <h3><?= formatMoney($pend_cobro) ?></h3>
        <p>Por Cobrar</p>
    </div>
    <div class="stat-card" style="border-top-color: #e74c3c;">
        <i class="fas fa-wallet fa-2x" style="color: #e74c3c"></i>
        <h3><?= formatMoney($gastos_mes) ?></h3>
        <p>Gastos Empresa (Mes)</p>
    </div>
</div>

<div class="responsive-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-top: 30px;">
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
    
    <div class="card" style="background: var(--primary); color: white;">
        <h3>Accesos Rápidos</h3>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
            <a href="viajes" class="btn-primary" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); width: 100%; justify-content: flex-start;">
                <i class="fas fa-plus-circle"></i> Iniciar Nuevo Viaje
            </a>
            <a href="cobranzas" class="btn-primary" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); width: 100%; justify-content: flex-start;">
                <i class="fas fa-file-invoice"></i> Pendientes de Facturar
            </a>
            <a href="mantenimiento" class="btn-primary" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); width: 100%; justify-content: flex-start;">
                <i class="fas fa-tools"></i> Registrar Service
            </a>
        </div>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; opacity: 0.8;">
            <p><i class="fas fa-info-circle"></i> Recuerda verificar los vencimientos de VTV en el módulo de vehículos.</p>
        </div>
    </div>
</div>