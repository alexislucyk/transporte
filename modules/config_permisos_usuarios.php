<?php
/**
 * Módulo de Permisos de Usuario
 * Muestra y permite editar los permisos de un usuario específico.
 * Se accede desde configuracion.php con ?user_id=X
 */

$mensaje = "";
$error = "";

// ─── VALIDAR USER_ID ──
$target_user_id = (int)($_GET['user_id'] ?? 0);
if ($target_user_id <= 0) {
    echo "<div class='card' style='text-align:center; padding:60px 20px; max-width:500px; margin:40px auto;'>";
    echo "<i class='fas fa-exclamation-triangle fa-4x' style='color:#e74c3c; margin-bottom:20px;'></i>";
    echo "<h2>Usuario no especificado</h2>";
    echo "<p style='opacity:0.7; margin-bottom:25px;'>No se recibió un ID de usuario válido.</p>";
    echo "<a href='configuracion' class='btn-primary' style='background:#34495e;'><i class='fas fa-arrow-left'></i> Volver a Configuración</a>";
    echo "</div>";
    return;
}

// ─── CARGAR DATOS DEL USUARIO ──
try {
    $stmt_user = $pdo->prepare("SELECT id, username, full_name, role, created_by FROM users WHERE id = ?");
    $stmt_user->execute([$target_user_id]);
    $target_user = $stmt_user->fetch();

    if (!$target_user) {
        echo "<div class='card' style='text-align:center; padding:60px 20px; max-width:500px; margin:40px auto;'>";
        echo "<i class='fas fa-user-slash fa-4x' style='color:#e74c3c; margin-bottom:20px;'></i>";
        echo "<h2>Usuario no encontrado</h2>";
        echo "<p style='opacity:0.7; margin-bottom:25px;'>El usuario solicitado no existe o fue eliminado.</p>";
        echo "<a href='configuracion' class='btn-primary' style='background:#34495e;'><i class='fas fa-arrow-left'></i> Volver a Configuración</a>";
        echo "</div>";
        return;
    }
} catch (PDOException $e) {
    $error = "Error al cargar usuario: " . $e->getMessage();
}

// ─── CONTROL DE ACCESO ──
// - Developer: puede editar permisos de cualquier usuario
// - Admin: solo puede editar permisos de usuarios que él mismo creó (created_by = su id)
// - User: no puede editar permisos
$isDeveloper = ($_SESSION['user_role'] ?? '') === 'developer';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$currentUserId = (int)$_SESSION['user_id'];

$accesoPermitido = false;
if ($isDeveloper) {
    $accesoPermitido = true;
} elseif ($isAdmin) {
    // Admin puede editar usuarios que creó (created_by = su id) o a sí mismo
    if ((int)$target_user['created_by'] === $currentUserId || $target_user_id === $currentUserId) {
        $accesoPermitido = true;
    }
}

if (!$accesoPermitido) {
    echo "<div class='card' style='text-align:center; padding:60px 20px; max-width:500px; margin:40px auto; border-top:5px solid #e74c3c;'>";
    echo "<i class='fas fa-user-shield fa-4x' style='color:#e74c3c; margin-bottom:20px;'></i>";
    echo "<h2>Acceso Restringido</h2>";
    echo "<p style='opacity:0.7; margin-bottom:25px;'>No tenés permisos para modificar los accesos de este usuario.</p>";
    echo "<a href='configuracion' class='btn-primary' style='background:#34495e;'><i class='fas fa-arrow-left'></i> Volver a Configuración</a>";
    echo "</div>";
    return;
}

// ─── CARGAR PERMISOS DEL ADMIN LOGUEADO ──
// El admin solo puede habilitar módulos que él mismo tenga habilitados por el developer.
$mis_permisos = []; // Solo se usa si es admin
if ($isAdmin) {
    try {
        $stmt_mis_perm = $pdo->prepare("SELECT module FROM user_permissions WHERE user_id = ?");
        $stmt_mis_perm->execute([$currentUserId]);
        $mis_permisos = $stmt_mis_perm->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error = "Error al cargar tus permisos: " . $e->getMessage();
    }
}

// ─── CARGAR PERMISOS ACTUALES ──
$permisos_actuales = [];
try {
    $stmt_perm = $pdo->prepare("SELECT module FROM user_permissions WHERE user_id = ?");
    $stmt_perm->execute([$target_user_id]);
    $permisos_actuales = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error al cargar permisos: " . $e->getMessage();
}

