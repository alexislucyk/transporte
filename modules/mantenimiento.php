<?php
/**
 * Módulo de Mantenimiento de Flota - Trans Cargo Hub
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- PROCESAR ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $vehiculo_id = $_POST['vehiculo_id'];
    $fecha = $_POST['fecha'];
    $km = !empty($_POST['kilometraje']) ? $_POST['kilometraje'] : 0;
    $desc = trim($_POST['descripcion']);
    $costo = !empty($_POST['costo_total']) ? $_POST['costo_total'] : 0;
    $proximo = !empty($_POST['proximo_service_km']) ? $_POST['proximo_service_km'] : null;

    if ($_POST['action'] === 'nuevo') {
        try {
            $sql = "INSERT INTO mantenimientos (vehiculo_id, fecha, kilometraje, descripcion, costo_total, proximo_service_km) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$vehiculo_id, $fecha, $km, $desc, $costo, $proximo]);
            $mensaje = "Registro de mantenimiento guardado.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }

    if ($_POST['action'] === 'editar') {
        try {
            $sql = "UPDATE mantenimientos SET vehiculo_id=?, fecha=?, kilometraje=?, descripcion=?, costo_total=?, proximo_service_km=? WHERE id=?";
            $pdo->prepare($sql)->execute([$vehiculo_id, $fecha, $km, $desc, $costo, $proximo, $_POST['id']]);
            $mensaje = "Registro actualizado correctamente.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// --- OBTENER DATOS ---
// 1. Vehículos de la empresa para el selector
$stmt_v = $pdo->prepare("SELECT id, dominio, marca, modelo FROM vehiculos WHERE transportista_id = ? ORDER BY dominio ASC");
$stmt_v->execute([$active_company_id]);
$lista_vehiculos = $stmt_v->fetchAll();

// 2. Listado de mantenimientos
$sql_m = "SELECT m.*, v.dominio 
          FROM mantenimientos m 
          JOIN vehiculos v ON m.vehiculo_id = v.id 
          WHERE v.transportista_id = ? 
          ORDER BY m.fecha DESC";
$stmt_m = $pdo->prepare($sql_m);
$stmt_m->execute([$active_company_id]);
$mantenimientos = $stmt_m->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Mantenimiento de Flota</h1>
        <p>Historial de reparaciones, cambios de aceite y servicios preventivos.</p>
    </div>
    <button onclick="prepararNuevoManto()" class="btn-primary">
        <i class="fas fa-tools"></i> Nuevo Registro
    </button>
</div>

<?php if ($mensaje): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $mensaje ?>
    </div>
<?php endif; ?>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Vehículo</th>
                <th>Kilometraje</th>
                <th>Descripción</th>
                <th>Costo</th>
                <th>Próximo (km)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($mantenimientos as $m): ?>
            <tr>
                <td><?= formatDate($m['fecha']) ?></td>
                <td style="font-weight:bold;"><?= htmlspecialchars($m['dominio']) ?></td>
                <td><?= number_format($m['kilometraje'], 0, '', '.') ?> km</td>
                <td><?= htmlspecialchars($m['descripcion']) ?></td>
                <td style="font-weight:bold;"><?= formatMoney($m['costo_total']) ?></td>
                <td style="color: var(--accent); font-weight:bold;"><?= $m['proximo_service_km'] ? number_format($m['proximo_service_km'], 0, '', '.') . ' km' : '-' ?></td>
                <td>
                    <button onclick='editManto(<?= json_encode($m) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Alta/Edición -->
<div id="modal-manto" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="manto-title">Registrar Mantenimiento</h3>
            <span class="close-modal" onclick="closeModal('modal-manto')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="m-action" value="nuevo">
                <input type="hidden" name="id" id="m-id">
                
                <div class="form-group">
                    <label>Vehículo</label>
                    <select name="vehiculo_id" id="m-vehiculo" class="input-field" required>
                        <?php foreach($lista_vehiculos as $veh): ?>
                            <option value="<?= $veh['id'] ?>"><?= htmlspecialchars($veh['dominio'] . " - " . $veh['marca']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group"><label>Fecha</label><input type="date" name="fecha" id="m-fecha" class="input-field" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Kilometraje Actual</label><input type="number" name="kilometraje" id="m-km" class="input-field" required></div>
                    <div class="form-group"><label>Costo Total ($)</label><input type="number" step="0.01" name="costo_total" id="m-costo" class="input-field"></div>
                    <div class="form-group"><label>Próximo Service (km)</label><input type="number" name="proximo_service_km" id="m-proximo" class="input-field"></div>
                </div>

                <div class="form-group">
                    <label>Descripción del Trabajo</label>
                    <textarea name="descripcion" id="m-desc" class="input-field" rows="3" required placeholder="Ej: Cambio de aceite y filtros, revisión de frenos..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-manto')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevoManto() {
    document.getElementById('manto-title').innerText = "Nuevo Registro de Mantenimiento";
    document.getElementById('m-action').value = "nuevo";
    document.querySelector('#modal-manto form').reset();
    openModal('modal-manto');
}
function editManto(data) {
    document.getElementById('manto-title').innerText = "Editar Registro";
    document.getElementById('m-action').value = "editar";
    document.getElementById('m-id').value = data.id;
    document.getElementById('m-vehiculo').value = data.vehiculo_id;
    document.getElementById('m-fecha').value = data.fecha;
    document.getElementById('m-km').value = data.kilometraje;
    document.getElementById('m-costo').value = data.costo_total;
    document.getElementById('m-proximo').value = data.proximo_service_km;
    document.getElementById('m-desc').value = data.descripcion;
    openModal('modal-manto');
}
</script>