<?php
/**
 * Módulo de Gestión de Clientes - Trans Cargo Hub
 */
$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $razon = trim($_POST['razon_social']);
    $cuit = trim($_POST['cuit']);
    $dir = trim($_POST['direccion']);

    if ($_POST['action'] === 'nuevo') {
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (transportista_id, razon_social, cuit, direccion) VALUES (?, ?, ?, ?)");
            $stmt->execute([$active_company_id, $razon, $cuit, $dir]);
            $mensaje = "Cliente registrado con éxito.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
    if ($_POST['action'] === 'editar') {
        try {
            $stmt = $pdo->prepare("UPDATE clientes SET razon_social=?, cuit=?, direccion=? WHERE id=? AND transportista_id=?");
            $stmt->execute([$razon, $cuit, $dir, $_POST['id'], $active_company_id]);
            $mensaje = "Cliente actualizado.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
}

$clientes = $pdo->prepare("SELECT * FROM clientes WHERE transportista_id = ? ORDER BY razon_social ASC");
$clientes->execute([$active_company_id]);
$lista = $clientes->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1>Cartera de Clientes</h1>
        <p>Dadores de carga asociados a la empresa actual.</p>
    </div>
    <button onclick="prepararNuevoCliente()" class="btn-primary"><i class="fas fa-plus"></i> Nuevo Cliente</button>
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
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lista as $c): ?>
            <tr>
                <td style="font-weight:bold"><?= htmlspecialchars($c['razon_social']) ?></td>
                <td><?= $c['cuit'] ?></td>
                <td><?= htmlspecialchars($c['direccion']) ?></td>
                <td>
                    <button onclick='editCliente(<?= json_encode($c) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="modal-cliente" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modal-title">Registrar Cliente</h3>
            <span class="close-modal" onclick="closeModal('modal-cliente')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="c-action" value="nuevo">
                <input type="hidden" name="id" id="c-id">
                <div class="form-group">
                    <label>Razón Social</label>
                    <input type="text" name="razon_social" id="c-razon" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>CUIT</label>
                    <input type="text" name="cuit" id="c-cuit" class="input-field" maxlength="11" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" id="c-dir" class="input-field">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararNuevoCliente() {
    document.getElementById('modal-title').innerText = "Nuevo Cliente";
    document.getElementById('c-action').value = "nuevo";
    document.querySelector('#modal-cliente form').reset();
    openModal('modal-cliente');
}
function editCliente(data) {
    document.getElementById('modal-title').innerText = "Editar Cliente";
    document.getElementById('c-action').value = "editar";
    document.getElementById('c-id').value = data.id;
    document.getElementById('c-razon').value = data.razon_social;
    document.getElementById('c-cuit').value = data.cuit;
    document.getElementById('c-dir').value = data.direccion;
    openModal('modal-cliente');
}
</script>