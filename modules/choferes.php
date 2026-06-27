<?php
/**
 * Modulo de Gestion de Choferes - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Spec: base.md seccion 3.
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function choferOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM choferes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $cuil = trim($_POST['cuil'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $porcentaje_ganancia = (float)($_POST['porcentaje_ganancia'] ?? 0);
    $licencia_nro = trim($_POST['licencia_nro'] ?? '');
    $vencimiento_licencia = $_POST['vencimiento_licencia'] ?? null;

    if ($_POST['action'] === 'nuevo') {
        if ($nombre === '' || $apellido === '') {
            $error = "Nombre y Apellido son obligatorios.";
        } else {
            try {
                $sql = "INSERT INTO choferes (transportista_id, nombre, apellido, cuil, telefono, porcentaje_ganancia, licencia_nro, vencimiento_licencia, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$active_company_id, $nombre, $apellido, $cuil ?: null, $telefono ?: null, $porcentaje_ganancia, $licencia_nro ?: null, $vencimiento_licencia ?: null, $currentUserId]);
                $mensaje = "Chofer registrado exitosamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: ya existe un chofer con ese CUIL en esta empresa.";
                } else {
                    $error = "Error al registrar: " . $e->getMessage();
                }
            }
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !choferOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el chofer no existe o pertenece a otro tenant.";
        } elseif ($nombre === '' || $apellido === '') {
            $error = "Nombre y Apellido son obligatorios.";
        } else {
            try {
                $sql = "UPDATE choferes SET nombre=?, apellido=?, cuil=?, telefono=?, porcentaje_ganancia=?, licencia_nro=?, vencimiento_licencia=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellido, $cuil ?: null, $telefono ?: null, $porcentaje_ganancia, $licencia_nro ?: null, $vencimiento_licencia ?: null, $id]);
                $mensaje = "Chofer actualizado correctamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: ya existe otro chofer con ese CUIL en esta empresa.";
                } else {
                    $error = "Error al actualizar: " . $e->getMessage();
                }
            }
        }
    }

    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !choferOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado: el chofer no existe o pertenece a otro tenant.";
        } else {
            try {
                $pdo->prepare("UPDATE choferes SET activo = 0 WHERE id = ?")->execute([$id]);
                $mensaje = "Chofer eliminado (borrado logico).";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido, nombre ASC");
$stmt->execute([$active_company_id]);
$choferes = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Gestion de Choferes</h1>
        <p>Administra los choferes asignados a la empresa activa.</p>
    </div>
    <button onclick="prepararNuevoChofer()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Chofer
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
    <?php if (empty($choferes)): ?>
        <p style="text-align:center; padding: 40px; opacity:0.5;">No hay choferes registrados.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Apellido, Nombre</th>
                    <th>CUIL</th>
                    <th>Telefono</th>
                    <th>% Ganancia</th>
                    <th>Licencia / Venc.</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($choferes as $c):
                    $lic_venc = $c['vencimiento_licencia'];
                    $lic_class = '';
                    if ($lic_venc) {
                        $dias = (strtotime($lic_venc) - time()) / 86400;
                        if ($dias < 0) $lic_class = 'color:#e74c3c; font-weight:bold;';
                        elseif ($dias <= 30) $lic_class = 'color:#f39c12; font-weight:bold;';
                    }
                ?>
                <tr>
                    <td style="font-weight:bold;"><?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['cuil'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                    <td><?= $c['porcentaje_ganancia'] ? $c['porcentaje_ganancia'] . '%' : '-' ?></td>
                    <td style="<?= $lic_class ?>">
                        <?= htmlspecialchars($c['licencia_nro'] ?? '-') ?>
                        <?php if ($lic_venc): ?>
                            <br><small>Vence: <?= formatDate($lic_venc) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <a href="choferes_ctacte?chofer_id=<?= (int)$c['id'] ?>" title="Cuenta Corriente" style="background:none; border:none; color:var(--accent); cursor:pointer; margin-right:8px;">
                            <i class="fas fa-dollar-sign"></i>
                        </a>
                        <button onclick='editChofer(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmarBorrarChofer(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:8px;">
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

<div id="modal-chofer" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="chofer-modal-title">Registrar Chofer</h3>
            <span class="close-modal" onclick="closeModal('modal-chofer')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="chofer-action" value="nuevo">
                <input type="hidden" name="id" id="chofer-id">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="chofer-nombre" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>Apellido *</label>
                    <input type="text" name="apellido" id="chofer-apellido" class="input-field" required>
                </div>
                <div class="form-group">
                    <label>CUIL (sin guiones)</label>
                    <input type="text" name="cuil" id="chofer-cuil" class="input-field" maxlength="11" pattern="[0-9]{11}">
                </div>
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="text" name="telefono" id="chofer-telefono" class="input-field">
                </div>
                <div class="form-group">
                    <label>% Ganancia Flete</label>
                    <input type="number" name="porcentaje_ganancia" id="chofer-porcentaje" class="input-field" min="0" max="100" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Licencia Nro</label>
                    <input type="text" name="licencia_nro" id="chofer-licencia" class="input-field" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Vencimiento Licencia</label>
                    <input type="date" name="vencimiento_licencia" id="chofer-venc-lic" class="input-field">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-chofer')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Chofer</button>
            </div>
        </form>
    </div>
</div>

<form id="form-borrar-chofer" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-chofer-id">
</form>

<script>
function prepararNuevoChofer() {
    document.getElementById('chofer-modal-title').innerText = "Registrar Nuevo Chofer";
    document.getElementById('chofer-action').value = "nuevo";
    document.getElementById('chofer-id').value = "";
    document.querySelector('#modal-chofer form').reset();
    openModal('modal-chofer');
}

function editChofer(data) {
    document.getElementById('chofer-modal-title').innerText = "Editar Chofer: " + data.apellido + ", " + data.nombre;
    document.getElementById('chofer-action').value = "editar";
    document.getElementById('chofer-id').value = data.id;
    document.getElementById('chofer-nombre').value = data.nombre;
    document.getElementById('chofer-apellido').value = data.apellido;
    document.getElementById('chofer-cuil').value = data.cuil || '';
    document.getElementById('chofer-telefono').value = data.telefono || '';
    document.getElementById('chofer-porcentaje').value = data.porcentaje_ganancia || 0;
    document.getElementById('chofer-licencia').value = data.licencia_nro || '';
    document.getElementById('chofer-venc-lic').value = data.vencimiento_licencia || '';
    openModal('modal-chofer');
}

function confirmarBorrarChofer(id, nombre) {
    appConfirm("Seguro que deseas eliminar al chofer \"" + nombre + "\"? (borrado logico)", function() {
        document.getElementById('borrar-chofer-id').value = id;
        document.getElementById('form-borrar-chofer').submit();
    }, "Eliminar Chofer");
}
</script>