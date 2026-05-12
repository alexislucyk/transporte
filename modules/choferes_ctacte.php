<?php
$choferId = $params[0];
$stmt = $pdo->prepare("SELECT * FROM choferes WHERE id = ?");
$stmt->execute([$choferId]);
$chofer = $stmt->fetch();

if (!$chofer) die("Chofer no encontrado.");

// Lógica para registrar un pago manual desde aquí
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $stmt = $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$choferId, $_POST['fecha'], $_POST['monto'], $_POST['tipo'], $_POST['detalle']]);
    header("Location: " . $base_path . "choferes/ctacte/" . $choferId); exit;
}

// Lógica para acreditar flete (Genera movimiento contable sin cerrar el viaje)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acreditar_flete') {
    $vId = $_POST['viaje_id'];
    
    $pdo->beginTransaction();
    try {
        $stmtV = $pdo->prepare("SELECT id, total_flete_neto, chofer_porcentaje, origen, destino, carta_porte_nro FROM viajes WHERE id = ?");
        $stmtV->execute([$vId]);
        $v = $stmtV->fetch();

        $monto = round(($v['total_flete_neto'] * $v['chofer_porcentaje']) / 100, 2);
        $refCP = $v['carta_porte_nro'] ? "CP: {$v['carta_porte_nro']}" : "Viaje #{$v['id']}";
        $detalle = "Acreditación Flete {$refCP} - {$v['origen']}/{$v['destino']} ({$v['chofer_porcentaje']}%)";

        // 1. Insertar el crédito real en la tabla de pagos
        $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, 'liquidacion', ?)")
            ->execute([$choferId, date('Y-m-d'), $monto, $detalle]);

        // 2. Marcar el viaje como ya acreditado
        $pdo->prepare("UPDATE viajes SET acreditado_chofer = 1 WHERE id = ?")->execute([$vId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }

    header("Location: " . $base_path . "choferes/ctacte/" . $choferId); exit;
}

// Consolidar movimientos: Viajes (ganancia) + Pagos/Adelantos
$movimientos = [];

// 2. Traer Adelantos de Viajes específicos (DEBE)
$adelantosViaje = $pdo->prepare("SELECT va.fecha, CONCAT('Adelanto Viaje #', v.id) as concepto, 0 as haber, va.monto as debe FROM viajes_adelantos va JOIN viajes v ON va.viaje_id = v.id WHERE v.chofer_id = ?");
$adelantosViaje->execute([$choferId]);
$movimientos = array_merge($movimientos, $adelantosViaje->fetchAll());

// 3. Traer Gastos rendidos con el adelanto (HABER - Justificación de fondos)
$gastosRendidos = $pdo->prepare("SELECT vg.fecha, CONCAT('Gasto Justificado Viaje #', v.id, ': ', vg.tipo_gasto) as concepto, vg.monto as haber, 0 as debe FROM viajes_gastos vg JOIN viajes v ON vg.viaje_id = v.id WHERE v.chofer_id = ? AND vg.pagado_por = 'adelanto'");
$gastosRendidos->execute([$choferId]);
$movimientos = array_merge($movimientos, $gastosRendidos->fetchAll());

// 4. Traer Movimientos de chofer_pagos (Pagos de Sueldos o Adelantos Grales)
$pagosGenerales = $pdo->prepare("SELECT fecha, tipo, detalle, monto FROM chofer_pagos WHERE chofer_id = ?");
$pagosGenerales->execute([$choferId]);
foreach($pagosGenerales->fetchAll() as $p) {
    $movimientos[] = [
        'fecha' => $p['fecha'],
        'concepto' => strtoupper($p['tipo']) . ": " . $p['detalle'],
        'haber' => ($p['tipo'] === 'liquidacion') ? $p['monto'] : 0,
        'debe' => ($p['tipo'] !== 'liquidacion') ? $p['monto'] : 0
    ];
}

// Ordenar por fecha
usort($movimientos, function($a, $b) { return strtotime($a['fecha']) - strtotime($b['fecha']); });

// 5. Traer Viajes Pendientes de Acreditación (que ya están descargados pero no impactaron en Cta Cte)
$pendientes_query = $pdo->prepare("SELECT * FROM viajes WHERE chofer_id = ? AND estado != 'en_viaje' AND acreditado_chofer = 0 ORDER BY fecha_carga DESC");
$pendientes_query->execute([$choferId]);
$pendientes = $pendientes_query->fetchAll();
?>

