<?php
/**
 * Modulo de Gestion de Clientes - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Un mismo registro puede ser: Cliente, Comisionista, Pagador.
 * Spec: base.md seccion 3.
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function clienteOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND transportista_id = ?");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

function validarTipoCliente(array $flags): bool {
    return (bool)($flags['es_comercial'] || $flags['es_comisionista'] || $flags['es_pagador']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    $razon_social = trim($_POST['razon_social'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $flags = [
        'es_comercial'    => isset($_POST['es_comercial'])    ? 1 : 0,
        'es_comisionista' => isset($_POST['es_comisionista']) ? 1 : 0,
        'es_pagador'      => isset($_POST['es_pagador'])      ? 1 : 0,
    ];

    if ($_POST['action'] === 'nuevo') {
        if ($razon_social === '' || $cuit === '') {
            $error = "Razon Social y CUIT/DNI son obligatorios.";
        } elseif (!validarTipoCliente($flags)) {
            $error = "Debe seleccionar al menos un tipo.";
        } else {
            try {
                $sql = "INSERT INTO clientes (transportista_id, razon_social, cuit, direccion, telefono, es_comercial, es_comisionista, es_pagador, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$active_company_id, $razon_social, $cuit, $direccion, $telefono, $flags['es_comercial'], $flags['es_comisionista'], $flags['es_pagador'], $currentUserId]);
                $mensaje = "Cliente registrado exitosamente.";
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
        if ($id <= 0 || !clienteOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el cliente no existe o pertenece a otro tenant.";
        } elseif ($razon_social === '' || $cuit === '') {
            $error = "Razon Social y CUIT/DNI son obligatorios.";
        } elseif (!validarTipoCliente($flags)) {
            $error = "Debe seleccionar al menos un tipo.";
        } else {
            try {
                $sql = "UPDATE clientes SET razon_social=?, cuit=?, direccion=?, telefono=?, es_comercial=?, es_comisionista=?, es_pagador=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$razon_social, $cuit, $direccion, $telefono, $flags['es_comercial'], $flags['es_comisionista'], $flags['es_pagador'], $id]);
                $mensaje = "Cliente actualizado correctamente.";
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
        if ($id <= 0 || !clienteOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el cliente no existe o pertenece a otro tenant.";
        } else {
            try {
                $pdo->prepare("UPDATE clientes SET activo = 0 WHERE id = ?")->execute([$id]);
                $mensaje = "Cliente eliminado (borrado logico).";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
}

$filtro_tipo = $_GET['tipo'] ?? 'todos';
$where_tipo = "";
$params = [$active_company_id];
switch ($filtro_tipo) {
    case 'comercial':    $where_tipo = "AND es_comercial = 1";    break;
    case 'comisionista': $where_tipo = "AND es_comisionista = 1"; break;
    case 'pagador':      $where_tipo = "AND es_pagador = 1";      break;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE transportista_id = ? AND activo = 1 $where_tipo ORDER BY razon_social ASC");
$stmt->execute($params);
$clientes = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Gestion de Clientes</h1>
        <p>Administra los clientes, comisionistas y pagadores de flete de la empresa activa.</p>
    </div>
    <button onclick="prepararNuevoCliente()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Cliente
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

<div class="card" style="margin-bottom: 20px; padding: 12px 16px;">
    <form method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <input type="hidden" name="route" value="clientes">
        <label style="font-weight: bold; opacity: 0.8;">Filtrar por tipo:</label>
        <select name="tipo" class="input-field" style="max-width: 220px;" onchange="this.form.submit()">
            <option value="todos"        <?= $filtro_tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="comercial"    <?= $filtro_tipo === 'comercial' ? 'selected' : '' ?>>Solo Clientes</option>
            <option value="comisionista" <?= $filtro_tipo === 'comisionista' ? 'selected' : '' ?>>Solo Comisionistas</option>
            <option value="pagador"      <?= $filtro_tipo === 'pagador' ? 'selected' : '' ?>>Solo Pagadores</option>
        </select>
        <span style="opacity: 0.6; font-size: 0.9rem;"><?= count($clientes) ?> resultado(s)</span>
    </form>
</div>

<div class="card">
    <?php if (empty($clientes)): ?>
        <p style="text-align:center; padding: 40px; opacity:0.5;">No hay clientes para mostrar.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Razon Social</th>
                    <th>CUIT / DNI</th>
                    <th>Telefono</th>
                    <th>Direccion</th>
                    <th>Tipo</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($clientes as $c): ?>
                <tr>
                    <td style="font-weight:bold;"><?= htmlspecialchars($c['razon_social']) ?></td>
                    <td><?= htmlspecialchars($c['cuit']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['direccion'] ?? '-') ?></td>
                    <td>
                        <?php if ($c['es_comercial']):    ?><span class="badge" style="background:#3498db; color:#fff;">Cliente</span> <?php endif; ?>
                        <?php if ($c['es_comisionista']): ?><span class="badge" style="background:#9b59b6; color:#fff;">Comisionista</span> <?php endif; ?>
                        <?php if ($c['es_pagador']):      ?><span class="badge" style="background:#16a085; color:#fff;">Pagador</span> <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <button onclick='editCliente(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmarBorrarCliente(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['razon_social'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:8px;">
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

<div id="modal-cliente" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="cliente-modal-title">Registrar Cliente</h3>
            <span class="close-modal" onclick="closeModal('modal-cliente')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="cliente-action" value="nuevo">
                <input type="hidden" name="id" id="cliente-id">
                <div class="form-group">
                    <label>Razon Social / Nombre *</label>
                    <input type="text" name="razon_social" id="cliente-razon" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>CUIT / DNI (sin guiones) *</label>
                    <input type="text" name="cuit" id="cliente-cuit" class="input-field" maxlength="11" pattern="[0-9]{8,11}" required>
                </div>
                <div class="form-group">
                    <label>Direccion</label>
                    <input type="text" name="direccion" id="cliente-direccion" class="input-field">
                </div>
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="text" name="telefono" id="cliente-telefono" class="input-field">
                </div>
                <div class="form-group">
                    <label>Tipo (al menos uno) *</label>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_comercial" id="cliente-es-comercial" value="1">
                            <span class="badge" style="background:#3498db; color:#fff;">Cliente</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_comisionista" id="cliente-es-comisionista" value="1">
                            <span class="badge" style="background:#9b59b6; color:#fff;">Comisionista</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="es_pagador" id="cliente-es-pagador" value="1">
                            <span class="badge" style="background:#16a085; color:#fff;">Pagador</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-cliente')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<form id="form-borrar-cliente" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-cliente-id">
</form>

<script>
function prepararNuevoCliente() {
    document.getElementById('cliente-modal-title').innerText = "Registrar Nuevo Cliente";
    document.getElementById('cliente-action').value = "nuevo";
    document.getElementById('cliente-id').value = "";
    document.querySelector('#modal-cliente form').reset();
    document.getElementById('cliente-es-comercial').checked = false;
    document.getElementById('cliente-es-comisionista').checked = false;
    document.getElementById('cliente-es-pagador').checked = false;
    openModal('modal-cliente');
}

function editCliente(data) {
    document.getElementById('cliente-modal-title').innerText = "Editar Cliente: " + data.razon_social;
    document.getElementById('cliente-action').value = "editar";
    document.getElementById('cliente-id').value = data.id;
    document.getElementById('cliente-razon').value = data.razon_social;
    document.getElementById('cliente-cuit').value = data.cuit;
    document.getElementById('cliente-direccion').value = data.direccion || '';
    document.getElementById('cliente-telefono').value = data.telefono || '';
    document.getElementById('cliente-es-comercial').checked    = (data.es_comercial == 1);
    document.getElementById('cliente-es-comisionista').checked = (data.es_comisionista == 1);
    document.getElementById('cliente-es-pagador').checked      = (data.es_pagador == 1);
    openModal('modal-cliente');
}

function confirmarBorrarCliente(id, nombre) {
    appConfirm("Seguro que deseas eliminar el cliente \"" + nombre + "\"? (borrado logico)", function() {
        document.getElementById('borrar-cliente-id').value = id;
        document.getElementById('form-borrar-cliente').submit();
    }, "Eliminar Cliente");
}
</script>
