<?php
/**
 * Módulo de Configuración
 */

$mensaje = "";
$error = "";

// Procesar el guardado si viene por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tema'])) {
    $nuevoTema = $_POST['tema'];
    
    // Validar que el tema exista
    if (array_key_exists($nuevoTema, $themes)) {
        try {
            $stmt = $pdo->prepare("UPDATE configuraciones SET valor = ? WHERE clave = 'tema'");
            $stmt->execute([$nuevoTema]);
            
            // Redireccionar para aplicar cambios
            header("Location: " . $base_path . "configuracion?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar en la base de datos: " . $e->getMessage();
        }
    }
}

// Procesar Gestión de Usuarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];

        // --- REGLAS MULTI-ADMIN ---
        // - Solo developer puede crear admin.
        // - Un admin solo puede operar sobre usuarios creados por él (created_by = $_SESSION['user_id']).
        // - Un developer puede operar sobre todos.
        $isDeveloper = ($_SESSION['user_role'] ?? '') === 'developer';
        $currentAdminId = $_SESSION['user_id'];

        // Acción: crear usuario
        if ($action === 'nuevo_usuario') {
            $targetRole = $_POST['role'] ?? 'user';
            
            if ($targetRole === 'admin' && !$isDeveloper) {
                throw new Exception("Solo el desarrollador (developer) puede crear usuarios con rol admin.");
            }

// Para admin: solo puede crear usuarios "hijos" de su propio admin.
            // Para developer: created_by se setea a su id.
            $hashedPass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['username'],
                $hashedPass,
                $_POST['full_name'],
                $targetRole,
                $currentAdminId
            ]);

            // Si un admin crea un usuario 'user', ese usuario debe heredar su admin raíz.
            // Esto se usa en index.php para filtrar empresas y evitar ver empresas de otros admins.
            // Para el login actual no hace falta persistir; se resuelve en login.
            
            $mensaje = "Usuario creado correctamente.";
        }

        // Acción: eliminar/cambiar permisos/cambiar password
        if (in_array($action, ['eliminar_usuario', 'cambiar_password', 'actualizar_permisos'], true)) {
            $targetUserId = (int)($_POST['user_id'] ?? 0);

            if ($targetUserId <= 0) {
                throw new Exception("Usuario inválido.");
            }

            if ($targetUserId !== (int)$_SESSION['user_id']) {
                if (!$isDeveloper) {
                    $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE id = ? AND created_by = ?");
                    $stmtOwner->execute([$targetUserId, $currentAdminId]);
                    $ownerRow = $stmtOwner->fetch();
                    if (!$ownerRow) {
                        throw new Exception("No podés gestionar usuarios de otro admin.");
                    }
                }
            }
        }

        if ($action === 'eliminar_usuario') {
            if ((int)$_POST['user_id'] === (int)$_SESSION['user_id']) {
                throw new Exception("No puedes eliminar tu propio usuario mientras estás en sesión.");
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $mensaje = "Usuario eliminado.";
        }

        if ($action === 'cambiar_password') {
            $hashedPass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPass, $_POST['user_id']]);
            $mensaje = "Contraseña de " . $_POST['target_username'] . " actualizada correctamente.";
        }

        if ($action === 'actualizar_permisos') {
            $targetUserId = (int)$_POST['user_id'];

            // Developer: permite. Admin: ya validamos ownership arriba.
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$targetUserId]);
            if (!empty($_POST['modules'])) {
                $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, module) VALUES (?, ?)");
                foreach ($_POST['modules'] as $mod) {
                    $stmt->execute([$targetUserId, $mod]);
                }
            }
            $pdo->commit();
            
            // Si se edita a sí mismo, actualizar sesión inmediatamente
            if ($targetUserId === (int)$_SESSION['user_id']) {
                $_SESSION['user_permissions'] = $_POST['modules'] ?? [];
            }
            $mensaje = "Permisos actualizados correctamente.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

