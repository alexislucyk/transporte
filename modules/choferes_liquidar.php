<?php
/**
 * Modulo de Liquidacion de Choferes - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Gestiona la liquidacion de viajes descargados por chofer.
 * Spec: base.md seccion 4.
 */

function choferOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM choferes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

$chofer_id = isset($_GET['chofer_id']) ? (int)$_GET['chofer_id'] : 0;

if ($chofer_id <= 0) {
    $stmt = $pdo->prepare("SELECT id, CONCAT(apellido, ', ', nombre) as nombre FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido, nombre ASC");
    $stmt->execute([$active_company_id]);
    $choferes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, CONCAT(apellido, ', ', nombre) as nombre FROM choferes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$chofer_id, $active_company_id]);
    $chofer = $stmt->fetch();

    if (!$chofer) {
        $error = "Chofer no encontrado o no pertenece a la empresa activa.";
        $chofer = null;
    } else {
        $stmt = $pdo->prepare("SELECT v.id, v.origen, v.destino, v.fecha_carga, v.total_flete_neto, v.chofer_porcentaje, v.estado,
                                      (v.total_flete_neto * v.chofer_porcentaje / 100) as ganancia_chofer
                               FROM viajes v
                               WHERE v.chofer_id = ? AND v.transportista_id = ? AND v.activo = 1
                               AND v.estado IN ('descargado', 'facturado')
                               ORDER BY v.fecha_carga DESC");
        $stmt->execute([$chofer_id, $active_company_id]);
        $viajes = $stmt->fetchAll();

        $total_ganancia = array_sum(array_column($viajes, 'ganancia_chofer'));

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM chofer_pagos WHERE chofer_id = ? AND tipo = 'liquidacion'");
        $stmt->execute([$chofer_id]);
        $total_liquidado = $stmt->fetchColumn();

        $saldo = $total_ganancia - $total_liquidado;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'liquidar' && $chofer_id > 0) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    if ($currentRole === 'developer' || choferOwner($pdo, $chofer_id, $active_company_id, $currentRole)) {
        if ($saldo <= 0) {
            $error = "No hay saldo pendiente para liquidar.";
        } else {
            try {
                $sql = "INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $chofer_id,
                    date('Y-m-d'),
                    $saldo,
                    'liquidacion',
                    'Liquidación de viajes'
                ]);
                $mensaje = "Liquidación registrada exitosamente por $ " . number_format($saldo, 2, ',', '.');
                header("Location: choferes_liquidar?chofer_id=" . $chofer_id . "&msg=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar liquidación: " . $e->getMessage();
            }
        }
    } else {
        $error = "No autorizado.";
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Liquidación de Choferes</h1>
        <p>Gestión de liquidación de viajes por chofer de la empresa activa.</p>
    </div>
    <?php if ($chofer_id > 0 && $chofer): ?>
    <a href="choferes_liquidar" class="btn-secondary" style="text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
    <?php endif; ?>
</div>

<?php if ($mensaje): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($chofer_id <= 0): ?>
<div class="card">
    <p style="text-align:center; padding: 20px;">Seleccione un chofer para ver sus liquidaciones pendientes:</p>
    <div style="max-width: 400px; margin: 0 auto;">
        <form method="GET">
            <div class="form-group">
                <label>Chofer</label>
                <select name="chofer_id" class="input-field" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach($choferes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Ver Liquidación</button>
        </form>
    </div>
</div>
<?php elseif ($chofer): ?>
<div class="card" style="margin-bottom: 20px;">
    <h2 style="margin-top:0;">
        <i class="fas fa-user" style="opacity:0.6;"></i>
        <?= htmlspecialchars($chofer['nombre']) ?>
    </h2>
    <?php if (!empty($viajes)): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
            <small>Ganancia Total (Descargado/Facturado)</small>
            <div style="font-size: 1.3rem; font-weight: bold; color: #27ae60;">$ <?= number_format($total_ganancia, 2, ',', '.') ?></div>
        </div>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
            <small>Ya Liquidado</small>
            <div style="font-size: 1.3rem; font-weight: bold; color: #2980b9;">$ <?= number_format($total_liquidado, 2, ',', '.') ?></div>
        </div>
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border: 1px solid #ffc107;">
            <small>Saldo Pendiente</small>
            <div style="font-size: 1.3rem; font-weight: bold; color: #e67e22;">$ <?= number_format($saldo, 2, ',', '.') ?></div>
        </div>
    </div>
    <?php else: ?>
    <p style="margin-top:15px; opacity:0.6;">No hay viajes pendientes de liquidación para este chofer.</p>
    <?php endif; ?>
</div>

<?php if (!empty($viajes)): ?>
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top:0;">Viajes Pendientes de Liquidación</h3>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha Carga</th>
                <th>Ruta</th>
                <th>Estado</th>
                <th>Flete Neto</th>
                <th>%</th>
                <th>Ganancia Chofer</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($viajes as $v): 
                $estado_badge = match($v['estado']) {
                    'descargado' => '<span class="badge" style="background:#f39c12; color:#fff;">Descargado</span>',
                    'facturado' => '<span class="badge" style="background:#3498db; color:#fff;">Facturado</span>',
                    default => htmlspecialchars($v['estado'])
                };
            ?>
            <tr>
                <td>#<?= (int)$v['id'] ?></td>
                <td><?= htmlspecialchars(formatDate($v['fecha_carga'])) ?></td>
                <td><?= htmlspecialchars($v['origen']) ?> → <?= htmlspecialchars($v['destino']) ?></td>
                <td><?= $estado_badge ?></td>
                <td>$ <?= number_format($v['total_flete_neto'], 2, ',', '.') ?></td>
                <td><?= number_format($v['chofer_porcentaje'], 2) ?>%</td>
                <td style="font-weight:bold; color: #27ae60;">$ <?= number_format($v['ganancia_chofer'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($saldo > 0): ?>
<div class="card">
    <h3 style="margin-top:0;">Registrar Liquidación</h3>
    <p>Se generará un pago de <strong>$ <?= number_format($saldo, 2, ',', '.') ?></strong> al chofer.</p>
    <form method="POST">
        <input type="hidden" name="action" value="liquidar">
        <button type="submit" class="btn-primary">
            <i class="fas fa-check-circle"></i> Liquidar Saldo Pendiente
        </button>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;">Historial de Liquidaciones</h3>
    <?php
    $stmt = $pdo->prepare("SELECT * FROM chofer_pagos WHERE chofer_id = ? AND tipo = 'liquidacion' ORDER BY fecha DESC, id DESC");
    $stmt->execute([$chofer_id]);
    $liqs = $stmt->fetchAll();
    ?>
    <?php if (empty($liqs)): ?>
        <p style="text-align:center; padding: 20px; opacity:0.5;">No hay liquidaciones registradas.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($liqs as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['fecha']) ?></td>
                    <td style="font-weight:bold; color: #27ae60;">$ <?= number_format($l['monto'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($l['detalle'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
