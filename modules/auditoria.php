<?php
/**
 * Módulo de Registro de Auditoría - Trans Cargo Hub
 * Solo accesible para el rol 'developer'
 * Muestra todos los movimientos realizados por admins y usuarios
 */

// Verificar que el usuario sea developer
if ($_SESSION['user_role'] !== 'developer') {
    die("Acceso restringido. Solo disponible para desarrolladores.");
}

$mensaje = "";
$error = "";

// --- FILTROS DE BÚSQUEDA ---
$filtro_usuario = trim($_GET['usuario'] ?? '');
$filtro_modulo = trim($_GET['modulo'] ?? '');
$filtro_accion = trim($_GET['accion'] ?? '');
$filtro_fecha_desde = trim($_GET['fecha_desde'] ?? '');
$filtro_fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

// --- OBTENER REGISTROS DE AUDITORÍA ---
$where = [];
$params = [];

if ($filtro_usuario !== '') {
    $where[] = "(username LIKE ? OR user_id = ?)";
    $params[] = "%{$filtro_usuario}%";
    $params[] = $filtro_usuario;
}
if ($filtro_modulo !== '') {
    $where[] = "modulo = ?";
    $params[] = $filtro_modulo;
}
if ($filtro_accion !== '') {
    $where[] = "accion = ?";
    $params[] = $filtro_accion;
}
if ($filtro_fecha_desde !== '') {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $filtro_fecha_desde;
}
if ($filtro_fecha_hasta !== '') {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener registros (últimos 500 por defecto)
$sql = "SELECT * FROM audit_log {$whereClause} ORDER BY created_at DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Obtener valores únicos para filtros
$modulos = $pdo->query("SELECT DISTINCT modulo FROM audit_log WHERE modulo IS NOT NULL ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);
$acciones = $pdo->query("SELECT DISTINCT accion FROM audit_log ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h1><i class="fas fa-clipboard-list"></i> Registro de Auditoría</h1>
        <p>Historial completo de acciones realizadas por usuarios y administradores.</p>
    </div>
    <div style="background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 8px; border: 1px solid #ffeaa7;">
        <i class="fas fa-user-shield"></i> <strong>Acceso restringido:</strong> Solo desarrolladores
    </div>
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

<!-- FILTROS DE BÚSQUEDA -->
<div class="card" style="margin-bottom: 20px;">
    <div style="padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 15px;"><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <input type="hidden" name="route" value="auditoria">
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Usuario</label>
                <input type="text" name="usuario" class="input-field" placeholder="Username o ID" value="<?= htmlspecialchars($filtro_usuario) ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Módulo</label>
                <select name="modulo" class="input-field">
                    <option value="">-- Todos --</option>
                    <?php foreach($modulos as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $filtro_modulo === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Acción</label>
                <select name="accion" class="input-field">
                    <option value="">-- Todas --</option>
                    <?php foreach($acciones as $acc): ?>
                        <option value="<?= htmlspecialchars($acc) ?>" <?= $filtro_accion === $acc ? 'selected' : '' ?>>
                            <?= htmlspecialchars($acc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Fecha Desde</label>
                <input type="date" name="fecha_desde" class="input-field" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Fecha Hasta</label>
                <input type="date" name="fecha_hasta" class="input-field" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <a href="auditoria" class="btn-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ESTADÍSTICAS -->
<div class="card" style="margin-bottom: 20px;">
    <div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold; color: #155724;"><?= count($registros) ?></div>
            <div style="color: #155724; font-size: 0.9rem;">Registros encontrados</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #d1ecf1; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold; color: #0c5460;">
                <?= $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn() ?>
            </div>
            <div style="color: #0c5460; font-size: 0.9rem;">Total en sistema</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold; color: #856404;">
                <?= $pdo->query("SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE user_id IS NOT NULL")->fetchColumn() ?>
            </div>
            <div style="color: #856404; font-size: 0.9rem;">Usuarios con actividad</div>
        </div>
    </div>
</div>

<!-- TABLA DE REGISTROS -->
<div class="card">
    <div style="padding: 20px; overflow-x: auto;">
        <?php if (empty($registros)): ?>
            <div style="text-align: center; padding: 40px; opacity: 0.5;">
                <i class="fas fa-inbox fa-3x" style="margin-bottom: 15px;"></i>
                <p>No se encontraron registros de auditoría.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 160px;">Fecha/Hora</th>
                        <th style="width: 120px;">Usuario</th>
                        <th style="width: 80px;">Rol</th>
                        <th style="width: 100px;">Acción</th>
                        <th style="width: 120px;">Módulo</th>
                        <th>Descripción</th>
                        <th style="width: 100px;">IP</th>
                        <th style="width: 80px; text-align: center;">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($registros as $reg): 
                        $roleBadge = '';
                        switch($reg['user_role']) {
                            case 'developer': $roleBadge = 'background: #9b59b6;'; break;
                            case 'admin': $roleBadge = 'background: #3498db;'; break;
                            default: $roleBadge = 'background: #95a5a6;';
                        }
                        
                        $actionBadge = '';
                        switch($reg['accion']) {
                            case 'crear': $actionBadge = 'background: #27ae60;'; break;
                            case 'editar': $actionBadge = 'background: #f39c12;'; break;
                            case 'eliminar': $actionBadge = 'background: #e74c3c;'; break;
                            case 'login': $actionBadge = 'background: #3498db;'; break;
                            case 'logout': $actionBadge = 'background: #95a5a6;'; break;
                            default: $actionBadge = 'background: #34495e;';
                        }
                    ?>
                    <tr>
                        <td><?= (int)$reg['id'] ?></td>
                        <td><?= date('d/m/Y H:i:s', strtotime($reg['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($reg['username'] ?? 'N/A') ?></strong>
                            <?php if ($reg['user_id']): ?>
                                <br><small style="opacity: 0.7;">ID: <?= (int)$reg['user_id'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; <?= $roleBadge ?>">
                                <?= strtoupper($reg['user_role'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td>
                            <span style="color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; <?= $actionBadge ?>">
                                <?= strtoupper($reg['accion']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($reg['modulo'] ?? '-') ?></td>
                        <td><?= nl2br(htmlspecialchars($reg['descripcion'])) ?></td>
                        <td><?= htmlspecialchars($reg['ip_address'] ?? '-') ?></td>
                        <td style="text-align: center;">
                            <?php if ($reg['datos_anteriores'] || $reg['datos_nuevos']): ?>
                                <button onclick="verDetalles(<?= (int)$reg['id'] ?>, '<?= htmlspecialchars($reg['accion']) ?>')" 
                                        class="btn-primary" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                            <?php else: ?>
                                <span style="opacity: 0.3; font-size: 0.85rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE DETALLES -->
<div id="modal-detalles" class="modal">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 style="margin: 0;" id="modal-detalles-title">Detalles del Registro</h3>
            <span class="close-modal" onclick="closeModal('modal-detalles')">&times;</span>
        </div>
        <div class="modal-body" id="modal-detalles-content">
            <!-- Contenido dinámico -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('modal-detalles')">Cerrar</button>
        </div>
    </div>
</div>

<script>
function verDetalles(id, accion) {
    // Aquí podrías hacer una llamada AJAX para obtener los detalles completos
    // Por ahora, mostraremos un mensaje informativo
    const content = `
        <div style="padding: 20px;">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #2c3e50;">Registro #${id} - Acción: ${accion.toUpperCase()}</h4>
                <p style="margin-bottom: 0; color: #7f8c8d;">
                    <i class="fas fa-info-circle"></i> 
                    Los datos detallados (datos anteriores y nuevos) están almacenados en formato JSON en la base de datos.
                </p>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px;">
                <h5 style="margin-top: 0; color: #856404;">
                    <i class="fas fa-database"></i> Información técnica
                </h5>
                <p style="margin-bottom: 10px;">
                    Para ver los datos JSON completos (datos_anteriores y datos_nuevos), 
                    puedes consultar directamente la base de datos:
                </p>
                <code style="display: block; background: #f4f4f4; padding: 10px; border-radius: 4px; font-size: 0.85rem;">
                    SELECT datos_anteriores, datos_nuevos FROM audit_log WHERE id = ${id}
                </code>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px;">
                <h5 style="margin-top: 0; color: #0c5460;">
                    <i class="fas fa-lightbulb"></i> Tip
                </h5>
                <p style="margin-bottom: 0;">
                    Los campos <code>datos_anteriores</code> y <code>datos_nuevos</code> contienen 
                    información en formato JSON con el estado completo del registro antes y después 
                    de la modificación.
                </p>
            </div>
        </div>
    `;
    
    document.getElementById('modal-detalles-content').innerHTML = content;
    openModal('modal-detalles');
}
</script>

<style>
/* Estilos adicionales para el módulo de auditoría */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.input-field {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.3s;
}

.input-field:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.btn-primary,
.btn-secondary {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: top;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.data-table tbody tr {
    transition: background 0.2s;
}

/* Responsive */
@media (max-width: 768px) {
    .data-table {
        font-size: 0.85rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
}
</style>