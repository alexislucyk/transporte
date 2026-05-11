<?php
/**
 * Módulo de Gestión de Choferes - Trans Cargo Hub
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

// --- LÓGICA DE LA CUENTA CORRIENTE ---
if ($action === 'ctacte' && isset($params[0])) {
    include_once 'modules/choferes_ctacte.php';
    return; // Detiene la ejecución para mostrar solo la Cta Cte
}

// --- PROCESAR ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cuil = trim($_POST['cuil']);
    $licencia = trim($_POST['licencia_nro']);
    $vencimiento = !empty($_POST['vencimiento_licencia']) ? $_POST['vencimiento_licencia'] : null;
    $ganancia = !empty($_POST['porcentaje_ganancia']) ? $_POST['porcentaje_ganancia'] : 0;
    $telefono = trim($_POST['telefono']);

    if (strlen($cuil) !== 11 || !is_numeric($cuil)) {
        $error = "El CUIL debe ser de 11 dígitos numéricos sin guiones.";
    } else {
        try {
            $sql = "INSERT INTO choferes (nombre, apellido, cuil, licencia_nro, vencimiento_licencia, porcentaje_ganancia, telefono, transportista_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $apellido, $cuil, $licencia, $vencimiento, $ganancia, $telefono, $active_company_id]);
            $mensaje = "Chofer registrado exitosamente.";
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? "Error: Ya existe un chofer con ese CUIL." : "Error: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $cuil = trim($_POST['cuil']);
    $licencia = trim($_POST['licencia_nro']);
    $vencimiento = !empty($_POST['vencimiento_licencia']) ? $_POST['vencimiento_licencia'] : null;
    $ganancia = !empty($_POST['porcentaje_ganancia']) ? $_POST['porcentaje_ganancia'] : 0;
    $telefono = trim($_POST['telefono']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    try {
        $sql = "UPDATE choferes SET nombre=?, apellido=?, cuil=?, licencia_nro=?, vencimiento_licencia=?, porcentaje_ganancia=?, telefono=?, activo=? WHERE id=?";
        $pdo->prepare($sql)->execute([$nombre, $apellido, $cuil, $licencia, $vencimiento, $ganancia, $telefono, $activo, $id]);
        $mensaje = "Chofer actualizado correctamente.";
    } catch (PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// 2. Obtener listado de choferes
$stmt = $pdo->prepare("SELECT * FROM choferes WHERE transportista_id = ? ORDER BY apellido ASC");
$sql = "SELECT c.*, 
        (
            COALESCE((SELECT SUM(monto) FROM chofer_pagos WHERE chofer_id = c.id AND tipo = 'liquidacion'), 0) 
            - 
            COALESCE((SELECT SUM(monto) FROM chofer_pagos WHERE chofer_id = c.id AND tipo != 'liquidacion'), 0) 
            + 
            COALESCE((SELECT SUM(vg.monto) FROM viajes_gastos vg JOIN viajes v ON vg.viaje_id = v.id WHERE v.chofer_id = c.id AND vg.pagado_por = 'adelanto'), 0)
            -
            COALESCE((SELECT SUM(va.monto) FROM viajes_adelantos va JOIN viajes v ON va.viaje_id = v.id WHERE v.chofer_id = c.id), 0)
        ) as saldo
        FROM choferes c 
        WHERE c.transportista_id = ? 
        ORDER BY c.apellido ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$active_company_id]);
$choferes = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Gestión de Choferes</h1>
        <p>Administración de conductores y legajos.</p>
    </div>
    <button onclick="openModal('modal-nuevo-chofer')" class="btn-primary">
        <i class="fas fa-user-plus"></i> Nuevo Chofer
    </button>
</div>

<style>
    .btn-primary { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .data-table th, .data-table td { text-align: left; padding: 12px; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .data-table th { background: rgba(0,0,0,0.02); color: var(--text); font-weight: bold; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; }
    .badge-success { background: #2ecc71; color: white; }
    .input-field { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: 1px solid #ddd; background: var(--card); color: var(--text); }
    .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
</style>

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

<!-- Modal de Alta de Chofer -->
<div id="modal-nuevo-chofer" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0">Registrar Nuevo Chofer</h3>
            <span class="close-modal" onclick="closeModal('modal-nuevo-chofer')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="form-action" value="nuevo">
                <input type="hidden" name="id" id="chofer-id">
                <div class="grid-form">
                    <div>
                        <label>Nombre</label>
                        <input type="text" name="nombre" id="form-nombre" class="input-field" required>
                    </div>
                    <div>
                        <label>Apellido</label>
                        <input type="text" name="apellido" id="form-apellido" class="input-field" required>
                    </div>
                    <div>
                        <label>CUIL (11 dígitos)</label>
                        <input type="text" name="cuil" id="form-cuil" class="input-field" maxlength="11" required>
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="text" name="telefono" id="form-telefono" class="input-field">
                    </div>
                    <div>
                        <label>Nro. Licencia LINTI</label>
                        <input type="text" name="licencia_nro" id="form-licencia" class="input-field">
                    </div>
                    <div>
                        <label>Vencimiento Licencia</label>
                        <input type="date" name="vencimiento_licencia" id="form-vencimiento" class="input-field">
                    </div>
                    <div>
                        <label>% Ganancia por Viaje</label>
                        <input type="number" step="0.01" name="porcentaje_ganancia" id="form-ganancia" class="input-field" value="0.00">
                    </div>
                    <div id="status-container" style="display:none">
                        <label><input type="checkbox" name="activo" id="form-activo" checked> Chofer Activo</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-nuevo-chofer')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Chofer</button>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre y Apellido</th>
                <th>CUIL</th>
                <th>Teléfono</th>
                <th>Venc. Licencia</th>
                <th>% Ganancia</th>
                <th style="text-align:right">Saldo Actual</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($choferes as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['apellido'] . ", " . $c['nombre']) ?></td>
                <td><?= $c['cuil'] ?></td>
                <td><?= htmlspecialchars($c['telefono']) ?></td>
                <td><?= $c['vencimiento_licencia'] ? date('d/m/Y', strtotime($c['vencimiento_licencia'])) : '-' ?></td>
                <td><?= number_format($c['porcentaje_ganancia'], 2) ?>%</td>
                <td style="text-align:right; font-weight:bold; color: <?= $c['saldo'] >= 0 ? '#2ecc71' : '#e74c3c' ?>">
                    <?= formatMoney($c['saldo']) ?>
                </td>
                <td><span class="badge <?= $c['activo'] ? 'badge-success' : '' ?>"><?= $c['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                <td>
                    <button onclick='editChofer(<?= json_encode($c) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                    <a href="choferes/ctacte/<?= $c['id'] ?>" title="Cuenta Corriente" style="color:var(--primary); margin-left:10px;"><i class="fas fa-file-invoice-dollar"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editChofer(data) {
    document.getElementById('form-action').value = 'editar';
    document.getElementById('chofer-id').value = data.id;
    document.getElementById('form-nombre').value = data.nombre;
    document.getElementById('form-apellido').value = data.apellido;
    document.getElementById('form-cuil').value = data.cuil;
    document.getElementById('form-telefono').value = data.telefono;
    document.getElementById('form-licencia').value = data.licencia_nro;
    document.getElementById('form-vencimiento').value = data.vencimiento_licencia;
    document.getElementById('form-ganancia').value = data.porcentaje_ganancia;
    document.getElementById('form-activo').checked = data.activo == 1;
    document.getElementById('status-container').style.display = 'block';
    openModal('modal-nuevo-chofer');
}
</script>