<div style="margin-bottom: 25px;">
    <a href="choferes" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; display: inline-block; margin-bottom: 10px;">
        <i class="fas fa-arrow-left"></i> Volver al listado
    </a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin:0;">Cuenta Corriente</h1>
            <p style="margin:5px 0 0 0; opacity: 0.7;">Chofer: <strong><?= htmlspecialchars($chofer['apellido'] . ", " . $chofer['nombre']) ?></strong></p>
        </div>
        <button onclick="openModal('modal-pago')" class="btn-primary" style="padding: 12px 24px; font-size: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <i class="fas fa-hand-holding-usd"></i> Registrar Movimiento
        </button>
    </div>
</div>

<?php if (!empty($pendientes)): ?>
<div class="card" style="margin-bottom: 30px; border-top: 4px solid #f39c12;">
    <h3 style="margin-top: 0;"><i class="fas fa-clock"></i> Fletes Pendientes de Acreditar</h3>
    <p style="font-size: 0.9rem; opacity: 0.8;">Viajes con kilos confirmados que aún no se han sumado al saldo del chofer.</p>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>C. Porte</th>
                <th>Ruta</th>
                <th style="text-align:right">Flete Neto</th>
                <th style="text-align:right">Ganancia (<?= $chofer['porcentaje_ganancia'] ?>%)</th>
                <th style="text-align:center">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pendientes as $vp): 
                $ganancia = ($vp['total_flete_neto'] * $vp['chofer_porcentaje']) / 100;
            ?>
            <tr>
                <td><?= formatDate($vp['fecha_carga']) ?></td>
                <td><strong><?= htmlspecialchars($vp['carta_porte_nro'] ?: '-') ?></strong></td>
                <td><?= htmlspecialchars($vp['origen'] . " -> " . $vp['destino']) ?></td>
                <td style="text-align:right"><?= formatMoney($vp['total_flete_neto']) ?></td>
                <td style="text-align:right; font-weight:bold; color: #2ecc71;"><?= formatMoney($ganancia) ?></td>
                <td style="text-align:center">
                    <button onclick="confirmarAcreditacion(<?= $vp['id'] ?>, '<?= formatMoney($ganancia) ?>', '<?= htmlspecialchars($vp['carta_porte_nro'] ?: 'S/N') ?>')" class="btn-primary" style="background:var(--accent); padding: 5px 12px; font-size: 0.85rem;">Acreditar Flete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th style="text-align:right">Debe (Retiros/Pagos)</th>
                <th style="text-align:right">Haber (Ganancia Flete)</th>
                <th style="text-align:right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $saldo = 0;
            foreach($movimientos as $m): 
                $saldo += ($m['haber'] - $m['debe']);
            ?>
            <tr>
                <td><?= formatDate($m['fecha']) ?></td>
                <td><?= htmlspecialchars($m['concepto']) ?></td>
                <td style="text-align:right; color: #e74c3c"><?= $m['debe'] > 0 ? formatMoney($m['debe']) : '-' ?></td>
                <td style="text-align:right; color: #2ecc71"><?= $m['haber'] > 0 ? formatMoney($m['haber']) : '-' ?></td>
                <td style="text-align:right; font-weight:bold"><?= formatMoney($saldo) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal para Registrar Pago Manual -->
<div id="modal-pago" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="margin:0;"><i class="fas fa-wallet"></i> Nuevo Pago o Adelanto</h3>
            <span class="close-modal" onclick="closeModal('modal-pago')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="registrar_pago" value="1">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Tipo de Movimiento</label>
                        <select name="tipo" class="input-field">
                            <option value="adelanto">Adelanto General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monto ($)</label>
                        <input type="number" step="0.01" name="monto" class="input-field" placeholder="0.00" required>
                    </div>
                </div>
                <div class="form-group"><label>Fecha de Operación</label><input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Detalle u Observación</label><input type="text" name="detalle" class="input-field" placeholder="Ej: Pago quincena de Enero o Adelanto para gastos"></div>
            </div>
            <div class="modal-footer" style="background: none; border: none; padding-bottom: 25px;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-pago')">Cancelar</button>
                <button type="submit" class="btn-primary">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<form id="form-acreditar-flete" method="POST" style="display:none;">
    <input type="hidden" name="action" value="acreditar_flete">
    <input type="hidden" name="viaje_id" id="acreditar_viaje_id">
</form>

<script>
function confirmarAcreditacion(id, monto, cp) {
    appConfirm(`¿Deseas acreditar ${monto} al chofer por la Carta de Porte ${cp}?`, function() {
        document.getElementById('acreditar_viaje_id').value = id;
        document.getElementById('form-acreditar-flete').submit();
    }, "Acreditar Ganancia");
}
</script>