<?php
/**
 * Módulo de Gestión de Empresas (Transportistas) - Trans Cargo Hub
 */

$mensaje = "";
$error = "";

// --- PROCESAR ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $razon_social = trim($_POST['razon_social']);
    $cuit = trim($_POST['cuit']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);

    if ($_POST['action'] === 'nuevo') {
        try {
            $sql = "INSERT INTO transportistas (razon_social, cuit, direccion, telefono, email) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$razon_social, $cuit, $direccion, $telefono, $email]);
            $mensaje = "Empresa registrada exitosamente.";
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? "Error: El CUIT ya existe." : "Error: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = $_POST['id'];
        try {
            $sql = "UPDATE transportistas SET razon_social=?, cuit=?, direccion=?, telefono=?, email=? WHERE id=?";
            $pdo->prepare($sql)->execute([$razon_social, $cuit, $direccion, $telefono, $email, $id]);
            $mensaje = "Empresa actualizada correctamente.";
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// --- OBTENER DATOS ---
$stmt = $pdo->query("SELECT * FROM transportistas ORDER BY razon_social ASC");
$empresas = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Gestión de Empresas</h1>
        <p>Administra los transportistas dueños de las flotas.</p>
    </div>
    <button onclick="prepararNuevaEmpresa()" class="btn-primary">
        <i class="fas fa-plus"></i> Nueva Empresa
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
                <th>Razón Social</th>
                <th>CUIT</th>
                <th>Dirección</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($empresas as $emp): ?>
            <tr>
                <td style="font-weight:bold;"><?= htmlspecialchars($emp['razon_social']) ?></td>
                <td><?= $emp['cuit'] ?></td>
                <td><?= htmlspecialchars($emp['direccion']) ?></td>
                <td><?= htmlspecialchars($emp['telefono']) ?></td>
                <td><?= htmlspecialchars($emp['email']) ?></td>
                <td>
                    <button onclick='editEmpresa(<?= json_encode($emp) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Alta/Edición -->
<div id="modal-empresa" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="modal-title">Registrar Empresa</h3>
            <span class="close-modal" onclick="closeModal('modal-empresa')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="emp-action" value="nuevo">
                <input type="hidden" name="id" id="emp-id">
                
                <div class="form-group">
                    <label>Razón Social</label>
                    <input type="text" name="razon_social" id="emp-razon" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>CUIT (11 dígitos)</label>
                    <input type="text" name="cuit" id="emp-cuit" class="input-field" maxlength="11" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" id="emp-direccion" class="input-field">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="emp-telefono" class="input-field">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="emp-email" class="input-field">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-empresa')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Empresa</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevaEmpresa() {
    document.getElementById('modal-title').innerText = "Registrar Nueva Empresa";
    document.getElementById('emp-action').value = "nuevo";
    document.getElementById('emp-id').value = "";
    document.querySelector('#modal-empresa form').reset();
    openModal('modal-empresa');
}

function editEmpresa(data) {
    document.getElementById('modal-title').innerText = "Editar Empresa: " + data.razon_social;
    document.getElementById('emp-action').value = "editar";
    document.getElementById('emp-id').value = data.id;
    document.getElementById('emp-razon').value = data.razon_social;
    document.getElementById('emp-cuit').value = data.cuit;
    document.getElementById('emp-direccion').value = data.direccion;
    document.getElementById('emp-telefono').value = data.telefono;
    document.getElementById('emp-email').value = data.email;
    openModal('modal-empresa');
}
</script>