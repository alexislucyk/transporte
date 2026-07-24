<?php
/**
 * Módulo de Configuración
 */

$mensaje = "";
$error = "";

// ─── CARGAR ADMINS CON SUS LÍMITES (para la pestaña Límites) ──
$admins_con_limites = [];
if (($_SESSION['user_role'] ?? '') === 'developer') {
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.full_name, u.created_at,
                   COALESCE(al.limite_empresas, 0) as limite_empresas,
                   COALESCE(al.limite_vehiculos, 0) as limite_vehiculos,
                   COALESCE(al.limite_choferes, 0) as limite_choferes
            FROM users u
            LEFT JOIN admin_limites al ON al.admin_id = u.id
            WHERE u.role = 'admin'
            ORDER BY u.username ASC
        ");
        $admins_con_limites = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Si la tabla admin_limites no existe, se ignora
    }
}

// ─── PROCESAR GUARDADO DE LÍMITES INDIVIDUALES (solo developer) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_limites_admin'])) {
    $isDev = ($_SESSION['user_role'] ?? '') === 'developer';
        if (!$isDev) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => "Solo el desarrollador puede configurar límites."
                ]);
                exit;
            }
            $error = "Solo el desarrollador puede configurar límites.";
    } else {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => "ID de administrador inválido."
                ]);
                exit;
            }
            $error = "ID de administrador inválido.";
        } else {
            $nuevo_limite_empresas  = max(0, (int)($_POST['limite_empresas'] ?? 0));
            $nuevo_limite_vehiculos = max(0, (int)($_POST['limite_vehiculos'] ?? 0));
            $nuevo_limite_choferes  = max(0, (int)($_POST['limite_choferes'] ?? 0));
            try {
                $pdo->prepare("
                    INSERT INTO admin_limites (admin_id, limite_empresas, limite_vehiculos, limite_choferes)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        limite_empresas = VALUES(limite_empresas),
                        limite_vehiculos = VALUES(limite_vehiculos),
                        limite_choferes = VALUES(limite_choferes)
                ")->execute([$admin_id, $nuevo_limite_empresas, $nuevo_limite_vehiculos, $nuevo_limite_choferes]);
                
                // Si es una petición AJAX, devolver JSON
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'mensaje' => 'Límites actualizados correctamente.',
                        'admin_id' => $admin_id,
                        'limite_empresas' => $nuevo_limite_empresas,
                        'limite_vehiculos' => $nuevo_limite_vehiculos,
                        'limite_choferes' => $nuevo_limite_choferes
                    ]);
                    exit;
                }
                
                $mensaje = "Límites actualizados correctamente para el administrador.";
            } catch (PDOException $e) {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => "Error al guardar límites: " . $e->getMessage()
                    ]);
                    exit;
                }
                $error = "Error al guardar límites: " . $e->getMessage();
            }
        }
    }
}

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
    <?php if (($_SESSION['user_role'] ?? '') === 'developer'): ?>
        <button class="tab-link" onclick="openTab(event, 'tab-limites')"><i class="fas fa-sliders-h"></i> Límites</button>
    <?php endif; ?>
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
                        <a href="config_permisos_usuarios?user_id=<?= $u['id'] ?>" title="Editar Permisos" style="background:none; border:none; color:#f39c12; cursor:pointer; margin-right:10px; text-decoration:none;">
                            <i class="fas fa-user-lock"></i>
                        </a>
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

