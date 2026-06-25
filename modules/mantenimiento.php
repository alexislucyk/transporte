<?php
/**
 * Modulo de Mantenimiento de Vehiculos - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 */

$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function mantenimientoOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT m.id FROM mantenimientos m INNER JOIN vehiculos v ON v.id = m.vehiculo_id WHERE m.id = ? AND v.transportista_id = ?");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];
    $vehiculo_id = (int)($_POST['vehiculo_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $kilometraje = (int)($_POST['kilometraje'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $costo_total = (float)($_POST['costo_total'] ?? 0);
    $proximo_service_km = (int)($_POST['proximo_service_km'] ?? 0);

    $vehiculoOk = false;
    if ($vehiculo_id > 0) {
        $stmt_check = $pdo->prepare("SELECT id FROM vehiculos WHERE id = ? AND transportista_id = ? AND activo = 1");
        $stmt_check->execute([$vehiculo_id, $active_company_id]);
        $vehiculoOk = (bool)$stmt_check->fetchColumn();
    }

    if ($_POST['action'] === 'nuevo') {
        if (!$vehiculoOk) { $error = "Vehiculo invalido."; }
        elseif ($descripcion === '') { $error = "Descripcion obligatoria."; }
        else {
            try {
                $sql = "INSERT INTO mantenimientos (vehiculo_id, fecha, kilometraje, descripcion, costo_total, proximo_service_km, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$vehiculo_id, $fecha, ($kilometraje > 0 ? $kilometraje : null), $descripcion, $costo_total, ($proximo_service_km > 0 ? $proximo_service_km : null), $currentUserId]);
                $mensaje = "Mantenimiento registrado.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !mantenimientoOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado.";
        } else {
            try {
                $pdo->prepare("UPDATE mantenimientos SET activo = 0 WHERE id = ?")->execute([$id]);
                $mensaje = "Registro eliminado (borrado logico).";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$stmt_vh = $pdo->prepare("SELECT id, dominio, marca, modelo, vtv_vencimiento FROM vehiculos WHERE transportista_id = ? AND activo = 1 ORDER BY dominio ASC");
$stmt_vh->execute([$active_company_id]);
$vehiculos = $stmt_vh->fetchAll();

$alertas_vtv = [];
foreach ($vehiculos as $vh) {
    if ($vh['vtv_vencimiento']) {
        $dias = (int)((strtotime($vh['vtv_vencimiento']) - time()) / 86400);
        if ($dias <= 30) {
            $alertas_vtv[] = ['vehiculo' => $vh, 'dias' => $dias];
        }
    }
}

$sql_m = "SELECT m.*, v.dominio, v.marca, v.modelo FROM mantenimientos m INNER JOIN vehiculos v ON v.id = m.vehiculo_id WHERE v.transportista_id = ? AND m.activo = 1 ORDER BY m.fecha DESC, m.id DESC LIMIT 200";
$stmt_m = $pdo->prepare($sql_m);
$stmt_m->execute([$active_company_id]);
$mantenimientos = $stmt_m->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Mantenimiento de Flota</h1>
        <p>Registra y consulta los mantenimientos realizados a los vehiculos.</p>
    </div>
    <button onclick="prepararNuevoMantenimiento()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Mantenimiento
    </button>
</div>

<?php if ($mensaje): ?>
    <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($alertas_vtv)): ?>
<div class="card" style="margin-bottom: 20px; border: 2px solid #f39c12;">
    <h4 style="margin:0 0 12px 0; color:#f39c12;">
        <i class="fas fa-exclamation-triangle"></i> Alertas de Vencimiento VTV
    </h4>
    <ul style="margin:0; padding-left:20px;">
        <?php foreach($alertas_vtv as $a): ?>
        <li style="margin-bottom:4px;">
            <?= htmlspecialchars($a['vehiculo']['dominio']) ?>: 
            <?php if($a['dias'] < 0): ?>
                <span style="color:#e74c3c; font-weight:bold;">Vencido hace <?= abs($a['dias']) ?> dias</span>
            <?php else: ?>
                <span style="color:#f39c12; font-weight:bold;">Vence en <?= $a['dias'] ?> dias</span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($mantenimientos)): ?>
        <p style="text-align:center; padding:40px; opacity:0.5;">No hay registros de mantenimiento.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Vehiculo</th>
                    <th>Kilometraje</th>
                    <th>Descripcion</th>
                    <th>Costo</th>
                    <th>Proximo Service (km)</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($mantenimientos as $m): ?>
                <tr>
                    <td><?= formatDate($m['fecha']) ?></td>
                    <td style="font-family:monospace;"><?= htmlspecialchars($m['dominio']) ?></td>
                    <td><?= $m['kilometraje'] ? number_format($m['kilometraje']) : '-' ?></td>
                    <td><?= htmlspecialchars($m['descripcion']) ?></td>
                    <td><?= $m['costo_total'] > 0 ? formatMoney($m['costo_total']) : '-' ?></td>
                    <td><?= $m['proximo_service_km'] ? number_format($m['proximo_service_km']) : '-' ?></td>
                    <td style="text-align:center">
                        <button onclick="confirmarBorrarMantenimiento(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['dominio'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div id="modal-mantenimiento" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="mantenimiento-modal-title">Registrar Mantenimiento</h3>
            <span class="close-modal" onclick="closeModal('modal-mantenimiento')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="mantenimiento-action" value="nuevo">
                <div class="form-group">
                    <label>Vehiculo *</label>
                    <select name="vehiculo_id" id="mantenimiento-vehiculo" class="input-field" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach($vehiculos as $vh): ?>
                            <option value="<?= (int)$vh['id'] ?>"><?= htmlspecialchars($vh['dominio'] . ' - ' . ($vh['marca'] ?? '') . ' ' . ($vh['modelo'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" id="mantenimiento-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Kilometraje</label>
                    <input type="number" name="kilometraje" id="mantenimiento-km" class="input-field" min="0">
                </div>
                <div class="form-group">
                    <label>Descripcion *</label>
                    <textarea name="descripcion" id="mantenimiento-descripcion" class="input-field" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Costo Total</label>
                    <input type="number" name="costo_total" id="mantenimiento-costo" class="input-field" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Proximo Service (km)</label>
                    <input type="number" name="proximo_service_km" id="mantenimiento-proximo" class="input-field" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-mantenimiento')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<form id="form-borrar-mantenimiento" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-mantenimiento-id">
</form>

<script>
function prepararNuevoMantenimiento() {
    document.getElementById('mantenimiento-modal-title').innerText = "Registrar Nuevo Mantenimiento";
    document.getElementById('mantenimiento-action').value = "nuevo";
    document.querySelector('#modal-mantenimiento form').reset();
    document.getElementById('mantenimiento-fecha').value = '<?= date('Y-m-d') ?>';
    openModal('modal-mantenimiento');
}

function confirmarBorrarMantenimiento(id, dominio) {
    appConfirm("Seguro que deseas eliminar el mantenimiento del vehiculo " + dominio + "?", function() {
        document.getElementById('borrar-mantenimiento-id').value = id;
        document.getElementById('form-borrar-mantenimiento').submit();
    }, "Eliminar Mantenimiento");
}
</script>
