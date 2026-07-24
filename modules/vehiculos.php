<?php
/**
 * Modulo de Gestion de Vehiculos - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Spec: base.md seccion 3.
 */

$mensaje = ""; $error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

function vehiculoOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM vehiculos WHERE id = ? AND transportista_id = ?");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];
    $dominio = strtoupper(trim($_POST['dominio'] ?? ''));
    $acoplado = strtoupper(trim($_POST['acoplado'] ?? ''));
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $anio = (int)($_POST['anio'] ?? 0);
    $vtv_vencimiento = $_POST['vtv_vencimiento'] ?? null;
    $chofer_id = (int)($_POST['chofer_id'] ?? 0);

    if ($_POST['action'] === 'nuevo') {
        if ($dominio === '') {
            $error = "La patente del camion es obligatoria.";
        } else {
            // Determinar adminRootId para verificar límites
            $adminRootId = $_SESSION['admin_root_id'] ?? null;
            if (!$adminRootId) {
                if ($currentRole === 'developer' || $currentRole === 'admin') {
                    $adminRootId = $currentUserId;
                } else {
                    $stmtAdmin = $pdo->prepare("SELECT created_by FROM users WHERE id = ? AND role <> 'developer' LIMIT 1");
                    $stmtAdmin->execute([$currentUserId]);
                    $adminRootId = (int)($stmtAdmin->fetchColumn() ?: 0);
                    if (!$adminRootId) {
                        $adminRootId = $currentUserId;
                    }
                }
            }
            
            // Verificar límite de vehículos (solo para admins, no developer)
            if ($currentRole !== 'developer') {
                $check = verificarLimite($pdo, 'vehiculos', $adminRootId, $active_company_id);
                if (!$check['permitido']) {
                    $error = $check['mensaje'];
                }
            }
            if (empty($error)) {
                try {
                    $sql = "INSERT INTO vehiculos (transportista_id, dominio, marca, modelo, acoplado, anio, vtv_vencimiento, chofer_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$active_company_id, $dominio, $marca, $modelo, $acoplado, ($anio > 0 ? $anio : null), ($vtv_vencimiento ?: null), ($chofer_id > 0 ? $chofer_id : null), $currentUserId]);
                    $nuevaId = (int)$pdo->lastInsertId();
                    
                    // Registrar auditoría de creación
                    registrarAuditoria($pdo, $currentUserId, 'crear', 'vehiculos', 
                        "Nuevo vehículo registrado: {$dominio} ({$marca} {$modelo})",
                        null,
                        ['id' => $nuevaId, 'dominio' => $dominio, 'marca' => $marca, 'modelo' => $modelo, 'acoplado' => $acoplado, 'anio' => $anio, 'chofer_id' => $chofer_id]
                    );
                    
                    $mensaje = "Vehiculo registrado exitosamente.";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "Error: ya existe un vehiculo con esa patente en esta empresa.";
                    } else {
                        $error = "Error al registrar: " . $e->getMessage();
                    }
                }
            }
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !vehiculoOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado.";
        } elseif ($dominio === '') {
            $error = "La patente es obligatoria.";
        } else {
            try {
                // Obtener datos anteriores para auditoría
                $stmtDatos = $pdo->prepare("SELECT dominio, marca, modelo, acoplado, anio, chofer_id FROM vehiculos WHERE id = ?");
                $stmtDatos->execute([$id]);
                $datosAnteriores = $stmtDatos->fetch(PDO::FETCH_ASSOC);
                
                $sql = "UPDATE vehiculos SET dominio=?, marca=?, modelo=?, acoplado=?, anio=?, vtv_vencimiento=?, chofer_id=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$dominio, $marca, $modelo, $acoplado, ($anio > 0 ? $anio : null), ($vtv_vencimiento ?: null), ($chofer_id > 0 ? $chofer_id : null), $id]);
                
                // Registrar auditoría de edición
                registrarAuditoria($pdo, $currentUserId, 'editar', 'vehiculos', 
                    "Vehículo actualizado: {$dominio} (ID: {$id})",
                    $datosAnteriores,
                    ['dominio' => $dominio, 'marca' => $marca, 'modelo' => $modelo, 'acoplado' => $acoplado, 'anio' => $anio, 'vtv_vencimiento' => $vtv_vencimiento, 'chofer_id' => $chofer_id]
                );
                
                $mensaje = "Vehiculo actualizado correctamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: patente duplicada en esta empresa.";
                } else {
                    $error = "Error al actualizar: " . $e->getMessage();
                }
            }
        }
    }

    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !vehiculoOwner($pdo, $id, $active_company_id, $currentRole)) {
            $error = "No autorizado.";
        } else {
            try {
                // Obtener datos anteriores para auditoría
                $stmtDatos = $pdo->prepare("SELECT dominio, marca, modelo FROM vehiculos WHERE id = ?");
                $stmtDatos->execute([$id]);
                $datosAnteriores = $stmtDatos->fetch(PDO::FETCH_ASSOC);
                
                $pdo->prepare("UPDATE vehiculos SET activo = 0 WHERE id = ?")->execute([$id]);
                
                // Registrar auditoría de eliminación
                registrarAuditoria($pdo, $currentUserId, 'eliminar', 'vehiculos', 
                    "Vehículo eliminado (borrado lógico): " . ($datosAnteriores['dominio'] ?? 'ID: ' . $id),
                    $datosAnteriores,
                    ['activo' => 0, 'tipo_eliminacion' => 'borrado_logico']
                );
                
                $mensaje = "Vehiculo eliminado (borrado logico).";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }
}

