<?php
/**
 * Cobranzas - Fletes Pendientes (Viajes Descargados)
 * 
 * Muestra los viajes en estado 'descargado' que están pendientes de facturar.
 * Cada fila muestra: ctg, cliente, origen-destino, patente, monto a facturar.
 * Acciones:
 *   - Botón "Ver Detalle": abre viajes_detalle?viaje_id=X (solo consulta)
 *   - Botón "Facturar": redirige a cobranzas_fletes_factura?viaje_id=X
 *
 * Multi-tenant: filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$mensaje = '';
$error   = '';

// ─── OBTENER VIAJES DESCARGADOS ──────────────────────────
$stmt = $pdo->prepare("
    SELECT v.*,
           c.razon_social as cliente_nombre,
           CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
           ve.dominio as vehiculo_dominio,
           p.razon_social as pagador_nombre
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN choferes ch ON ch.id = v.chofer_id
    LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
    LEFT JOIN clientes p ON p.id = v.pagador_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'descargado'
    ORDER BY v.fecha_carga DESC, v.id DESC
");
$stmt->execute([$active_company_id]);
$viajes = $stmt->fetchAll();
?>
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #e67e22, #f39c12, #e74c3c); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-hourglass-half" style="color:#e67e22; margin-right:8px;"></i>
                Fletes Pendientes de Facturar
            </h2>
            <div style="margin-top:6px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Viajes descargados listos para emitir factura
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn-secondary" href="cobranzas" style="text-decoration:none; padding:10px 14px;">
                <i class="fas fa-arrow-left"></i> Volver a Cobranzas
            </a>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0;">
        <i class="fas fa-box-open"></i> Viajes Descargados
        <span class="badge" style="background:#e67e22; color:#fff; font-size:0.8rem; padding:4px 10px; margin-left:8px;">
            <?= count($viajes) ?> pendientes
        </span>
    </h3>

    <?php if (empty($viajes)): ?>
        <p style="opacity:0.7; text-align:center; padding:30px;">
            <i class="fas fa-check-circle" style="color:#27ae60; font-size:2rem; display:block; margin-bottom:10px;"></i>
            No hay viajes descargados pendientes de facturar.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Origen → Destino</th>
                        <th>Patente</th>
                        <th style="text-align:right;">TN Desc.</th>
                        <th style="text-align:right;">Monto a Facturar</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $monto = (float)($v['total_flete_neto'] ?? 0);
                    $tn_desc = (float)($v['peso_neto'] ?? 0);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td style="font-size:0.9rem;">
                            <?= htmlspecialchars($v['origen'] ?? '-') ?>
                            <i class="fas fa-arrow-right" style="color:#999; font-size:0.7rem; margin:0 4px;"></i>
                            <?= htmlspecialchars($v['destino'] ?? '-') ?>
                        </td>
                        <td><?= htmlspecialchars($v['vehiculo_dominio'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= number_format($tn_desc, 2, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            $ <?= number_format($monto, 2, ',', '.') ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                                <a class="btn-primary" style="background:#3498db; border:none; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:0.85rem;"
                                   href="viajes_detalle?viaje_id=<?= (int)$v['id'] ?>&from=cobranzas">
                                    <i class="fas fa-eye"></i> Detalle
                                </a>
                                <a class="btn-primary" style="background:#9b59b6; border:none; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:0.85rem;"
                                   href="cobranzas_fletes_factura?viaje_id=<?= (int)$v['id'] ?>">
                                    <i class="fas fa-file-invoice"></i> Facturar
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold; background:#f8f9fa;">
                        <td colspan="5" style="text-align:right;">Totales:</td>
                        <td style="text-align:right; color:#27ae60;">
                            $ <?= number_format(array_sum(array_map(function($v) { return (float)($v['total_flete_neto'] ?? 0); }, $viajes)), 2, ',', '.') ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>