<?php
/**
 * FRONT CONTROLLER - Punto de entrada único
 */
ob_start();
session_start();
require_once 'config/db.php';
require_once 'core/helpers.php';

// Determinar la ruta base para que los enlaces relativos funcionen correctamente
$base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

// 1. Enrutamiento simple (Movido al principio para evitar bucles de redirección)
$route = $_GET['route'] ?? 'dashboard';
$routeParts = explode('/', $route);
$module = $routeParts[0] ?: 'dashboard';
$action = $routeParts[1] ?? 'index';
$params = array_slice($routeParts, 2); // Captura IDs o extras

// --- GESTIÓN DE EMPRESA MULTIPLE ---
// 1. Obtener todas las empresas (transportistas) para el selector
$stmt_trans = $pdo->query("SELECT id, razon_social FROM transportistas ORDER BY razon_social ASC");
$todas_empresas = $stmt_trans->fetchAll();

// 2. Manejar cambio de empresa
if (isset($_POST['set_active_company'])) {
    $_SESSION['active_company_id'] = $_POST['set_active_company'];
    header("Location: " . $base_path . ($_GET['route'] ?? 'dashboard'));
    exit;
}

// Redirigir si no hay empresas creadas (excepto si ya estamos en transportistas)
if (empty($todas_empresas) && ($module !== 'transportistas' && $module !== 'dashboard')) {
    header("Location: " . $base_path . "transportistas");
    exit;
}

// 3. Definir empresa activa (por defecto la primera si no hay selección)
if (!isset($_SESSION['active_company_id']) && !empty($todas_empresas)) {
    $_SESSION['active_company_id'] = $todas_empresas[0]['id'];
}
$active_company_id = $_SESSION['active_company_id'] ?? null;

// 0. Obtener configuración de tema
try {
    $stmt = $pdo->query("SELECT valor FROM configuraciones WHERE clave = 'tema'");
    $currentTheme = $stmt->fetchColumn() ?: 'corporativo';
} catch (Exception $e) {
    $currentTheme = 'corporativo';
}

$themes = [
    'corporativo' => ['primary' => '#2c3e50', 'accent' => '#3498db', 'bg' => '#f4f7f6', 'card' => '#ffffff', 'text' => '#2c3e50'],
    'medio'       => ['primary' => '#27ae60', 'accent' => '#2ecc71', 'bg' => '#f0f3f4', 'card' => '#ffffff', 'text' => '#2c3e50'],
    'dark'        => ['primary' => '#1a1a1a', 'accent' => '#e74c3c', 'bg' => '#121212', 'card' => '#1e1e1e', 'text' => '#e0e0e0']
];
$theme = $themes[$currentTheme];