<?php if (($_SESSION['user_role'] ?? '') === 'developer'): ?>
<div id="tab-limites" class="tab-content">
<div class="card" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3><i class="fas fa-sliders-h"></i> Límites de Gestión por Administrador</h3>
        <span class="badge" style="background:#e74c3c; color:#fff; font-size:0.8rem; padding:6px 12px;">
            <i class="fas fa-lock"></i> Solo Developer
        </span>
    </div>
    <p>Define los límites individuales para cada <strong>Administrador</strong> según el plan contratado. El valor <strong>0</strong> significa <em>sin límite</em>.</p>

    <?php if (empty($admins_con_limites)): ?>
        <div style="text-align:center; padding:30px; opacity:0.6;">
            <i class="fas fa-users fa-3x" style="display:block; margin-bottom:10px;"></i>
            No hay administradores registrados en el sistema.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Administrador</th>
                        <th>Usuario</th>
                        <th style="text-align:center;">Límite Empresas</th>
                        <th style="text-align:center;">Límite Vehículos</th>
                        <th style="text-align:center;">Límite Choferes</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins_con_limites as $admin): 
                        $tiene_limites = ((int)$admin['limite_empresas'] > 0 || (int)$admin['limite_vehiculos'] > 0 || (int)$admin['limite_choferes'] > 0);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($admin['full_name'] ?: $admin['username']) ?></strong></td>
                        <td><?= htmlspecialchars($admin['username']) ?></td>
                        <td style="text-align:center;">
                            <?php if ((int)$admin['limite_empresas'] > 0): ?>
                                <span class="badge" style="background:#3498db; color:#fff;"><?= (int)$admin['limite_empresas'] ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">∞</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ((int)$admin['limite_vehiculos'] > 0): ?>
                                <span class="badge" style="background:#e67e22; color:#fff;"><?= (int)$admin['limite_vehiculos'] ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">∞</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ((int)$admin['limite_choferes'] > 0): ?>
                                <span class="badge" style="background:#27ae60; color:#fff;"><?= (int)$admin['limite_choferes'] ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">∞</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <button onclick="abrirModalLimites(<?= (int)$admin['id'] ?>, '<?= htmlspecialchars($admin['full_name'] ?: $admin['username'], ENT_QUOTES) ?>', <?= (int)$admin['limite_empresas'] ?>, <?= (int)$admin['limite_vehiculos'] ?>, <?= (int)$admin['limite_choferes'] ?>)" 
                                    class="btn-primary btn-sm" style="background:<?= $tiene_limites ? '#e67e22' : '#3498db' ?>; border:none; padding:6px 12px; font-size:0.8rem; cursor:pointer;">
                                <i class="fas fa-sliders-h"></i> <?= $tiene_limites ? 'Modificar' : 'Configurar' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal Límites por Admin -->
<div id="modal-limites-admin" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #e74c3c, #c0392b); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-sliders-h" style="margin-right:8px;"></i> Límites del Administrador
            </h3>
            <span class="close-modal" onclick="closeModal('modal-limites-admin')" style="color:#fff; font-size:1.2rem; cursor:pointer;">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="guardar_limites_admin" value="1">
                <input type="hidden" name="admin_id" id="limites-admin-id">

                <p style="margin-top:0; font-size:1.05rem;">
                    Configurando límites para: <strong id="limites-admin-nombre"></strong>
                </p>
                <p style="font-size:0.85rem; color:#888; margin-bottom:16px;">
                    Establecé en 0 si no querés límite para ese recurso.
                </p>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:16px;">
                    <div class="form-group" style="margin:0; text-align:center;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem; color:#3498db;">
                            <i class="fas fa-industry"></i> Empresas
                        </label>
                        <input type="number" min="0" step="1" name="limite_empresas" id="limites-empresas" class="input-field" style="width:100%; font-size:1.3rem; text-align:center;">
                    </div>
                    <div class="form-group" style="margin:0; text-align:center;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem; color:#e67e22;">
                            <i class="fas fa-truck"></i> Vehículos
                        </label>
                        <input type="number" min="0" step="1" name="limite_vehiculos" id="limites-vehiculos" class="input-field" style="width:100%; font-size:1.3rem; text-align:center;">
                    </div>
                    <div class="form-group" style="margin:0; text-align:center;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem; color:#27ae60;">
                            <i class="fas fa-users"></i> Choferes
                        </label>
                        <input type="number" min="0" step="1" name="limite_choferes" id="limites-choferes" class="input-field" style="width:100%; font-size:1.3rem; text-align:center;">
                    </div>
                </div>

                <div style="background:#fef9e7; border-radius:8px; padding:12px; border:1px solid #f9e79f; font-size:0.85rem;">
                    <i class="fas fa-info-circle" style="color:#f39c12;"></i>
                    Los límites se aplican <strong>inmediatamente</strong>. Si un admin ya superó el nuevo límite, no podrá crear nuevas entidades hasta que se reduzca el conteo actual o se aumente el límite.
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px; border-top:1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-limites-admin')" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#e74c3c; border:none; padding:10px 18px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-save"></i> Guardar Límites
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalLimites(adminId, adminNombre, limiteEmpresas, limiteVehiculos, limiteChoferes) {
    document.getElementById('limites-admin-id').value = adminId;
    document.getElementById('limites-admin-nombre').textContent = adminNombre;
    document.getElementById('limites-empresas').value = limiteEmpresas;
    document.getElementById('limites-vehiculos').value = limiteVehiculos;
    document.getElementById('limites-choferes').value = limiteChoferes;
    openModal('modal-limites-admin');
}

// Manejar el envío del formulario de límites por AJAX
document.addEventListener('DOMContentLoaded', function() {
    const formLimites = document.querySelector('#modal-limites-admin form');
    if (formLimites) {
        formLimites.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar la tabla sin recargar
                    actualizarFilaTabla(data);
                    
                    // Mostrar mensaje de éxito
                    mostrarMensajeExito(data.mensaje);
                    
                    // Cerrar modal
                    closeModal('modal-limites-admin');
                } else {
                    mostrarError(data.error);
                }
            })
            .catch(error => {
                mostrarError('Error de conexión: ' + error.message);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            });
        });
    }
});