$stmt_ch = $pdo->prepare("SELECT id, nombre, apellido, activo FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido, nombre ASC");
$stmt_ch->execute([$active_company_id]);
$choferes_list = $stmt_ch->fetchAll();

$stmt = $pdo->prepare("SELECT v.*, c.nombre AS chofer_nombre, c.apellido AS chofer_apellido FROM vehiculos v LEFT JOIN choferes c ON c.id = v.chofer_id WHERE v.transportista_id = ? AND v.activo = 1 ORDER BY v.dominio ASC");
$stmt->execute([$active_company_id]);
$vehiculos = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1>Gestion de Vehiculos</h1>
        <p>Administra la flota de camiones y acoplados de la empresa activa.</p>
    </div>
    <button onclick="prepararNuevoVehiculo()" class="btn-primary">
        <i class="fas fa-plus"></i> Nuevo Vehiculo
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

<div class="card">
    <?php if (empty($vehiculos)): ?>
        <p style="text-align:center; padding:40px; opacity:0.5;">No hay vehiculos registrados.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Dominio</th>
                    <th>Marca / Modelo</th>
                    <th>Acoplado</th>
                    <th>Anio</th>
                    <th>Chofer Asignado</th>
                    <th>VTV Vencimiento</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vehiculos as $v):
                    $vtv = $v['vtv_vencimiento'];
                    $vtv_class = '';
                    if ($vtv) {
                        $dias = (strtotime($vtv) - time()) / 86400;
                        if ($dias < 0) $vtv_class = 'color:#e74c3c; font-weight:bold;';
                        elseif ($dias <= 30) $vtv_class = 'color:#f39c12; font-weight:bold;';
                    }
                ?>
                <tr>
                    <td style="font-weight:bold; font-family:monospace;"><?= htmlspecialchars($v['dominio']) ?></td>
                    <td><?= htmlspecialchars(trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')) ?: '-') ?></td>
                    <td style="font-family:monospace;"><?= htmlspecialchars($v['acoplado'] ?? '-') ?></td>
                    <td><?= $v['anio'] ?: '-' ?></td>
                    <td>
                        <?php if ($v['chofer_id']): ?>
                            <i class="fas fa-user"></i> <?= htmlspecialchars(trim($v['chofer_apellido'] . ', ' . $v['chofer_nombre'])) ?>
                        <?php else: ?>
                            <span style="opacity:0.5;">Sin asignar</span>
                        <?php endif; ?>
                    </td>
                    <td style="<?= $vtv_class ?>">
                        <?= $vtv ? formatDate($vtv) : '<span style="opacity:0.5;">-</span>' ?>
                    </td>
                    <td style="text-align:center">
                        <button onclick='editVehiculo(<?= json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar" style="background:none; border:none; color:var(--accent); cursor:pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmarBorrarVehiculo(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($v['dominio'], ENT_QUOTES) ?>')" title="Eliminar" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:8px;">
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