// 2. Títulos de página
$titles = [
    'dashboard' => 'Panel de Control',
    'choferes'  => 'Gestión de Choferes',
    'vehiculos' => 'Flota de Vehículos',
    'viajes'    => 'Operativa de Viajes',
    'clientes'  => 'Cartera de Clientes',
    'cobranzas' => 'Gestión de Cobranzas',
    'transportistas' => 'Gestión de Empresas',
    'mantenimiento' => 'Mantenimiento de Flota',
    'configuracion' => 'Configuración del Sistema',
    'tesoreria' => 'Tesorería y Conciliación'
];
$pageTitle = $titles[$module] ?? 'Sistema de Transporte';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <base href="<?= $base_path ?>">
    <title><?= $pageTitle ?> - Trans Cargo Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --sidebar-width: 280px; 
            --primary: <?= $theme['primary'] ?>; 
            --accent: <?= $theme['accent'] ?>; 
            --bg: <?= $theme['bg'] ?>;
            --card: <?= $theme['card'] ?>;
            --text: <?= $theme['text'] ?>;
        }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: var(--bg); color: var(--text); display: flex; transition: all 0.3s ease; }
        h1, h2, h3 { color: var(--text); }
        
        /* Sidebar Fijo */
        .sidebar { width: var(--sidebar-width); background: var(--primary); color: white; height: 100vh; position: fixed; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 20px; text-align: center; font-size: 1.5rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        /* Selector de Empresa */
        .company-selector { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .company-selector label { display: block; font-size: 0.7rem; color: #7f8c8d; text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
        .company-select { width: 100%; background: #34495e; color: white; border: 1px solid #5d6d7e; padding: 8px; border-radius: 4px; font-size: 0.9rem; cursor: pointer; }

        .nav-menu { padding: 10px 0; flex: 1; overflow-y: auto; }
        .nav-menu-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding: 10px 0; }
        .nav-item { padding: 12px 25px; display: block; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--accent); }
        .nav-item i { margin-right: 10px; width: 20px; transition: transform 0.2s; }
        .nav-item:hover i { transform: scale(1.15); }
        .sidebar-footer { width: 100%; padding: 15px; font-size: 0.75rem; color: #7f8c8d; border-top: 1px solid rgba(255,255,255,0.1); box-sizing: border-box; }

        /* Contenido Principal */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; padding: 30px; }
        .card { background: var(--card); padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); color: var(--text); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 8px; text-align: center; border-bottom: 4px solid var(--accent); color: var(--text); }
        .slogan { font-style: italic; color: #7f8c8d; margin-top: -20px; margin-bottom: 30px; display: block; }

        /* Tablas de Datos (Estilo Choferes Unificado) */
        .table-container { width: 100%; overflow-x: auto; margin-top: 10px; -webkit-overflow-scrolling: touch; border-radius: 8px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { text-align: left; padding: 12px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .data-table th { background: rgba(0,0,0,0.02); color: var(--text); font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; text-transform: uppercase; }
        .badge-success { background: #2ecc71 !important; color: white !important; }
        .badge-info { background: var(--accent) !important; color: white !important; }

        /* Elementos de Formulario Globales */
        .btn-primary { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text); opacity: 0.9; }
        .input-field { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.1); background: var(--card); color: var(--text); box-sizing: border-box; font-size: 1rem; }
        .input-field:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); }

        /* Modales Adaptables al Tema */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background-color: var(--card); margin: 2vh auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); overflow-y: auto; max-height: 95vh; animation: animatetop 0.3s; }
        @keyframes animatetop { from {top:-300px; opacity:0} to {top:0; opacity:1} }
        .modal-header { padding: 15px 20px; background: var(--primary); color: white; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .modal-body { padding: 25px; color: var(--text); }
        .modal-footer { padding: 15px 20px; text-align: right; background: rgba(0,0,0,0.02); border-top: 1px solid rgba(0,0,0,0.05); position: sticky; bottom: 0; }
        .close-modal { color: white; font-size: 24px; cursor: pointer; opacity: 0.8; }
        .close-modal:hover { opacity: 1; }
        
        /* Botones de acción del modal */
        .btn-secondary { background: #95a5a6; color: white; border: none; padding: 10px 18px; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .btn-danger { background: #e74c3c; color: white; border: none; padding: 10px 18px; border-radius: 5px; cursor: pointer; }

        /* Media Queries para Pantallas Pequeñas (Adaptación 1024x768) */
        @media (max-width: 1200px) {
            :root { --sidebar-width: 240px; }
            .main-content { padding: 20px; }
        }
        @media (max-width: 1024px) {
            .responsive-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand">Trans Cargo Hub</div>
        
        <div class="company-selector">
            <label>Empresa Activa</label>
            <form id="companyForm" method="POST" action="">
                <select name="set_active_company" class="company-select" onchange="this.form.submit()">
                    <?php foreach($todas_empresas as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $active_company_id == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <nav class="nav-menu">
            <a href="dashboard" class="nav-item <?= $module == 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-line" style="color: #3498db;"></i> Dashboard
            </a>
            <a href="viajes" class="nav-item <?= $module == 'viajes' ? 'active' : '' ?>">
                <i class="fas fa-truck-moving" style="color: #f39c12;"></i> Viajes
            </a>
            <a href="choferes" class="nav-item <?= $module == 'choferes' ? 'active' : '' ?>">
                <i class="fas fa-users" style="color: #2ecc71;"></i> Choferes
            </a>
            <a href="cobranzas" class="nav-item <?= $module == 'cobranzas' ? 'active' : '' ?>">
                <i class="fas fa-hand-holding-usd" style="color: #27ae60;"></i> Cobranzas
            </a>
            <a href="tesoreria" class="nav-item <?= $module == 'tesoreria' ? 'active' : '' ?>">
                <i class="fas fa-vault" style="color: #f1c40f;"></i> Tesorería
            </a>
            <a href="vehiculos" class="nav-item <?= $module == 'vehiculos' ? 'active' : '' ?>">
                <i class="fas fa-truck" style="color: #9b59b6;"></i> Vehículos
            </a>
            <a href="clientes" class="nav-item <?= $module == 'clientes' ? 'active' : '' ?>">
                <i class="fas fa-building" style="color: #00a8ff;"></i> Clientes
            </a>
            <a href="mantenimiento" class="nav-item <?= $module == 'mantenimiento' ? 'active' : '' ?>">
                <i class="fas fa-tools" style="color: #e67e22;"></i> Mantenimiento
            </a>
        </nav>

        <nav class="nav-menu-bottom">
            <a href="transportistas" class="nav-item <?= $module == 'transportistas' ? 'active' : '' ?>">
                <i class="fas fa-industry" style="color: #95a5a6;"></i> Empresas
            </a>
            <a href="configuracion" class="nav-item <?= $module == 'configuracion' ? 'active' : '' ?>">
                <i class="fas fa-cog" style="color: #bdc3c7;"></i> Configuración
            </a>
        </nav>
        <div class="sidebar-footer">
            Desarrollado por <strong>Sistemas Lucyk</strong>
        </div>
    </aside>

    <main class="main-content">
        <?php 
        // Mapeo de rutas a archivos para mayor seguridad
        $module_map = [
            'dashboard'      => 'modules/dashboard.php',
            'choferes'       => 'modules/choferes.php',
            'vehiculos'      => 'modules/vehiculos.php',
            'transportistas' => 'modules/transportistas.php',
            'clientes'       => 'modules/clientes.php',
            'viajes'         => 'modules/viajes.php',
            'cobranzas'      => 'modules/choferes_liquidar.php',
            'mantenimiento'  => 'modules/mantenimiento.php',
            'configuracion'  => 'modules/configuracion.php',
            'tesoreria'      => 'modules/tesoreria.php'
        ];

        $file_to_include = $module_map[$module] ?? null;

        if ($file_to_include && file_exists($file_to_include)) {
            include_once $file_to_include;
        } else {
            echo "<h1>404</h1><p>El módulo <strong>" . htmlspecialchars($module) . "</strong> no existe.</p>";
        }
        ?>
    </main>

    <!-- Modal de Confirmación Genérico -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="confirmTitle" style="margin:0; font-size:1.2rem;">Confirmar Acción</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">¿Estás seguro de realizar esta acción?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeConfirm(false)">Cancelar</button>
                <button class="btn-primary" id="confirmBtnOk" onclick="closeConfirm(true)">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        // Lógica simple para modales
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // Reemplazo de confirm()
        let confirmCallback = null;
        function appConfirm(message, callback, title = "Confirmar Acción") {
            document.getElementById('confirmMessage').innerText = message;
            document.getElementById('confirmTitle').innerText = title;
            confirmCallback = callback;
            openModal('confirmModal');
        }
        function closeConfirm(result) {
            closeModal('confirmModal');
            if(result && confirmCallback) confirmCallback();
        }
    </script>
</body>
</html>