function actualizarFilaTabla(data) {
    const adminId = data.admin_id;
    // Buscar la fila que contiene el botón con este admin_id en el onclick
    const fila = document.querySelector(`tr button[onclick*="abrirModalLimites(${adminId},"]`)?.closest('tr');
    
    if (!fila) {
        // Si no se encontró, recargar la tabla entera (fallback)
        location.reload();
        return;
    }
    
    // Actualizar celdas de límites por posición directa
    const celdas = fila.querySelectorAll('td[style*="text-align:center"]');
    
    // celdas[0] = empresas, celdas[1] = vehiculos, celdas[2] = choferes, celdas[3] = acciones
    
    // Celda de empresas
    if (celdas[0]) {
        if (data.limite_empresas > 0) {
            celdas[0].innerHTML = `<span class="badge" style="background:#3498db; color:#fff;">${data.limite_empresas}</span>`;
        } else {
            celdas[0].innerHTML = `<span style="opacity:0.4;">∞</span>`;
        }
    }
    
    // Celda de vehículos
    if (celdas[1]) {
        if (data.limite_vehiculos > 0) {
            celdas[1].innerHTML = `<span class="badge" style="background:#e67e22; color:#fff;">${data.limite_vehiculos}</span>`;
        } else {
            celdas[1].innerHTML = `<span style="opacity:0.4;">∞</span>`;
        }
    }
    
    // Celda de choferes
    if (celdas[2]) {
        if (data.limite_choferes > 0) {
            celdas[2].innerHTML = `<span class="badge" style="background:#27ae60; color:#fff;">${data.limite_choferes}</span>`;
        } else {
            celdas[2].innerHTML = `<span style="opacity:0.4;">∞</span>`;
        }
    }
    
    // Actualizar botón de acción (celdas[3] o buscarlo directamente)
    const btnAccion = fila.querySelector('button[onclick*="abrirModalLimites"]');
    if (btnAccion) {
        const tieneLimites = data.limite_empresas > 0 || data.limite_vehiculos > 0 || data.limite_choferes > 0;
        btnAccion.style.background = tieneLimites ? '#e67e22' : '#3498db';
        btnAccion.innerHTML = `<i class="fas fa-sliders-h"></i> ${tieneLimites ? 'Modificar' : 'Configurar'}`;
    }
}

function mostrarMensajeExito(mensaje) {
    // Remover mensajes anteriores
    const mensajesAnteriores = document.querySelectorAll('.alert-success, .alert-exito');
    mensajesAnteriores.forEach(el => el.remove());
    
    const divMensaje = document.createElement('div');
    divMensaje.className = 'alert-success';
    divMensaje.style.cssText = 'background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    divMensaje.innerHTML = `<i class="fas fa-check-circle"></i> ${mensaje}`;
    
    document.body.appendChild(divMensaje);
    
    // Ocultar después de 3 segundos
    setTimeout(() => {
        divMensaje.style.transition = 'opacity 0.5s';
        divMensaje.style.opacity = '0';
        setTimeout(() => divMensaje.remove(), 500);
    }, 3000);
}

function mostrarError(mensaje) {
    // Remover errores anteriores
    const erroresAnteriores = document.querySelectorAll('.alert-error');
    erroresAnteriores.forEach(el => el.remove());
    
    const divError = document.createElement('div');
    divError.className = 'alert-error';
    divError.style.cssText = 'background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    divError.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
    
    document.body.appendChild(divError);
    
    // Ocultar después de 4 segundos
    setTimeout(() => {
        divError.style.transition = 'opacity 0.5s';
        divError.style.opacity = '0';
        setTimeout(() => divError.remove(), 500);
    }, 4000);
}
</script>
<?php endif; ?>

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
                        'viajes' => 'Viajes', 'choferes' => 'Choferes', 'choferes_ctacte' => 'Cta Cte Choferes',
                        'choferes_liquidar' => 'Liquidar Choferes', 'cobranzas' => 'Cobranzas',
                        'comisionistas' => 'Comisionistas', 'comisionistas_ctacte' => 'Cta Cte Comisiones', 'vehiculos' => 'Vehículos',
                        'clientes' => 'Clientes', 'mantenimiento' => 'Mantenimiento',
                        'tesoreria' => 'Tesorería', 'cuentas' => 'Cuentas',
                        'empresas' => 'Empresas', 'configuracion' => 'Configuración',
                        'config_permisos_usuarios' => 'Permisos Usuarios', 'auditoria' => 'Auditoría',
                        'importar_carta_porte' => 'Importar Carta Porte PDF'
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