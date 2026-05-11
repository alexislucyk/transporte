<?php
$active_company_id = $_SESSION['active_company_id'] ?? 0;
$primer_dia_mes = date('Y-m-01');

// 1. Viajes este mes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM viajes WHERE transportista_id = ? AND fecha_carga >= ?");
$stmt->execute([$active_company_id, $primer_dia_mes]);
$viajes_mes = $stmt->fetchColumn();

// 2. Choferes Activos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM choferes WHERE transportista_id = ? AND activo = 1");
$stmt->execute([$active_company_id]);
$choferes_activos = $stmt->fetchColumn();

// 3. Gastos en Combustible (Mes actual)
$stmt = $pdo->prepare("SELECT SUM(g.monto) FROM viajes_gastos g JOIN viajes v ON g.viaje_id = v.id WHERE v.transportista_id = ? AND g.tipo_gasto = 'combustible' AND g.fecha >= ?");
$stmt->execute([$active_company_id, $primer_dia_mes]);
$gastos_nafta = $stmt->fetchColumn() ?: 0;

// 4. Últimos movimientos (combinados)
$stmt = $pdo->prepare("SELECT 'viaje' as tipo, id, fecha_carga as fecha, CONCAT(origen, ' -> ', destino) as detalle FROM viajes WHERE transportista_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$active_company_id]);
$ultimos_movs = $stmt->fetchAll();
?>

<h1>Panel de Control</h1>
<p>Resumen general de la operación</p>

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
        <i class="fas fa-gas-pump fa-2x" style="color: #e67e22"></i>
        <h3><?= formatMoney($gastos_nafta) ?></h3>
        <p>Gastos en Combustible</p>
    </div>
</div>

<div class="card">
    <h3>Últimos movimientos</h3>
    <?php if(empty($ultimos_movs)): ?>
        <p>No hay actividad reciente para mostrar.</p>
    <?php else: ?>
        <table class="data-table">
            <?php foreach($ultimos_movs as $m): ?>
                <tr>
                    <td style="width: 40px;"><i class="fas fa-truck-loading" style="color: var(--accent)"></i></td>
                    <td><strong>Nuevo Viaje #<?= $m['id'] ?>:</strong> <?= htmlspecialchars($m['detalle']) ?></td>
                    <td style="text-align: right; opacity: 0.6; font-size: 0.85rem;"><?= formatDate($m['fecha']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>