<div id="modal-vehiculo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0" id="vehiculo-modal-title">Registrar Vehiculo</h3>
            <span class="close-modal" onclick="closeModal('modal-vehiculo')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="vehiculo-action" value="nuevo">
                <input type="hidden" name="id" id="vehiculo-id">
                <div class="form-group">
                    <label>Patente del Camion *</label>
                    <input type="text" name="dominio" id="vehiculo-dominio" class="input-field" maxlength="10" style="text-transform:uppercase;" required>
                </div>
                <div class="form-group">
                    <label>Patente del Acoplado</label>
                    <input type="text" name="acoplado" id="vehiculo-acoplado" class="input-field" maxlength="50" style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" id="vehiculo-marca" class="input-field" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="modelo" id="vehiculo-modelo" class="input-field" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Anio</label>
                    <input type="number" name="anio" id="vehiculo-anio" class="input-field" min="1950" max="<?= date('Y') + 1 ?>">
                </div>
                <div class="form-group">
                    <label>Chofer Asignado</label>
                    <select name="chofer_id" id="vehiculo-chofer" class="input-field">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach($choferes_list as $ch): ?>
                            <option value="<?= (int)$ch['id'] ?>"><?= htmlspecialchars($ch['apellido'] . ', ' . $ch['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vencimiento VTV</label>
                    <input type="date" name="vtv_vencimiento" id="vehiculo-vtv" class="input-field">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-vehiculo')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Vehiculo</button>
            </div>
        </form>
    </div>
</div>

<form id="form-borrar-vehiculo" method="POST" style="display:none;">
    <input type="hidden" name="action" value="borrar">
    <input type="hidden" name="id" id="borrar-vehiculo-id">
</form>

<script>
function prepararNuevoVehiculo() {
    document.getElementById('vehiculo-modal-title').innerText = "Registrar Nuevo Vehiculo";
    document.getElementById('vehiculo-action').value = "nuevo";
    document.getElementById('vehiculo-id').value = "";
    document.querySelector('#modal-vehiculo form').reset();
    openModal('modal-vehiculo');
}

function editVehiculo(data) {
    document.getElementById('vehiculo-modal-title').innerText = "Editar Vehiculo: " + data.dominio;
    document.getElementById('vehiculo-action').value = "editar";
    document.getElementById('vehiculo-id').value = data.id;
    document.getElementById('vehiculo-dominio').value = data.dominio;
    document.getElementById('vehiculo-acoplado').value = data.acoplado || '';
    document.getElementById('vehiculo-marca').value = data.marca || '';
    document.getElementById('vehiculo-modelo').value = data.modelo || '';
    document.getElementById('vehiculo-anio').value = data.anio || '';
    document.getElementById('vehiculo-chofer').value = data.chofer_id || '';
    document.getElementById('vehiculo-vtv').value = data.vtv_vencimiento || '';
    openModal('modal-vehiculo');
}

function confirmarBorrarVehiculo(id, dominio) {
    appConfirm("Seguro que deseas eliminar el vehiculo " + dominio + "? (borrado logico)", function() {
        document.getElementById('borrar-vehiculo-id').value = id;
        document.getElementById('form-borrar-vehiculo').submit();
    }, "Eliminar Vehiculo");
}
</script>
