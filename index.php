<?php
/**
 * FRONT CONTROLLER - Punto de entrada único
 */
ob_start();

// Sincronizar duración de sesión con el frontend (30 minutos = 1800 segundos)
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);

session_start();
require_once 'core/env.php';
require_once 'config/db.php';
require_once 'core/helpers.php';


// --- PROTECCIÓN DE ACCESO ---
if (!isset($_SESSION['user_id']) && !strpos($_SERVER['PHP_SELF'], 'login.php')) {
    header("Location: login.php");
    exit;
}
$user_role = $_SESSION['user_role'] ?? 'user';
$user_permissions = $_SESSION['user_permissions'] ?? [];

// Determinar la ruta base para que los enlaces relativos funcionen correctamente
$base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

// 1. Enrutamiento inteligente: Detecta la ruta tanto por parámetro GET como por la URL real
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$relative_path = str_replace($base_path, '', $request_uri);
$route = $_GET['route'] ?? $relative_path;
$route = trim((string)$route, '/ ');
if (empty($route) || $route === 'index.php') $route = 'dashboard';

// --- MANEJO DE CIERRE DE SESIÓN ---
if ($route === 'logout') {
    $_SESSION = []; // Limpia las variables de sesión
    session_destroy(); // Destruye la sesión
    header("Location: " . $base_path . "login.php");
    exit;
}

$routeParts = explode('/', $route);
$module = $routeParts[0] ?: 'dashboard';
$action = $routeParts[1] ?? 'index';
$params = array_slice($routeParts, 2); // Captura IDs o extras

// --- GESTIÓN DE EMPRESA MULTIPLE ---
// 1. Obtener todas las empresas (transportistas) para el selector
// Multi-admin: un usuario NO-developer debe ver SOLO las empresas creadas por su admin creador.
// Regla aplicada:
// - transportistas.created_by = id del admin que creó esa empresa.
// - Si el usuario logueado es 'admin': puede ver solo las empresas con created_by = su id.
// - Si el usuario logueado es 'user': se asume que su created_by (en users) indica el admin dueño.
//   Para esto usamos $_SESSION['admin_root_id'] si existe, si no, fallback a $_SESSION['user_id'].
if ($user_role === 'developer') {
    // El desarrollador puede ver también las empresas inactivas (borrado lógico)
    $stmt_trans = $pdo->query("SELECT id, razon_social, activo FROM transportistas ORDER BY razon_social ASC");
} else {
    $adminRootId = $_SESSION['admin_root_id'] ?? null;

    // Fallback: si no existe admin_root_id, para usuarios NO-developer asumimos
    // que sus empresas están creadas por el admin que figura en users.created_by.
    if (!$adminRootId) {
        $stmtAdmin = $pdo->prepare("SELECT created_by FROM users WHERE id = ? AND role <> 'developer' LIMIT 1");
        $stmtAdmin->execute([$_SESSION['user_id']]);
        $adminRootId = (int)($stmtAdmin->fetchColumn() ?: 0);
    }

    if (!$adminRootId) {
        // Último fallback (comportamiento anterior): usar el user_id como created_by.
        $adminRootId = $_SESSION['user_id'];
    }

    $stmt_trans = $pdo->prepare("SELECT id, razon_social FROM transportistas WHERE created_by = ? ORDER BY razon_social ASC");
    $stmt_trans->execute([$adminRootId]);
}
    $todas_empresas = $stmt_trans->fetchAll();
    // Para developer: marcamos estilo en selector (activos normales, inactivos en rojo)


// 2. Manejar cambio de empresa

// Alias: si la empresa activa se usaba antes con transportistas, mantenemos compatibilidad
// (no afecta lógica actual, pero evita futuros cambios de referencia)

if (isset($_POST['set_active_company'])) {
    $requested_id = $_POST['set_active_company'];
    // Seguridad: Validar que la empresa seleccionada esté en la lista de permitidas para este usuario
    $is_allowed = false;
    foreach ($todas_empresas as $emp) {
        if ($emp['id'] == $requested_id) { $is_allowed = true; break; }
    }

    if ($is_allowed) {
        $_SESSION['active_company_id'] = $requested_id;
    }
    header("Location: " . $base_path . ($route ?: 'dashboard'));
    exit;
}

// Redirigir si no hay empresas creadas (excepto si ya estamos en empresas o dashboard/config)
if (empty($todas_empresas) && !in_array($module, ['empresas', 'dashboard', 'configuracion'])) {
    header("Location: " . $base_path . "empresas");
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
    'comisionistas' => 'Gestión de Comisionistas',
    'cobranzas' => 'Gestión de Cobranzas',
    'empresas' => 'Gestión de Empresas',
    'mantenimiento' => 'Mantenimiento de Flota',
    'configuracion' => 'Configuración del Sistema',
    'tesoreria' => 'Tesorería y Conciliación'
];
$pageTitle = $titles[$module] ?? 'Sistema de Transporte';

