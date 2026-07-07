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
            if ($estado !== 'facturado') {
                $error = "Solo se puede cobrar cuando el viaje está 'facturado'.";
            } else {
                $fecha_cobro = $_POST['fecha_cobro'] ?? date('Y-m-d');
                $medio_cobro = trim($_POST['medio_cobro'] ?? '');
                $monto_cobro = (float)($_POST['monto_cobro'] ?? 0);
                $retenciones = (float)($_POST['retenciones'] ?? 0);
                $observaciones_cobro = trim($_POST['observaciones_cobro'] ?? '');

                if ($monto_cobro <= 0) {
                    $error = 'El monto cobrado debe ser mayor a 0.';
                } else {
                    // El modelo actual en viajes_detalle.php guarda:
                    // - fecha_cobro
                    // - estado = cobrado
                    // - observaciones (concatenada)
                    // No asumimos columnas extra para medio/retenciones.
                    $pdo->prepare("
                        UPDATE viajes
                        SET fecha_cobro = ?,
                            estado = 'cobrado',
                            observaciones = CASE
                                WHEN observaciones IS NULL OR observaciones = '' THEN ?
                                ELSE CONCAT(observaciones, ' | ', ?)
                            END
                        WHERE id = ? AND transportista_id = ? AND activo = 1
                    ")->execute([
                        $fecha_cobro,
                        ($observaciones_cobro !== '' ? $observaciones_cobro : "Cobro registrado"),
                        ($observaciones_cobro !== '' ? $observaciones_cobro : "Cobro registrado"),
                        $viaje_id,
                        $active_company_id
                    ]);

                    $mensaje = "Cobro registrado exitosamente.";
                }
            }
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

<div class="card" style="margin-bottom:20px;">
    <h2 style="margin:0 0 10px 0;"><?= $titles['cobranzas'] ?? 'Gestión de Cobranzas' ?></h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin:12px 0;">
        <a class="btn-secondary" href="cobranzas_fletes_pendientes" style="text-decoration:none;">
            <i class="fas fa-hourglass-half"></i> Fletes Pendientes
        </a>
        <a class="btn-secondary" href="cobranzas_fletes_factura" style="text-decoration:none;">
            <i class="fas fa-file-invoice"></i> Fletes a Facturar
        </a>
        <a class="btn-secondary" href="cobranzas_fletes_liquidar" style="text-decoration:none;">
            <i class="fas fa-money-check-alt"></i> Fletes a Cobrar
        </a>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0;"><i class="fas fa-box-open"></i> Viajes Descargados (Facturar)</h3>

    <?php if (empty($viajes_descargados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">No hay viajes descargados para facturar.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Viaje</th>
                        <th>Cliente</th>
                        <th>Chofer</th>
                        <th>Factura</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_descargados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $monto = (float)($v['total_flete_neto'] ?? 0);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['chofer_nombre'] ?? '-') ?></td>
                        <td>
                            —
                        </td>
                        <td style="text-align:right; font-weight:bold;">$ <?= number_format($monto, 2, ',', '.') ?></td>
                        <td style="text-align:center;">
                            <form method="POST" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:center;">
                                <input type="hidden" name="action" value="facturar">
                                <input type="hidden" name="viaje_id" value="<?= (int)$v['id'] ?>">
                                <input type="text" name="factura_nro" class="input-field" placeholder="A 00001-00000001" required style="min-width:220px;">
                                <input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>">
                                <button type="submit" class="btn-primary" style="background:#9b59b6; border:none;">
                                    <i class="fas fa-file-invoice"></i> Facturar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 style="margin-top:0;"><i class="fas fa-wallet"></i> Viajes Facturados (Cobrar)</h3>

    <?php if (empty($viajes_facturados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">No hay viajes facturados para cobrar.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Viaje</th>
                        <th>Cliente</th>
                        <th>Factura</th>
                        <th style="text-align:right;">Monto</th>
                        <th style="text-align:center;">Registrar Cobro</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_facturados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $monto = (float)($v['total_flete_neto'] ?? 0);
                    $factura_nro = $v['factura_nro'] ?? '';
                    $factura_fecha = $v['factura_fecha'] ?? '';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:2px;">
                                <span><strong>N°:</strong> <?= htmlspecialchars($factura_nro !== '' ? $factura_nro : '-') ?></span>
                                <span style="opacity:0.75;"><strong>Fecha:</strong> <?= htmlspecialchars($factura_fecha !== '' ? $factura_fecha : '-') ?></span>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:bold;">$ <?= number_format($monto, 2, ',', '.') ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-start;">
                                <input type="hidden" name="action" value="cobrar">
                                <input type="hidden" name="viaje_id" value="<?= (int)$v['id'] ?>">

                                <input type="number" step="0.01" min="0.01" name="monto_cobro" class="input-field" required
                                       value="<?= number_format($monto, 2, '.', '') ?>" style="width:140px;">

                                <input type="number" step="0.01" min="0" name="retenciones" class="input-field"
                                       value="0" style="width:120px;">

                                <select name="medio_cobro" class="input-field" style="width:170px;">
                                    <option value="">-- Medio --</option>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="otro">Otro</option>
                                </select>

                                <input type="date" name="fecha_cobro" class="input-field" value="<?= date('Y-m-d') ?>">

                                <input type="text" name="observaciones_cobro" class="input-field" placeholder="Observaciones" style="min-width:180px;">

                                <button type="submit" class="btn-primary" style="background:#27ae60; border:none;">
                                    <i class="fas fa-check-double"></i> Cobrar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