// ─── PROCESAR GUARDADO ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_permisos'])) {
    try {
        $modulos_a_guardar = !empty($_POST['modules']) ? $_POST['modules'] : [];
        
        // Validación: admin solo puede guardar módulos que él mismo tiene habilitados
        if ($isAdmin) {
            foreach ($modulos_a_guardar as $mod) {
                if (!in_array(trim($mod), $mis_permisos)) {
                    throw new Exception("No podés habilitar el módulo '" . htmlspecialchars($mod) . "' porque vos no lo tenés asignado.");
                }
            }
        }
        
        $pdo->beginTransaction();
        
        // Eliminar permisos actuales
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$target_user_id]);
        
        // Insertar nuevos permisos
        if (!empty($modulos_a_guardar)) {
            $stmt_insert = $pdo->prepare("INSERT INTO user_permissions (user_id, module) VALUES (?, ?)");
            foreach ($modulos_a_guardar as $mod) {
                $stmt_insert->execute([$target_user_id, trim($mod)]);
            }
        }
        
        $pdo->commit();
        
        // Si se editó a sí mismo, actualizar sesión inmediatamente
        if ($target_user_id === $currentUserId) {
            $_SESSION['user_permissions'] = $modulos_a_guardar;
        }
        
        // Recargar permisos actualizados
        $stmt_perm = $pdo->prepare("SELECT module FROM user_permissions WHERE user_id = ?");
        $stmt_perm->execute([$target_user_id]);
        $permisos_actuales = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
        
        $mensaje = "Permisos actualizados correctamente para <strong>" . htmlspecialchars($target_user['full_name'] ?: $target_user['username']) . "</strong>.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al guardar permisos: " . $e->getMessage();
    }
}

// ─── LISTA DE MÓDULOS DISPONIBLES ──
$modulos_disponibles = [
    'viajes'                => ['label' => 'Viajes',                'icon' => 'fa-truck-moving'],
    'choferes'              => ['label' => 'Choferes',              'icon' => 'fa-users'],
    'choferes_ctacte'       => ['label' => 'Cta Cte Choferes',     'icon' => 'fa-file-invoice-dollar'],
    'choferes_liquidar'     => ['label' => 'Liquidar Choferes',    'icon' => 'fa-calculator'],
    'cobranzas'             => ['label' => 'Cobranzas',             'icon' => 'fa-hand-holding-usd'],
    'comisionistas'         => ['label' => 'Comisionistas',         'icon' => 'fa-percentage'],
    'comisionistas_ctacte'  => ['label' => 'Cta Cte Comisiones',   'icon' => 'fa-file-invoice'],
    'vehiculos'             => ['label' => 'Vehículos',             'icon' => 'fa-truck'],
    'clientes'              => ['label' => 'Clientes',              'icon' => 'fa-building'],
    'mantenimiento'         => ['label' => 'Mantenimiento',         'icon' => 'fa-tools'],
    'tesoreria'             => ['label' => 'Tesorería',             'icon' => 'fa-coins'],
    'cuentas'               => ['label' => 'Cuentas',               'icon' => 'fa-wallet'],
    'empresas'              => ['label' => 'Empresas',              'icon' => 'fa-industry'],
    'configuracion'         => ['label' => 'Configuración',         'icon' => 'fa-cog'],
    'config_permisos_usuarios' => ['label' => 'Permisos Usuarios', 'icon' => 'fa-user-lock'],
    'auditoria'             => ['label' => 'Auditoría',             'icon' => 'fa-clipboard-list'],
    'importar_carta_porte'  => ['label' => 'Importar Carta Porte PDF', 'icon' => 'fa-file-pdf'],
];

// ─── FILTRAR MÓDULOS SEGÚN EL ROL ──
// Developer ve todos. Admin solo ve los módulos que él mismo tiene habilitados.
$modulos_visibles = $modulos_disponibles;
if ($isAdmin) {
    $modulos_visibles = [];
    foreach ($modulos_disponibles as $key => $mod) {
        if (in_array($key, $mis_permisos)) {
            $modulos_visibles[$key] = $mod;
        }
    }
}
?>
<h1>
    <i class="fas fa-user-lock"></i> Permisos de Usuario
</h1>
<p style="margin-bottom:25px;">
    Gestioná los módulos a los que <strong><?= htmlspecialchars($target_user['full_name'] ?: $target_user['username']) ?></strong> tendrá acceso.
</p>

<?php if ($isAdmin): ?>
    <div style="background:#fef9e7; border:1px solid #f9e79f; border-radius:8px; padding:12px 16px; margin-bottom:20px; display:flex; align-items:center; gap:10px; font-size:0.9rem;">
        <i class="fas fa-info-circle" style="color:#f39c12; font-size:1.1rem;"></i>
        <span>Solamente ves los módulos que el <strong>Developer</strong> te habilitó. Para gestionar otros módulos, contactá al desarrollador.</span>
    </div>
