<?php
/**
 * Modulo de Gestion de Comisionistas - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Un comisionista es un cliente con es_comisionista = 1.
 * Spec: base.md seccion 3.
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function comisionistaOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND transportista_id = ? AND es_comisionista = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    $razon_social = trim($_POST['razon_social'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $es_comercial    = isset($_POST['es_comercial'])    ? 1 : 0;
    $es_comisionista = isset($_POST['es_comisionista']) ? 1 : 0;
    $es_pagador      = isset($_POST['es_pagador'])      ? 1 : 0;

    if ($_POST['action'] === 'nuevo') {
        if ($razon_social === '' || $cuit === '') {
            $error = "Razon Social y CUIT/DNI son obligatorios.";
        } else {
            try {
                $sql = "INSERT INTO clientes (transportista_id, razon_social, cuit, direccion, telefono, es_comercial, es_comisionista, es_pagador, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$active_company_id, $razon_social, $cuit, $direccion, $telefono, $es_comercial, $es_comisionista, $es_pagador, $currentUserId]);
                $mensaje = "Comisionista registrado exitosamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: ya existe un cliente con ese CUIT en esta empresa.";
                } else {
                    $error = "Error al registrar: " . $e->getMessage();
                }
            }
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !comisionistaOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el comisionista no existe o pertenece a otro tenant.";
        } elseif ($razon_social === '' || $cuit === '') {
            $error = "Razon Social y CUIT/DNI son obligatorios.";
        } else {
            try {
                $sql = "UPDATE clientes SET razon_social=?, cuit=?, direccion=?, telefono=?, es_comercial=?, es_comisionista=?, es_pagador=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$razon_social, $cuit, $direccion, $telefono, $es_comercial, $es_comisionista, $es_pagador, $id]);
                $mensaje = "Comisionista actualizado correctamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: ya existe otro cliente con ese CUIT en esta empresa.";
                } else {
                    $error = "Error al actualizar: " . $e->getMessage();
                }
            }
        }
    }

    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !comisionistaOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el comisionista no existe o pertenece a otro tenant.";
        } else {
            try {
                $pdo->prepare("UPDATE clientes SET activo = 0 WHERE id = ?")->execute([$id]);
                $mensaje = "Comisionista eliminado (borrado logico).";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE transportista_id = ? AND activo = 1 AND es_comisionista = 1 ORDER BY razon_social ASC");
$stmt->execute([$active_company_id]);
$comisionistas = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Gestion de Comisionistas</h1>
        <p>Administra los comisionistas de la empresa activa.</p>
    </div>
    <button onclick="prepararNuevoComisionista()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Comisionista
    </button>
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

<div class="card">
    <?php if (empty($comisionistas)): ?>
        <p style="text-align:center; padding: 40px; opacity:0.5;">No hay comisionistas para mostrar.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Razon Social</th>
                    <th>CUIT / DNI</th>
                    <th>Telefono</th>
                    <th>Direccion</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($comisionistas as $c): ?>
                <tr>
                    <td style="font-weight:bold;"><?= htmlspecialchars($c['razon_social']) ?></td>
                    <td><?= htmlspecialchars($c['cuit']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['direccion'] ?? '-') ?></td>
                    <td style="text-align:center">
                        <a href="comisionistas_ctacte?cliente_id=<?= (int)$c['id'] ?>" title="Cuenta Corriente" style="background:none; border:none; color:var(--accent); cursor:pointer; margin-right:8px;">
                            <i class="fas fa-dollar-sign"></i>
                        </a>
                        <button onclick='editComisionista(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmarBorrarComisionista(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['razon_social'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:8px;">
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

<div id="modal-comisionista" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="comisionista-modal-title">Registrar Comisionista</h3>
            <span class="close-modal" onclick="closeModal('modal-comisionista')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="comisionista-action" value="nuevo">
                <input type="hidden" name="id" id="comisionista-id">
                <div class="form-group">
                    <label>Razon Social / Nombre *</label>
                    <input type="text" name="razon_social" id="comisionista-razon" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>CUIT / DNI (sin guiones) *</label>
                    <input type="text" name="cuit" id="comisionista-cuit" class="input-field" maxlength="11" pattern="[0-9]{8,11}" required>
                </div>
                <div class="form-group">
                    <label>Direccion</label>
                    <input type="text" name="direccion" id="comisionista-direccion" class="input-field">
                </div>
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="text" name="telefono" id="comisionista-telefono" class="input-field">
                </div>
                <div class="form-group">
                    <label>Tipo</label>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_comercial" id="comisionista-es-comercial" value="1">
                            <span class="badge" style="background:#3498db; color:#fff;">Cliente</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_comisionista" id="comisionista-es-comisionista" value="1" checked>
                            <span class="badge" style="background:#9b59b6; color:#fff;">Comisionista</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_pagador" id="comisionista-es-pagador" value="1">
                            <span class="badge" style="background:#16a085; color:#fff;">Pagador</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-comisionista')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Comisionista</button>
            </div>
        </form>
    </div>
</div>

<form id="form-borrar-comisionista" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-comisionista-id">
</form>

<script>
function prepararNuevoComisionista() {
    document.getElementById('comisionista-modal-title').innerText = "Registrar Nuevo Comisionista";
    document.getElementById('comisionista-action').value = "nuevo";
    document.getElementById('comisionista-id').value = "";
    document.querySelector('#modal-comisionista form').reset();
    document.getElementById('comisionista-es-comercial').checked = false;
    document.getElementById('comisionista-es-comisionista').checked = true;
    document.getElementById('comisionista-es-pagador').checked = false;
    openModal('modal-comisionista');
}

function editComisionista(data) {
    document.getElementById('comisionista-modal-title').innerText = "Editar Comisionista: " + data.razon_social;
    document.getElementById('comisionista-action').value = "editar";
    document.getElementById('comisionista-id').value = data.id;
    document.getElementById('comisionista-razon').value = data.razon_social;
    document.getElementById('comisionista-cuit').value = data.cuit;
    document.getElementById('comisionista-direccion').value = data.direccion || '';
    document.getElementById('comisionista-telefono').value = data.telefono || '';
    document.getElementById('comisionista-es-comercial').checked    = (data.es_comercial == 1);
    document.getElementById('comisionista-es-comisionista').checked = (data.es_comisionista == 1);
    document.getElementById('comisionista-es-pagador').checked      = (data.es_pagador == 1);
    openModal('modal-comisionista');
}

function confirmarBorrarComisionista(id, nombre) {
    appConfirm("Seguro que deseas eliminar el comisionista \"" + nombre + "\"? (borrado logico)", function() {
        document.getElementById('borrar-comisionista-id').value = id;
        document.getElementById('form-borrar-comisionista').submit();
    }, "Eliminar Comisionista");
}
</script>
