<?php
/**
 * Funciones de ayuda globales para Trans Cargo Hub
 */

function formatMoney($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Obtiene un valor de configuración desde la tabla configuraciones.
 * @param PDO $pdo
 * @param string $clave
 * @param mixed $default Valor por defecto si no existe la clave
 * @return mixed
 */
function getConfig(PDO $pdo, string $clave, mixed $default = null): mixed {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuraciones WHERE clave = ?");
        $stmt->execute([$clave]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Obtiene los límites individuales de un administrador desde la tabla admin_limites.
 * Si no existe registro, lo crea con valores en 0 (sin límite).
 * 
 * @param PDO $pdo
 * @param int $adminId ID del usuario administrador
 * @return array ['limite_empresas' => int, 'limite_vehiculos' => int, 'limite_choferes' => int]
 */
function getAdminLimites(PDO $pdo, int $adminId): array {
    try {
        $stmt = $pdo->prepare("SELECT limite_empresas, limite_vehiculos, limite_choferes FROM admin_limites WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        $limites = $stmt->fetch();
        if ($limites) {
            return [
                'limite_empresas'  => (int)$limites['limite_empresas'],
                'limite_vehiculos' => (int)$limites['limite_vehiculos'],
                'limite_choferes'  => (int)$limites['limite_choferes'],
            ];
        }
    } catch (PDOException $e) {
        // Si la tabla no existe, retornar sin límite
    }
    return ['limite_empresas' => 0, 'limite_vehiculos' => 0, 'limite_choferes' => 0];
}

/**
 * Registra una acción en el log de auditoría.
 * Solo accesible para el rol 'developer'.
 * 
 * @param PDO $pdo
 * @param int|null $userId ID del usuario que realiza la acción
 * @param string $accion Tipo de acción (ej: 'crear', 'editar', 'eliminar', 'login', 'logout')
 * @param string $modulo Módulo donde se realiza la acción (ej: 'empresas', 'choferes', 'viajes')
 * @param string $descripcion Descripción detallada de la acción
 * @param array|null $datosAnteriores Datos antes del cambio (para updates)
 * @param array|null $datosNuevos Datos después del cambio (para updates)
 * @return bool
 */
function registrarAuditoria(PDO $pdo, ?int $userId, string $accion, string $modulo, string $descripcion, ?array $datosAnteriores = null, ?array $datosNuevos = null): bool {
    try {
        // Obtener información del usuario desde la sesión si no se proporciona
        if ($userId === null && session_status() === PHP_SESSION_ACTIVE) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        $username = $_SESSION['username'] ?? 'sistema';
        $userRole = $_SESSION['user_role'] ?? 'user';
        
        // Obtener IP y User Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Limitar longitud de user_agent
        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_log 
            (user_id, username, user_role, accion, modulo, descripcion, datos_anteriores, datos_nuevos, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $datosAnterioresJson = $datosAnteriores !== null ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
        $datosNuevosJson = $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;
        
        return $stmt->execute([
            $userId,
            $username,
            $userRole,
            $accion,
            $modulo,
            $descripcion,
            $datosAnterioresJson,
            $datosNuevosJson,
            $ipAddress,
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log("[AUDIT ERROR] " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si un administrador ha alcanzado el límite de creación de una entidad.
 * Los límites son individuales por admin (según plan contratado).
 * 
 * @param PDO $pdo
 * @param string $tipo 'empresas', 'vehiculos' o 'choferes'
 * @param int $adminId ID del admin
 * @param int|null $tenantId ID del tenant activo (requerido para vehiculos/choferes)
 * @return array ['permitido' => bool, 'actual' => int, 'limite' => int, 'mensaje' => string]
 */
function verificarLimite(PDO $pdo, string $tipo, int $adminId, ?int $tenantId = null): array {
    $limites = getAdminLimites($pdo, $adminId);
    
    $limite = 0;
    switch ($tipo) {
        case 'empresas':  $limite = $limites['limite_empresas']; break;
        case 'vehiculos': $limite = $limites['limite_vehiculos']; break;
        case 'choferes':  $limite = $limites['limite_choferes']; break;
        default: return ['permitido' => false, 'actual' => 0, 'limite' => 0, 'mensaje' => 'Tipo de límite inválido.'];
    }
    
    // 0 = sin límite
    if ($limite <= 0) {
        return ['permitido' => true, 'actual' => 0, 'limite' => 0, 'mensaje' => ''];
    }

    $actual = 0;
    $label = '';

    switch ($tipo) {
        case 'empresas':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transportistas WHERE created_by = ? AND activo = 1");
            $stmt->execute([$adminId]);
            $actual = (int)$stmt->fetchColumn();
            $label = 'empresas';
            break;
        case 'vehiculos':
            if (!$tenantId) return ['permitido' => false, 'actual' => 0, 'limite' => $limite, 'mensaje' => 'Error: tenant no especificado.'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehiculos WHERE transportista_id = ? AND activo = 1");
            $stmt->execute([$tenantId]);
            $actual = (int)$stmt->fetchColumn();
            $label = 'vehículos';
            break;
        case 'choferes':
            if (!$tenantId) return ['permitido' => false, 'actual' => 0, 'limite' => $limite, 'mensaje' => 'Error: tenant no especificado.'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM choferes WHERE transportista_id = ? AND activo = 1");
            $stmt->execute([$tenantId]);
            $actual = (int)$stmt->fetchColumn();
            $label = 'choferes';
            break;
    }

    if ($actual >= $limite) {
        return [
            'permitido' => false,
            'actual' => $actual,
            'limite' => $limite,
            'mensaje' => "Has alcanzado el límite máximo de {$label} permitido ({$limite}). No puedes crear más."
        ];
    }

    return ['permitido' => true, 'actual' => $actual, 'limite' => $limite, 'mensaje' => ''];
}