<?php endif; ?>


<?php if ($mensaje): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-check-circle"></i> <?= $mensaje ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="permisos-container">
    <!-- Barra de información del usuario -->
    <div class="user-info-bar">
        <div class="user-avatar">
            <?= strtoupper(substr($target_user['full_name'] ?: $target_user['username'], 0, 1)) ?>
        </div>
        <div class="user-details">
            <div class="user-name"><?= htmlspecialchars($target_user['full_name'] ?: $target_user['username']) ?></div>
            <div class="user-meta">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($target_user['username']) ?></span>
                <span>
                    <i class="fas fa-tag"></i>
                    <span class="badge-role <?= $target_user['role'] ?>"><?= strtoupper($target_user['role']) ?></span>
                </span>
                <span><i class="fas fa-hashtag"></i> ID: <?= (int)$target_user['id'] ?></span>
            </div>
        </div>
        <div style="margin-left:auto;">
            <a href="configuracion" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Card de permisos -->
    <div class="permisos-card">
        <div class="permisos-card-header">
            <i class="fas fa-check-double" style="font-size:1.2rem;"></i>
            <h3>Módulos de Acceso</h3>
            <span style="margin-left:auto; font-size:0.85rem; opacity:0.9;">
                <span id="selected-count"><?= count($permisos_actuales) ?></span> / <?= count($modulos_visibles) ?> seleccionados
            </span>
        </div>
        <div class="permisos-card-body">
            <form method="POST" id="form-permisos">
                <div class="select-all-bar">
                    <span style="font-size:0.85rem; color:#7f8c8d;">Selección rápida:</span>
                    <button type="button" onclick="seleccionarTodos(true)"><i class="fas fa-check-square"></i> Todos</button>
                    <button type="button" onclick="seleccionarTodos(false)"><i class="fas fa-square"></i> Ninguno</button>
                    <button type="button" onclick="seleccionarPorRol()"><i class="fas fa-user-tag"></i> Por defecto (Operador)</button>
                </div>

                <div class="permisos-grid" id="permisos-grid">
                    <?php foreach ($modulos_visibles as $key => $mod): 
                        $checked = in_array($key, $permisos_actuales) ? 'checked' : '';
                    ?>
                    <label class="permiso-item <?= $checked ? 'checked' : '' ?>">
                        <input type="checkbox" name="modules[]" value="<?= $key ?>" class="perm-check" <?= $checked ?> onchange="this.parentElement.classList.toggle('checked')">
                        <span class="permiso-icon"><i class="fas <?= $mod['icon'] ?>"></i></span>
                        <span class="permiso-label"><?= $mod['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="permisos-actions" style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" name="guardar_permisos" value="1" class="btn-primary" style="display:inline-flex; align-items:center; gap:8px; padding:12px 25px; font-size:1rem;">
                        <i class="fas fa-save"></i> Guardar Permisos
                    </button>
                    <a href="configuracion" class="btn-secondary" style="display:inline-flex; align-items:center; gap:6px; padding:12px 25px; text-decoration:none;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function seleccionarTodos(seleccionar) {
    document.querySelectorAll('.perm-check').forEach(cb => {
        cb.checked = seleccionar;
        cb.parentElement.classList.toggle('checked', seleccionar);
    });
    actualizarContador();
}

function seleccionarPorRol() {
    // Módulos por defecto para un operador estándar
    const modulosDefault = [
        'viajes', 'choferes', 'choferes_ctacte', 'choferes_liquidar',
        'cobranzas', 'comisionistas', 'comisionistas_ctacte',
        'vehiculos', 'clientes', 'mantenimiento', 'tesoreria', 'cuentas'
    ];
    document.querySelectorAll('.perm-check').forEach(cb => {
        const seleccionar = modulosDefault.includes(cb.value);
        cb.checked = seleccionar;
        cb.parentElement.classList.toggle('checked', seleccionar);
    });
    actualizarContador();
}

function actualizarContador() {
    const total = document.querySelectorAll('.perm-check:checked').length;
    const contador = document.getElementById('selected-count');
    if (contador) contador.textContent = total;
}

// Actualizar contador al cargar
document.addEventListener('DOMContentLoaded', function() {
    actualizarContador();
    
    // Actualizar contador cuando cambia cualquier checkbox
    document.querySelectorAll('.perm-check').forEach(cb => {
        cb.addEventListener('change', actualizarContador);
    });
});
</script>