if ($user_role === 'developer') {
    $usuarios = $pdo->query("SELECT u.*, (SELECT GROUP_CONCAT(module) FROM user_permissions WHERE user_id = u.id) as permissions FROM users u ORDER BY username ASC")->fetchAll();
} else {
    $stmt_u = $pdo->prepare("SELECT u.*, (SELECT GROUP_CONCAT(module) FROM user_permissions WHERE user_id = u.id) as permissions FROM users u WHERE u.id = ? OR u.created_by = ? ORDER BY username ASC");
    $stmt_u->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $usuarios = $stmt_u->fetchAll();
}

if (isset($_GET['success'])) $mensaje = $_GET['msg'] ?? "Configuración actualizada correctamente.";
?>
<h1>Configuración del Sistema</h1>
<p>Personaliza la apariencia y el comportamiento de Trans Cargo Hub.</p>

<style>
    .theme-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .theme-card { border: 2px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; transition: 0.3s; position: relative; }
    .theme-radio:checked + .theme-card { border-color: var(--accent); box-shadow: 0 0 10px rgba(0,0,0,0.1); background-color: rgba(0,0,0,0.02); }
    .theme-preview { display: flex; height: 40px; border-radius: 4px; overflow: hidden; margin-bottom: 10px; border: 1px solid #eee; }
    .theme-active-badge { color: var(--accent); font-size: 0.8rem; display: block; margin-top: 5px; }
    
    .tab-menu { display: flex; border-bottom: 2px solid rgba(0,0,0,0.1); margin-bottom: 25px; gap: 10px; }
    .tab-link { padding: 12px 25px; cursor: pointer; border: none; background: none; font-weight: bold; color: #7f8c8d; border-bottom: 3px solid transparent; transition: 0.3s; font-size: 1rem; }
    .tab-link:hover { color: var(--accent); }
    .tab-link.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab-content { display: none; animation: fadeIn 0.3s; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
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

<div class="tab-menu">
    <button class="tab-link active" onclick="openTab(event, 'tab-general')"><i class="fas fa-palette"></i> Apariencia</button>
    <button class="tab-link" onclick="openTab(event, 'tab-usuarios')"><i class="fas fa-users-cog"></i> Usuarios</button>
</div>

<div id="tab-general" class="tab-content active">
<div class="card">
    <h3>Apariencia</h3>
    <p>Selecciona el tema visual que prefieras para la interfaz:</p>
    
    <form method="POST" action="configuracion">
        <div class="theme-grid">
            
            <?php foreach($themes as $name => $colors): ?>
            <label style="cursor: pointer;">
                <input type="radio" name="tema" value="<?= $name ?>" <?= $currentTheme == $name ? 'checked' : '' ?> class="theme-radio" style="display:none">
                <div class="theme-card" style="background: <?= $colors['card'] ?>;">
                    <div class="theme-preview">
                        <div style="width: 30%; background: <?= $colors['primary'] ?>;"></div>
                        <div style="width: 70%; background: <?= $colors['bg'] ?>;"></div>
                    </div>
                    <strong style="color: <?= $colors['text'] ?>; text-transform: capitalize;"><?= $name ?></strong>
                    <?php if($currentTheme == $name): ?>
                        <span class="theme-active-badge"><i class="fas fa-check-circle"></i> Actual</span>
                    <?php endif; ?>
                </div>
            </label>
            <?php endforeach; ?>

        </div>

        <button type="submit" style="background: var(--accent); color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1rem;">
            <i class="fas fa-save"></i> Guardar Configuración
        </button>
    </form>
</div>
<div class="card" style="margin-top: 20px;">
    <h3>Sobre Sistemas Lucyk</h3>
    <p>Trans Cargo Hub es una solución diseñada para optimizar la logística y el transporte de cargas.</p>
    <p>Versión: 1.0.0-dev</p>
</div>
</div>

<div id="tab-usuarios" class="tab-content">
<div class="card" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>Gestión de Usuarios</h3>
        <button onclick="openModal('modal-usuario')" class="btn-primary" style="font-size: 0.85rem; padding: 8px 15px;">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </button>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre Completo</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th style="text-align:center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($usuarios as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><span class="badge" style="background: #ecf0f1; color: #7f8c8d;"><?= strtoupper($u['role']) ?></span></td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td style="text-align:center">
                        <button onclick="abrirModalPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" title="Cambiar Contraseña" style="background:none; border:none; color:var(--accent); cursor:pointer; margin-right:10px;">
                            <i class="fas fa-key"></i>
                        </button>
                        <button onclick="abrirModalPermisos(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= $u['permissions'] ?>')" title="Editar Permisos" style="background:none; border:none; color:#f39c12; cursor:pointer; margin-right:10px;">
                            <i class="fas fa-user-lock"></i>
                        </button>
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <button onclick="confirmarEliminarUsuario(<?= $u['id'] ?>, '<?= $u['username'] ?>')" style="background:none; border:none; color:#e74c3c; cursor:pointer;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php else: ?>
                            <small style="opacity:0.5">Tú</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Modal Nuevo Usuario -->
<div id="modal-usuario" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Registrar Nuevo Usuario</h3>
            <span class="close-modal" onclick="closeModal('modal-usuario')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="nuevo_usuario">
            <div class="modal-body">
                <div class="form-group"><label>Nombre Completo</label><input type="text" name="full_name" class="input-field" required></div>
                <div class="form-group"><label>Nombre de Usuario</label><input type="text" name="username" class="input-field" required></div>
                <div class="form-group"><label>Contraseña</label><input type="password" name="password" class="input-field" required></div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" class="input-field">
                        <option value="user">Operador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cambiar Contraseña -->
<div id="modal-password" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Cambiar Contraseña</h3>
            <span class="close-modal" onclick="closeModal('modal-password')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="cambiar_password">
            <input type="hidden" name="user_id" id="pass-user-id">
            <input type="hidden" name="target_username" id="pass-target-username">
            <div class="modal-body">
                <p>Nueva contraseña para: <strong id="pass-display-name"></strong></p>
                <div class="form-group">
                    <label>Contraseña Nueva</label>
                    <input type="password" name="new_password" class="input-field" required minlength="4">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Actualizar Contraseña</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Permisos -->
<div id="modal-permisos" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Permisos de Acceso</h3>
            <span class="close-modal" onclick="closeModal('modal-permisos')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="actualizar_permisos">
            <input type="hidden" name="user_id" id="perm-user-id">
            <div class="modal-body">
                <p>Módulos permitidos para: <strong id="perm-display-name"></strong></p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <?php 
                    $modulos_lista = [
                        'viajes' => 'Viajes', 'choferes' => 'Choferes', 'cobranzas' => 'Cobranzas',
                        'comisionistas' => 'Comisionistas', 'vehiculos' => 'Vehículos',
                        'clientes' => 'Clientes', 'mantenimiento' => 'Mantenimiento',
                        'tesoreria' => 'Tesorería', 'transportistas' => 'Empresas',
                        'configuracion' => 'Configuración'
                    ];
                    foreach ($modulos_lista as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="modules[]" value="<?= $key ?>" class="perm-check"> <?= $label ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary">Guardar Permisos</button>
            </div>
        </form>
    </div>
</div>

<form id="form-delete-user" method="POST" style="display:none;">
    <input type="hidden" name="action" value="eliminar_usuario">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
function confirmarEliminarUsuario(id, name) {
    appConfirm(`¿Estás seguro de eliminar el acceso al usuario "${name}"?`, function() {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('form-delete-user').submit();
    }, "Eliminar Usuario");
}

function abrirModalPassword(id, name) {
    document.getElementById('pass-user-id').value = id;
    document.getElementById('pass-target-username').value = name;
    document.getElementById('pass-display-name').innerText = name;
    openModal('modal-password');
}

function abrirModalPermisos(id, name, permsString) {
    document.getElementById('perm-user-id').value = id;
    document.getElementById('perm-display-name').innerText = name;
    const perms = permsString ? permsString.split(',') : [];
    document.querySelectorAll('.perm-check').forEach(cb => {
        cb.checked = perms.includes(cb.value);
    });
    openModal('modal-permisos');
}

function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
    localStorage.setItem('config-active-tab', tabName);
}

document.addEventListener('DOMContentLoaded', function() {
    const activeTab = localStorage.getItem('config-active-tab') || 'tab-general';
    const btn = document.querySelector(`button[onclick*="${activeTab}"]`);
    if(btn) btn.click();
});
</script>