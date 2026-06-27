<?php
/**
 * Modulo de Cuenta Corriente de Comisionistas - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Muestra el historial de pagos a comisionistas y permite registrar nuevos.
 */

function comisionistaOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND transportista_id = ? AND es_comisionista = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

$mensaje = isset($_GET['msg']) ? "Pago registrado exitosamente." : "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

$cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

if ($cliente_id <= 0) {
    $stmt = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE transportista_id = ? AND activo = 1 AND es_comisionista = 1 ORDER BY razon_social ASC");
    $stmt->execute([$active_company_id]);
    $comisionistas = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, razon_social FROM clientes WHERE id = ? AND transportista_id = ? AND activo = 1 AND es_comisionista = 1");
    $stmt->execute([$cliente_id, $active_company_id]);
    $comisionista = $stmt->fetch();

    if (!$comisionista) {
        $error = "Comisionista no encontrado o no pertenece a la empresa activa.";
        $comisionista = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM comisionista_pagos WHERE cliente_id = ? ORDER BY fecha DESC, id DESC");
        $stmt->execute([$cliente_id]);
        $pagos = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM comisionista_pagos WHERE cliente_id = ?");
        $stmt->execute([$cliente_id]);
        $total = $stmt->fetchColumn();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo_pago' && $cliente_id > 0) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    if ($currentRole === 'developer' || comisionistaOwner($pdo, $cliente_id, $active_company_id, $currentRole)) {
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $monto = (float)($_POST['monto'] ?? 0);
        $detalle = trim($_POST['detalle'] ?? '');

        if ($monto <= 0) {
            $error = "El monto debe ser mayor a cero.";
        } else {
            try {
                $sql = "INSERT INTO comisionista_pagos (cliente_id, fecha, monto, detalle) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cliente_id, $fecha, $monto, $detalle ?: null]);
                $mensaje = "Pago registrado exitosamente.";
                header("Location: comisionistas_ctacte?cliente_id=" . $cliente_id . "&msg=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar pago: " . $e->getMessage();
            }
        }
    } else {
        $error = "No autorizado.";
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Cuenta Corriente - Comisionistas</h1>
        <p>Registro de pagos a comisionistas de la empresa activa.</p>
    </div>
    <?php if ($cliente_id > 0 && $comisionista): ?>
    <a href="comisionistas" class="btn-secondary" style="text-decoration:none;">
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

<?php if ($cliente_id <= 0): ?>
<div class="card">
    <p style="text-align:center; padding: 20px;">Seleccione un comisionista para ver su cuenta corriente:</p>
    <div style="max-width: 400px; margin: 0 auto;">
        <form method="GET">
            <div class="form-group">
                <label>Comisionista</label>
                <select name="cliente_id" class="input-field" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach($comisionistas as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['razon_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Ver Cuenta Corriente</button>
        </form>
    </div>
</div>
<?php elseif ($comisionista): ?>
<div class="card" style="margin-bottom: 20px;">
    <h2 style="margin-top:0;">
        <i class="fas fa-user" style="opacity:0.6;"></i>
        <?= htmlspecialchars($comisionista['razon_social']) ?>
    </h2>
    <p style="font-size: 1.2rem; margin: 10px 0;">
        Total Pagado: <strong style="color: #27ae60;">$ <?= number_format($total, 2, ',', '.') ?></strong>
    </p>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top:0;">Registrar Nuevo Pago</h3>
    <form method="POST">
        <input type="hidden" name="action" value="nuevo_pago">
        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 140px; margin: 0;">
                <label>Fecha</label>
                <input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="flex: 1; min-width: 140px; margin: 0;">
                <label>Monto $</label>
                <input type="number" step="0.01" name="monto" class="input-field" placeholder="0.00" required>
            </div>
            <div class="form-group" style="flex: 2; min-width: 200px; margin: 0;">
                <label>Detalle</label>
                <input type="text" name="detalle" class="input-field" placeholder="Concepto del pago">
            </div>
            <button type="submit" class="btn-primary" style="height: 38px;">Agregar Pago</button>
        </div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;">Historial de Pagos</h3>
    <?php if (empty($pagos)): ?>
        <p style="text-align:center; padding: 20px; opacity:0.5;">No hay pagos registrados.</p>
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
                <?php foreach($pagos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['fecha']) ?></td>
                    <td style="font-weight:bold; color: #27ae60;">$ <?= number_format($p['monto'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($p['detalle'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
