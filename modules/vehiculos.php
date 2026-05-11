<?php
/**
 * Módulo de Gestión de Vehículos - Trans Cargo Hub
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- PROCESAR ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $dominio = strtoupper(trim($_POST['dominio']));
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $acoplado = trim($_POST['acoplado']);
    $anio = intval($_POST['anio']);
    $vtv = !empty($_POST['vtv_vencimiento']) ? $_POST['vtv_vencimiento'] : null;
    $transportista_id = $active_company_id;
    $chofer_id = !empty($_POST['chofer_id']) ? $_POST['chofer_id'] : null;

    if ($_POST['action'] === 'nuevo') {
        try {
            $sql = "INSERT INTO vehiculos (transportista_id, chofer_id, acoplado, dominio, marca, modelo, anio, vtv_vencimiento) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$transportista_id, $chofer_id, $acoplado, $dominio, $marca, $modelo, $anio, $vtv]);
            $mensaje = "Vehículo registrado exitosamente.";
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? "Error: El dominio (patente) ya existe." : "Error: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = $_POST['id'];
        try {
            $sql = "UPDATE vehiculos SET transportista_id=?, chofer_id=?, acoplado=?, dominio=?, marca=?, modelo=?, anio=?, vtv_vencimiento=? WHERE id=?";
            $pdo->prepare($sql)->execute([$transportista_id, $chofer_id, $acoplado, $dominio, $marca, $modelo, $anio, $vtv, $id]);
            $mensaje = "Vehículo actualizado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// --- OBTENER DATOS ---
// 1. Listado de vehículos filtrado por empresa activa
$sql_v = "SELECT v.*, t.razon_social as transportista, CONCAT(c.apellido, ', ', c.nombre) as chofer_nombre
          FROM vehiculos v 
          LEFT JOIN transportistas t ON v.transportista_id = t.id 
          LEFT JOIN choferes c ON v.chofer_id = c.id
          WHERE v.transportista_id = ?
          ORDER BY v.dominio ASC";
$stmt_v = $pdo->prepare($sql_v);
$stmt_v->execute([$active_company_id]);
$vehiculos = $stmt_v->fetchAll();

// 2. Listado de choferes para el selector (solo activos de la empresa)
$stmt_c = $pdo->prepare("SELECT id, nombre, apellido FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido ASC");
$stmt_c->execute([$active_company_id]);
$choferes_lista = $stmt_c->fetchAll();

$hoy = date('Y-m-d');
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Flota de Vehículos</h1>
        <p>Control de camiones, acoplados y sus vencimientos técnicos.</p>
    </div>
    <button onclick="prepararNuevoVehiculo()" class="btn-primary">
        <i class="fas fa-plus"></i> Nueva Unidad
    </button>
</div>

<?php if ($mensaje): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?= $mensaje ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Dominio</th>
                <th>Marca / Modelo</th>
                <th>Acoplado Hab.</th>
                <th>Año</th>
                <th>Chofer Habitual</th>
                <!-- Ocultamos transportista ya que está filtrado arriba -->
                <th>Venc. VTV</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($vehiculos as $v): 
                $vencido = ($v['vtv_vencimiento'] && $v['vtv_vencimiento'] < $hoy);
            ?>
            <tr>
                <td style="font-weight:bold; letter-spacing: 1px;"><?= $v['dominio'] ?></td>
                <td><?= htmlspecialchars($v['marca'] . " " . $v['modelo']) ?></td>
                <td><?= htmlspecialchars($v['acoplado'] ?? '-') ?></td>
                <td><?= $v['anio'] ?></td>
                <td><?= htmlspecialchars($v['chofer_nombre'] ?? 'Sin asignar') ?></td>
                <td style="<?= $vencido ? 'color: #e74c3c; font-weight:bold;' : '' ?>">
                    <?= $v['vtv_vencimiento'] ? date('d/m/Y', strtotime($v['vtv_vencimiento'])) : '-' ?>
                    <?php if($vencido): ?> <i class="fas fa-exclamation-circle"></i> <?php endif; ?>
                </td>
                <td>
                    <button onclick='editVehiculo(<?= json_encode($v) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Alta/Edición -->
<div id="modal-vehiculo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="modal-title">Registrar Vehículo</h3>
            <span class="close-modal" onclick="closeModal('modal-vehiculo')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="v-action" value="nuevo">
                <input type="hidden" name="id" id="v-id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Dominio (Patente)</label>
                        <input type="text" name="dominio" id="v-dominio" class="input-field" placeholder="ABC123" required>
                    </div>
                    <div class="form-group">
                        <label>Año</label>
                        <input type="number" name="anio" id="v-anio" class="input-field" value="<?= date('Y') ?>">
                    </div>
                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="marca" id="v-marca" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="modelo" id="v-modelo" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label>Acoplado Habitual (Patente/Tipo)</label>
                        <input type="text" name="acoplado" id="v-acoplado" class="input-field" placeholder="Ej: AA123BB o Batea">
                    </div>
                    <div class="form-group">
                        <label>Chofer Habitual</label>
                        <select name="chofer_id" id="v-chofer" class="input-field">
                            <option value="">-- Sin chofer fijo --</option>
                            <?php foreach($choferes_lista as $ch): ?>
                                <option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['apellido'] . ", " . $ch['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vencimiento VTV</label>
                        <input type="date" name="vtv_vencimiento" id="v-vtv" class="input-field">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-vehiculo')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Unidad</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevoVehiculo() {
    document.getElementById('modal-title').innerText = "Registrar Nueva Unidad";
    document.getElementById('v-action').value = "nuevo";
    document.getElementById('v-id').value = "";
    document.querySelector('#modal-vehiculo form').reset();
    openModal('modal-vehiculo');
}

function editVehiculo(data) {
    document.getElementById('modal-title').innerText = "Editar Unidad: " + data.dominio;
    document.getElementById('v-action').value = "editar";
    document.getElementById('v-id').value = data.id;
    document.getElementById('v-dominio').value = data.dominio;
    document.getElementById('v-marca').value = data.marca;
    document.getElementById('v-modelo').value = data.modelo;
    document.getElementById('v-anio').value = data.anio;
    document.getElementById('v-vtv').value = data.vtv_vencimiento;
    document.getElementById('v-chofer').value = data.chofer_id || "";
    document.getElementById('v-acoplado').value = data.acoplado || "";
    openModal('modal-vehiculo');
}
</script>