<?php
/**
 * Modulo: Cobranzas - Liquidar (lectura)
 * En este proyecto, la "liquidación" del chofer se gestiona en choferes_liquidar.php.
 * Este módulo funciona como vista de viajes en estado liquidado/cobrado.
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;

function viajeDocLabel(array $v): string {
    if (!empty($v['ctg_nro'])) return 'CTG ' . $v['ctg_nro'];
    if (!empty($v['carta_porte_nro'])) return 'CP ' . $v['carta_porte_nro'];
    if (!empty($v['otros_docs'])) return (string)$v['otros_docs'];
    return 'Viaje #' . (int)$v['id'];
}

$estado_filter = $_GET['estado'] ?? 'liquidado'; // liquidado|cobrado|ambos

$whereEstado = "";
if ($estado_filter === 'cobrado') {
    $whereEstado = " AND v.estado = 'cobrado' ";
} elseif ($estado_filter === 'liquidado') {
    $whereEstado = " AND v.estado = 'liquidado' ";
}

$sql = "SELECT v.id, v.estado, v.total_flete_neto, v.factura_nro, v.fecha_cobro, v.ctg_nro, v.carta_porte_nro, v.otros_docs,
               c.razon_social AS cliente_nombre,
               ch.nombre AS chofer_nombre
        FROM viajes v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN choferes ch ON ch.id = v.chofer_id
        WHERE v.transportista_id = ?
          AND v.activo = 1
          {$whereEstado}
        ORDER BY v.id DESC
        LIMIT 300";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$active_company_id]);
    $viajes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar fletes: " . $e->getMessage();
    $viajes = [];
}

?>

<div class="card" style="display:flex; flex-direction:column; gap:12px;">
    <h2 style="margin:0; border-bottom:2px solid var(--accent); padding-bottom:10px;">
        <i class="fas fa-balance-scale"></i> Liquidación (Vista de Viajes)
    </h2>

    <form method="GET" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label>Mostrar</label>
            <select name="estado" class="input-field" onchange="this.form.submit()">
                <option value="liquidado" <?= $estado_filter === 'liquidado' ? 'selected' : '' ?>>Solo Liquidado</option>
                <option value="cobrado" <?= $estado_filter === 'cobrado' ? 'selected' : '' ?>>Solo Cobrado</option>
                <option value="ambos" <?= $estado_filter === 'ambos' ? 'selected' : '' ?>>Cobrado + Liquidado</option>
            </select>
        </div>
        <div style="opacity:0.75; font-size:0.95rem;">
            <?= count($viajes) ?> resultado(s)
        </div>
    </form>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($viajes)): ?>
        <p style="text-align:center; padding:20px; opacity:0.6; margin:0;">No hay viajes para mostrar.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doc</th>
                        <th>Cliente</th>
                        <th>Chofer</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Total Neto</th>
                        <th style="text-align:center;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes as $v):
                    $doc = viajeDocLabel($v);
                    $estado = $v['estado'] ?? '';
                    $badge = match($estado) {
                        'liquidado' => '<span class="badge" style="background:#95a5a6;color:#fff;">✅ Liquidado</span>',
                        'cobrado' => '<span class="badge" style="background:#27ae60;color:#fff;">💰 Cobrado</span>',
                        default => '<span class="badge">' . htmlspecialchars($estado) . '</span>'
                    };
                ?>
                    <tr>
                        <td><?= htmlspecialchars($doc) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(($v['chofer_nombre'] ?? '-') ?: '-') ?></td>
                        <td><?= $badge ?></td>
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

