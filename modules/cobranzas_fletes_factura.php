<?php
/**
 * Modulo: Cobranzas - Fletes con Factura
 * Muestra viajes facturados (con factura_nro) para revisión/gestión.
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;

function viajeDocLabel(array $v): string {
    if (!empty($v['ctg_nro'])) return 'CTG ' . $v['ctg_nro'];
    if (!empty($v['carta_porte_nro'])) return 'CP ' . $v['carta_porte_nro'];
    if (!empty($v['otros_docs'])) return (string)$v['otros_docs'];
    return 'Viaje #' . (int)$v['id'];
}

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$where = " WHERE v.transportista_id = ? AND v.activo = 1 AND v.estado = 'facturado' AND v.factura_nro IS NOT NULL AND v.factura_nro <> '' ";
$params = [$active_company_id];

if (!empty($desde)) {
    $where .= " AND v.factura_fecha >= ? ";
    $params[] = $desde;
}
if (!empty($hasta)) {
    $where .= " AND v.factura_fecha <= ? ";
    $params[] = $hasta;
}

$sql = "SELECT v.id, v.factura_nro, v.factura_fecha, v.total_flete_neto, v.fecha_cobro,
               v.ctg_nro, v.carta_porte_nro, v.otros_docs,
               c.razon_social AS cliente_nombre, p.razon_social AS pagador_nombre,
               v.estado
        FROM viajes v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN clientes p ON p.id = v.pagador_id
        {$where}
        ORDER BY v.factura_fecha DESC, v.id DESC
        LIMIT 300";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $viajes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar facturas: " . $e->getMessage();
    $viajes = [];
}

?>

<div class="card" style="display:flex; flex-direction:column; gap:12px;">
    <h2 style="margin:0; border-bottom:2px solid var(--accent); padding-bottom:10px;">
        <i class="fas fa-file-invoice"></i> Fletes con Factura
    </h2>

    <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:10px; align-items:end;">
        <div class="form-group">
            <label>Desde</label>
            <input type="date" name="desde" class="input-field" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="form-group">
            <label>Hasta</label>
            <input type="date" name="hasta" class="input-field" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn-secondary" type="button" onclick="window.location.href='cobranzas_fletes_factura'">
                <i class="fas fa-eraser"></i> Limpiar
            </button>
            <button class="btn-primary" type="submit">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($viajes)): ?>
        <p style="text-align:center; padding:20px; opacity:0.6; margin:0;">No hay facturas registradas en este rango.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doc</th>
                        <th>Cliente / Pagador</th>
                        <th>Factura</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Total Neto</th>
                        <th style="text-align:center;">Abrir</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes as $v):
                    $doc = viajeDocLabel($v);
                    $estado = $v['estado'] ?? '';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($doc) ?></td>
                        <td>
                            <div style="font-weight:bold;"><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></div>
                            <div style="opacity:0.75; font-size:0.9rem;">Pagador: <?= htmlspecialchars($v['pagador_nombre'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($v['factura_nro'] ?? '-') ?></div>
                            <div style="opacity:0.75; font-size:0.9rem;"><?= htmlspecialchars($v['factura_fecha'] ?? '-') ?></div>
                        </td>
                        <td>
                            <?php if ($estado === 'facturado'): ?>
                                <span class="badge" style="background:#9b59b6;color:#fff;">📄 Facturado</span>
                            <?php elseif ($estado === 'cobrado'): ?>
                                <span class="badge" style="background:#27ae60;color:#fff;">💰 Cobrado</span>
                            <?php else: ?>
                                <span class="badge"><?= htmlspecialchars($estado) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:bold;">
                            $ <?= number_format((float)($v['total_flete_neto'] ?? 0), 2, ',', '.') ?>
                        </td>
                        <td style="text-align:center;">
                            <a class="btn-primary btn-sm" style="background:var(--accent); display:inline-block;" href="viajes_detalle?viaje_id=<?= (int)$v['id'] ?>" title="Abrir detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

