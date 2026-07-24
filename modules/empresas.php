<?php
/**
 * Módulo de Gestión de Empresas (Transportistas) - Trans Cargo Hub
 * Módulo canónico (consolida lo que antes hacía transportistas.php).
 */

$mensaje = "";
$error = "";

// --- HELPERS DE AUTORIZACIÓN (multi-tenant seguro) ---
function empresaOwner(PDO $pdo, int $id, int $currentUserId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    
    // Usar la misma lógica que index.php para determinar adminRootId
    $adminRootId = $_SESSION['admin_root_id'] ?? null;
    if (!$adminRootId) {
        if ($currentRole === 'developer' || $currentRole === 'admin') {
            // Developer y Admin son su propio root
            $adminRootId = $currentUserId;
        } else {
            // Usuario normal: buscar el admin que lo creó
            $stmtAdmin = $pdo->prepare("SELECT created_by FROM users WHERE id = ? AND role <> 'developer' LIMIT 1");
            $stmtAdmin->execute([$currentUserId]);
            $adminRootId = (int)($stmtAdmin->fetchColumn() ?: 0);
            if (!$adminRootId) {
                $adminRootId = $currentUserId;
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT id FROM transportistas WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $adminRootId]);
    return (bool)$stmt->fetchColumn();
}

// --- PROCESAR ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentRole = $_SESSION['user_role'] ?? 'user';
    $currentUserId = (int)$_SESSION['user_id'];

    // Solo para acciones nuevo/editar (en borrar no vienen estos campos)
    $razon_social = trim($_POST['razon_social'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($_POST['action'] === 'nuevo') {
        // Determinar el admin_root_id igual que en index.php para consistencia
        $adminRootId = $_SESSION['admin_root_id'] ?? null;
        if (!$adminRootId) {
            $userRole = $_SESSION['user_role'] ?? 'user';
            if ($userRole === 'developer' || $userRole === 'admin') {
                // Developer y Admin son su propio root
                $adminRootId = $currentUserId;
            } else {
                // Usuario normal: buscar el admin que lo creó
                $stmtAdmin = $pdo->prepare("SELECT created_by FROM users WHERE id = ? AND role <> 'developer' LIMIT 1");
                $stmtAdmin->execute([$currentUserId]);
                $adminRootId = (int)($stmtAdmin->fetchColumn() ?: 0);
                if (!$adminRootId) {
                    $adminRootId = $currentUserId;
                }
            }
        }
        
        // Verificar límite de empresas (solo para admins, no developer)
        if ($currentRole !== 'developer') {
            $check = verificarLimite($pdo, 'empresas', $adminRootId);
            if (!$check['permitido']) {
                $error = $check['mensaje'];
            }
        }
        if (empty($error)) {
            try {
                $sql = "INSERT INTO transportistas (razon_social, cuit, direccion, telefono, email, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$razon_social, $cuit, $direccion, $telefono, $email, $adminRootId]);
                $nuevaId = (int)$pdo->lastInsertId();
                
                // Registrar auditoría de creación
                registrarAuditoria($pdo, $currentUserId, 'crear', 'empresas', 
                    "Nueva empresa registrada: {$razon_social} (CUIT: {$cuit})",
                    null,
                    ['id' => $nuevaId, 'razon_social' => $razon_social, 'cuit' => $cuit, 'direccion' => $direccion, 'telefono' => $telefono, 'email' => $email]
                );
                
                $mensaje = "Empresa registrada exitosamente.";
            } catch (PDOException $e) {
                $error = ($e->getCode() == 23000) ? "Error: El CUIT ya existe." : "Error: " . $e->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'borrar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !empresaOwner($pdo, $id, $currentUserId, $currentRole)) {
            $error = "No autorizado: la empresa no existe o pertenece a otro administrador.";
        } else {
            try {
                // Obtener datos anteriores para auditoría
                $stmtDatos = $pdo->prepare("SELECT razon_social, cuit, direccion, telefono, email FROM transportistas WHERE id = ?");
                $stmtDatos->execute([$id]);
                $datosAnteriores = $stmtDatos->fetch(PDO::FETCH_ASSOC);
                
                $sql = "UPDATE transportistas SET activo = 0 WHERE id=?";
                $pdo->prepare($sql)->execute([$id]);
                
                // Registrar auditoría de eliminación
                registrarAuditoria($pdo, $currentUserId, 'eliminar', 'empresas', 
                    "Empresa eliminada (borrado lógico): " . ($datosAnteriores['razon_social'] ?? 'ID: ' . $id),
                    $datosAnteriores,
                    ['activo' => 0, 'tipo_eliminacion' => 'borrado_logico']
                );
                
                $mensaje = "Empresa eliminada (borrado lógico) correctamente.";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !empresaOwner($pdo, $id, $currentUserId, $currentRole)) {
            $error = "No autorizado: la empresa no existe o pertenece a otro administrador.";
        } else {
            try {
                // Obtener datos anteriores para auditoría
                $stmtDatos = $pdo->prepare("SELECT razon_social, cuit, direccion, telefono, email FROM transportistas WHERE id = ?");
                $stmtDatos->execute([$id]);
                $datosAnteriores = $stmtDatos->fetch(PDO::FETCH_ASSOC);
                
                $sql = "UPDATE transportistas SET razon_social=?, cuit=?, direccion=?, telefono=?, email=? WHERE id=?";
                $pdo->prepare($sql)->execute([$razon_social, $cuit, $direccion, $telefono, $email, $id]);
                
                // Registrar auditoría de edición
                registrarAuditoria($pdo, $currentUserId, 'editar', 'empresas', 
                    "Empresa actualizada: " . ($datosAnteriores['razon_social'] ?? 'ID: ' . $id),
                    $datosAnteriores,
                    ['razon_social' => $razon_social, 'cuit' => $cuit, 'direccion' => $direccion, 'telefono' => $telefono, 'email' => $email]
                );
                
                $mensaje = "Empresa actualizada correctamente.";
            } catch (PDOException $e) {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}

// --- OBTENER DATOS ---
if ($_SESSION['user_role'] === 'developer') {
    $stmt = $pdo->query("SELECT * FROM transportistas WHERE activo = 1 ORDER BY razon_social ASC");
    $empresas = $stmt->fetchAll();
} else {
    // Usar la misma lógica que index.php para determinar adminRootId
    $adminRootId = $_SESSION['admin_root_id'] ?? null;
    if (!$adminRootId) {
        $userRole = $_SESSION['user_role'] ?? 'user';
        if ($userRole === 'developer' || $userRole === 'admin') {
            // Developer y Admin son su propio root
            $adminRootId = $_SESSION['user_id'];
        } else {
            // Usuario normal: buscar el admin que lo creó
            $stmtAdmin = $pdo->prepare("SELECT created_by FROM users WHERE id = ? AND role <> 'developer' LIMIT 1");
            $stmtAdmin->execute([$_SESSION['user_id']]);
            $adminRootId = (int)($stmtAdmin->fetchColumn() ?: 0);
            if (!$adminRootId) {
                $adminRootId = $_SESSION['user_id'];
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM transportistas WHERE created_by = ? AND activo = 1 ORDER BY razon_social ASC");
    $stmt->execute([$adminRootId]);
    $empresas = $stmt->fetchAll();
}
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
                    <button onclick="confirmarBorrarEmpresa(<?= (int)$emp['id'] ?>)" title="Borrar (borrado lógico)" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:8px;"><i class="fas fa-trash-alt"></i></button>
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

function confirmarBorrarEmpresa(id) {
    appConfirm("¿Seguro que deseas borrar esta empresa? (borrado lógico)", function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'borrar';

        const empId = document.createElement('input');
        empId.type = 'hidden';
        empId.name = 'id';
        empId.value = id;

        form.appendChild(action);
        form.appendChild(empId);
        document.body.appendChild(form);
        form.submit();
    }, "Confirmar borrado lógico");
}
</script>