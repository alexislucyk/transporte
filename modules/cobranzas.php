<?php
/**
 * Modulo Cobranzas - Trans Cargo Hub
 * Flujo:
 * - Desde Viajes solo deja consulta luego de descargar.
 * - Este módulo continúa:
 *    - Facturar: estado descargado -> facturado
 *    - Cobrar: estado facturado -> cobrado
 *
 * Multi-tenant estricto:
 *   - Toda lectura/escritura filtra:
 *       viajes.transportista_id = $_SESSION['active_company_id'] AND viajes.activo=1
 */

$mensaje = '';
$error = '';

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';

function viajesOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $viaje_id = (int)($_POST['viaje_id'] ?? 0);

    if ($viaje_id <= 0 || !viajesOwner($pdo, $viaje_id, $active_company_id, $currentRole)) {
        $error = 'Viaje no encontrado o no pertenece a la empresa activa.';
    } else {
        $stmt = $pdo->prepare("SELECT estado FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
        $stmt->execute([$viaje_id, $active_company_id]);
        $estado = (string)($stmt->fetchColumn() ?: '');

        if ($action === 'facturar') {
            if ($estado !== 'descargado') {
                $error = "Solo se puede facturar cuando el viaje está 'descargado'.";
            } else {
                $factura_nro = trim($_POST['factura_nro'] ?? '');
                $factura_fecha = $_POST['factura_fecha'] ?? date('Y-m-d');

                if ($factura_nro === '') {
                    $error = 'El número de factura es obligatorio.';
                } else {
                    $pdo->prepare("
                        UPDATE viajes
                        SET factura_nro = ?, factura_fecha = ?, estado = 'facturado'
                        WHERE id = ? AND transportista_id = ? AND activo = 1
                    ")->execute([$factura_nro, $factura_fecha, $viaje_id, $active_company_id]);

                    $mensaje = "Viaje facturado exitosamente. Factura N°: " . htmlspecialchars($factura_nro);
                }
            }
        }

            if ($action === 'cobrar') {
                // Redirigir al módulo detallado de cobros
                $viaje_id_param = (int)($_POST['viaje_id'] ?? 0);
                if ($viaje_id_param > 0) {
                    header("Location: cobranzas_fletes_liquidar?cobrar_viaje_id={$viaje_id_param}");
                    exit;
                }
                $error = 'ID de viaje inválido para redirigir al cobro.';
            }
    }
}

// Listados
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
$viajes_descargados = $stmt->fetchAll();

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
      AND v.estado = 'facturado'
    ORDER BY v.fecha_cobro DESC, v.id DESC
");
$stmt->execute([$active_company_id]);
$viajes_facturados = $stmt->fetchAll();
?>

<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #2c3e50, #3498db, #27ae60, #e67e22); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-wallet" style="color:var(--accent); margin-right:8px;"></i>
                <?= $titles['cobranzas'] ?? 'Gestión de Cobranzas' ?>
            </h2>
            <div style="margin-top:6px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Continuación desde Viajes
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-start;">
            <?php if ($mensaje): ?>
                <span class="badge" style="background:#27ae60; color:#fff; font-size:0.9rem; padding:8px 14px;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
                </span>
            <?php endif; ?>
            <?php if ($error): ?>
                <span class="badge" style="background:#e74c3c; color:#fff; font-size:0.9rem; padding:8px 14px;">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

        
</div>

<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <i class="fas fa-box-open"></i> Viajes Descargados (Facturar)
        <span class="badge" style="background:#e67e22; color:#fff; font-size:0.8rem; padding:4px 10px;">
            <?= count($viajes_descargados) ?> pendientes
        </span>
        <a href="cobranzas_fletes_factura_lote" class="btn-primary" style="background:#9b59b6; border:none; padding:6px 12px; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; text-decoration:none; margin-left:auto;">
            <i class="fas fa-layer-group"></i> Facturar en Lote
        </a>
    </h3>

    <?php if (empty($viajes_descargados)): ?>
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
                <?php foreach ($viajes_descargados as $v):
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
                                <!-- <a class="btn-primary" style="background:#3498db; border:none; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:0.85rem;"
                                   href="viajes_detalle?viaje_id=<?= (int)$v['id'] ?>">
                                    <i class="fas fa-eye"></i> Detalle
                                </a> -->
                                <a class="btn-primary" style="background:#e67e22; border:none; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:0.85rem;"
                                   href="viajes_detalle?viaje_id=<?= (int)$v['id'] ?>&from=cobranzas">
                                    <i class="fas fa-edit"></i> Editar
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
                            $ <?= number_format(array_sum(array_map(function($v) { return (float)($v['total_flete_neto'] ?? 0); }, $viajes_descargados)), 2, ',', '.') ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <i class="fas fa-wallet" style="color:#9b59b6;"></i> Viajes Facturados (Cobrar)
        <?php if (!empty($viajes_facturados)): ?>
        <span class="badge" style="background:#9b59b6; color:#fff; font-size:0.8rem; padding:4px 10px;">
            <?= count($viajes_facturados) ?> pendientes
        </span>
        <?php endif; ?>
        <a href="cobranzas_fletes_cobro_lote" class="btn-primary" style="background:#27ae60; border:none; padding:6px 12px; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; text-decoration:none; margin-left:auto;">
            <i class="fas fa-check-double"></i> Cobrar en Lote
        </a>
    </h3>

    <?php if (empty($viajes_facturados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">
            <i class="fas fa-check-circle" style="color:#27ae60; font-size:1.5rem; display:block; margin-bottom:8px;"></i>
            No hay viajes facturados pendientes de cobro.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Origen → Destino</th>
                        <th>Factura N°</th>
                        <th style="text-align:right;">Neto</th>
                        <th style="text-align:right;">Total Facturado</th>
                        <th>Fecha Fact.</th>
                        <th>Días</th>
                        <th style="text-align:center;">Cobrar</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $sum_neto = 0;
                $sum_total = 0;
                foreach ($viajes_facturados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $neto    = (float)($v['total_flete_neto'] ?? 0);
                    $total_fact = $neto * 1.21; // IVA 21% incluido
                    $factura_nro = $v['factura_nro'] ?? '';
                    $factura_fecha = $v['factura_fecha'] ?? '';
                    
                    // Calcular días desde facturación
                    $dias_desde = '';
                    $dias_color = '';
                    if ($factura_fecha !== '' && $factura_fecha !== null) {
                        try {
                            $fecha_fact_dt = new DateTime($factura_fecha);
                            $hoy = new DateTime();
                            $diff = $fecha_fact_dt->diff($hoy);
                            $dias_desde = (int)$diff->days;
                            if ($dias_desde <= 7) $dias_color = '#27ae60';
                            elseif ($dias_desde <= 15) $dias_color = '#e67e22';
                            elseif ($dias_desde <= 30) $dias_color = '#e74c3c';
                            else $dias_color = '#8e44ad';
                        } catch (Exception $e) {
                            $dias_desde = '-';
                        }
                    }
                    
                    $sum_neto += $neto;
                    $sum_total += $total_fact;
                ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td style="font-size:0.85rem;">
                            <?= htmlspecialchars($v['origen'] ?? '-') ?>
                            <i class="fas fa-arrow-right" style="color:#999; font-size:0.65rem; margin:0 3px;"></i>
                            <?= htmlspecialchars($v['destino'] ?? '-') ?>
                        </td>
                        <td>
                            <span style="font-weight:bold; font-size:0.9rem;"><?= htmlspecialchars($factura_nro ?: '-') ?></span>
                        </td>
                        <td style="text-align:right;">$ <?= number_format($neto, 2, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            $ <?= number_format($total_fact, 2, ',', '.') ?>
                        </td>
                        <td><?= htmlspecialchars(formatDate($factura_fecha)) ?></td>
                        <td style="text-align:center;">
                            <?php if ($dias_desde !== ''): ?>
                                <span style="display:inline-block; padding:2px 8px; border-radius:12px; font-weight:bold; font-size:0.8rem; background:<?= $dias_color ?>20; color:<?= $dias_color ?>;">
                                    <?= $dias_desde ?>d
                                </span>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                                <a class="btn-primary" style="background:#e67e22; border:none; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:0.85rem;"
                                   href="cobranzas_fletes_factura?viaje_id=<?= (int)$v['id'] ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="cobranzas_fletes_liquidar?cobrar_viaje_id=<?= (int)$v['id'] ?>"
                                   class="btn-primary" style="background:#27ae60; border:none; padding:8px 14px; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
                                    <i class="fas fa-check-double"></i> Cobrar
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold; background:#f8f9fa;">
                        <td colspan="4" style="text-align:right;">Totales:</td>
                        <td style="text-align:right;">$ <?= number_format($sum_neto, 2, ',', '.') ?></td>
                        <td style="text-align:right; color:#27ae60;">$ <?= number_format($sum_total, 2, ',', '.') ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