// --- CONTROL DE PERMISOS POR ROL ---
$access_denied = false;
// El administrador tiene acceso total. Los usuarios normales solo a lo permitido en su lista.
if (!in_array($user_role, ['admin', 'developer']) && $module !== 'dashboard') {
    if (!in_array($module, $user_permissions)) {
        $access_denied = true;
        $pageTitle = "Acceso Restringido";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
<base href="<?= $base_path ?>">
    <title><?= $pageTitle ?> - Trans Cargo Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base_path ?>css/main.css">
<link rel="stylesheet" href="<?= $base_path ?>css/font-scale-viajes.css">
    <style>
        :root {
            --sidebar-width: 280px;
            --primary: <?= $theme['primary'] ?>;
            --accent: <?= $theme['accent'] ?>;
            --bg: <?= $theme['bg'] ?>;
            --card: <?= $theme['card'] ?>;
            --text: <?= $theme['text'] ?>;
        }
        body.collapsed { --sidebar-width: 70px; }
    </style>
</head>
<body>
    <script>
        // Aplicar estado del sidebar antes de renderizar para evitar parpadeo
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.body.classList.add('collapsed');
        }
    </script>
<?php include_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php
        if ($access_denied) : ?>
            <div class="card" style="text-align:center; padding: 60px 20px; border-top: 5px solid #e74c3c; max-width: 600px; margin: 40px auto;">
                <i class="fas fa-user-shield fa-5x" style="color:#e74c3c; margin-bottom:25px;"></i>
                <h2 style="font-size: 2rem; margin-bottom: 10px;">Acceso Restringido</h2>
                <p style="font-size: 1.1rem; opacity: 0.8; margin-bottom: 30px;">
                    Lo sentimos, tu perfil no cuenta con los permisos necesarios para acceder a <br>
                    <strong><?= $titles[$module] ?? $module ?></strong>.
                </p>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <a href="dashboard" class="btn-primary" style="background:#34495e;">
                        <i class="fas fa-arrow-left"></i> Volver al Inicio
                    </a>
                </div>
            </div>
        <?php else:
        switch($module) {
            case 'dashboard':
                include_once 'modules/dashboard.php';
                break;
            case 'choferes':
                include_once 'modules/choferes.php';
                break;
            case 'choferes_ctacte':
                include_once 'modules/choferes_ctacte.php';
                break;
            case 'choferes_liquidar':
                include_once 'modules/choferes_liquidar.php';
                break;
            case 'vehiculos':
                include_once 'modules/vehiculos.php';
                break;
            case 'empresas':
                include_once 'modules/empresas.php';
                break;
            case 'clientes':
                include_once 'modules/clientes.php';
                break;
            case 'comisionistas':
                include_once 'modules/comisionistas.php';
                break;
            case 'comisionistas_ctacte':
                include_once 'modules/comisionistas_ctacte.php';
                break;
            case 'viajes':
                include_once 'modules/viajes.php';
                break;
            case 'viajes_detalle':
                include_once 'modules/viajes_detalle.php';
                break;
            case 'cobranzas':
                include_once 'modules/cobranzas.php';
                break;
            case 'cobranzas_fletes_pendientes':
                include_once 'modules/cobranzas_fletes_pendientes.php';
                break;
            case 'cobranzas_fletes_liquidar':
                include_once 'modules/cobranzas_fletes_liquidar.php';
                break;
            case 'cobranzas_fletes_factura':
                include_once 'modules/cobranzas_fletes_factura.php';
                break;
            case 'mantenimiento':
                include_once 'modules/mantenimiento.php';
                break;
            case 'configuracion':
                include_once 'modules/configuracion.php';
                break;
            case 'tesoreria':
                include_once 'modules/tesoreria.php';
                break;
            default:
                echo "<h1>404</h1><p>Módulo no encontrado.</p>";
                break;
        }
        endif;
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
            // Frenamos el submit automático para que el submit se dispare SOLO desde el callback.
            return false;
        }
        function closeConfirm(result) {
            if(!result) {
                closeModal('confirmModal');
                return;
            }

            // Ejecutar callback primero
            if(confirmCallback) confirmCallback();

            // Cerrar inmediatamente tras confirmar.
            closeModal('confirmModal');

            // Permitir submit manual si hace falta: cuando se usa appConfirm en onsubmit, al devolver false
            // el form NO envía. Entonces no podemos reanudar automáticamente.
            // En este app, los callbacks no hacen submit, así que la UI debe usar otro flujo.
            // Por eso: solo usamos esta función para no romper, y dejamos el callback para navegación si aplica.
        }

        function toggleSidebar() {
            const body = document.body;
            body.classList.toggle('collapsed');
            localStorage.setItem('sidebar-collapsed', body.classList.contains('collapsed'));
        }

        function confirmLogout() {
            appConfirm("¿Estás seguro de que deseas salir del sistema?", function() {
                window.location.href = 'logout';
            }, "Cerrar Sesión");
        }

        // --- CONTROL DE INACTIVIDAD ---
        let idleTimeout;
        function resetIdleTimer() {
            clearTimeout(idleTimeout);
            // 30 minutos = 1.800.000 milisegundos
            idleTimeout = setTimeout(() => {
                window.location.href = 'logout';
            }, 1800000);
        }

        // Reiniciar el temporizador con cualquier interacción del usuario
        ['mousemove', 'mousedown', 'keypress', 'touchstart', 'scroll'].forEach(evt => {
            document.addEventListener(evt, resetIdleTimer, true);
        });

        // Iniciar el temporizador al cargar la página
        resetIdleTimer();
    </script>
</body>
</html>

