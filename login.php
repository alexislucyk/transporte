<?php
// Sincronizar duración de sesión con el frontend (30 minutos = 1800 segundos)
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);

session_start();
require_once 'core/env.php';
require_once 'config/db.php';
require_once 'core/helpers.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $userData = $stmt->fetch();

    if ($userData && password_verify($pass, $userData['password'])) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['user_name'] = $userData['full_name'];
        $_SESSION['user_role'] = $userData['role'];
        
        // Determinar admin_root_id para multi-tenant
        // - Developer: su propio ID es el root
        // - Admin: su propio ID es el root (cada admin maneja sus propias empresas)
        // - User: buscar el created_by en la tabla users para encontrar su admin root
        if ($userData['role'] === 'developer') {
            $_SESSION['admin_root_id'] = $userData['id'];
        } elseif ($userData['role'] === 'admin') {
            $_SESSION['admin_root_id'] = $userData['id'];
        } else {
            // Usuario normal: buscar quién lo creó (su admin)
            $adminRootId = (int)($userData['created_by'] ?: 0);
            if (!$adminRootId) {
                $adminRootId = $userData['id'];
            }
            $_SESSION['admin_root_id'] = $adminRootId;
        }
        
        // Cargar permisos específicos
        $stmtPerms = $pdo->prepare("SELECT module FROM user_permissions WHERE user_id = ?");
        $stmtPerms->execute(array($userData['id']));
        $_SESSION['user_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
        
        // Registrar auditoría de login exitoso
        registrarAuditoria($pdo, $userData['id'], 'login', 'auth', 
            'Inicio de sesión exitoso',
            null,
            ['username' => $userData['username'], 'role' => $userData['role']]
        );
        
        // Permitir el acceso siempre que las credenciales de usuario sean correctas.
        header("Location: dashboard");
        exit;
    } else {
        // Registrar auditoría de login fallido
        registrarAuditoria($pdo, null, 'login_fallido', 'auth', 
            "Intento de inicio de sesión fallido para usuario: {$user}",
            null,
            ['username_intentado' => $user, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $error = "Credenciales incorrectas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso - Trans Cargo Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="login-card">
        <img src="assets/logo01.png" alt="Logo" class="login-logo">

        <!-- <h1>Trans Cargo Hub</h1> -->
        <p>Inicia sesión para gestionar tu flota</p>


        <?php if($error): ?>
            <div class="error-msg"><i class="fas fa-times-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="username" class="input-field" required autofocus>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" class="input-field" required>
            </div>
            <button type="submit" class="btn-login">Ingresar al Sistema</button>
        </form>
        <div style="margin-top: 20px; font-size: 0.6rem; color: #bdc3c7;">
            Desarrollado por <strong>Sistemas Lucyk</strong>
        </div>
    </div>
</body>
</html>

