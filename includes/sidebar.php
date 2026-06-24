<?php
/**
 * Sidebar (extraída de index.php)
 * Requiere variables:
 * - $todas_empresas, $active_company_id
 * - $module, $user_role, $user_permissions
 */
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div style="display: flex; align-items: center;">
            <span>Trans Cargo Hub</span>
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    </div>

    <div class="company-selector">
        <label>Empresa Activa</label>
        <form id="companyForm" method="POST" action="">
            <select name="set_active_company" class="company-select" onchange="this.form.submit()">
                <?php foreach($todas_empresas as $emp): ?>
                    <?php
                        $isActivo = isset($emp['activo']) ? (int)$emp['activo'] : 1;
                        $style = ($user_role === 'developer' && $isActivo === 0) ? 'color:#e74c3c; font-weight:600;' : '';
                    ?>
                    <option value="<?= $emp['id'] ?>" <?= $active_company_id == $emp['id'] ? 'selected' : '' ?> <?= $style ? 'style="'.$style.'"' : '' ?> >
                        <?= htmlspecialchars($emp['razon_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <nav class="nav-menu">
        <?php
        // Función para renderizar item del menú si tiene permiso
        function navItem($mod, $icon, $label, $current, $role, $perms) {
            if (in_array($role, ['admin', 'developer']) || in_array($mod, $perms)) {
                $active = ($current == $mod) ? 'active' : '';
                echo "<a href=\"$mod\" class=\"nav-item $active\"><i class=\"fas $icon\"></i> <span>$label</span></a>";
            }
        }

        navItem('dashboard', 'fa-chart-line', 'Dashboard', $module, $user_role, $user_permissions);
        navItem('viajes', 'fa-truck-moving', 'Viajes', $module, $user_role, $user_permissions);
        navItem('choferes', 'fa-users', 'Choferes', $module, $user_role, $user_permissions);
        navItem('cobranzas', 'fa-hand-holding-usd', 'Cobranzas', $module, $user_role, $user_permissions);
        navItem('comisionistas', 'fa-percentage', 'Comisionistas', $module, $user_role, $user_permissions);
        navItem('vehiculos', 'fa-truck', 'Vehículos', $module, $user_role, $user_permissions);
        navItem('clientes', 'fa-building', 'Clientes', $module, $user_role, $user_permissions);
        navItem('mantenimiento', 'fa-tools', 'Mantenimiento', $module, $user_role, $user_permissions);
        navItem('tesoreria', 'fa-university', 'Tesorería', $module, $user_role, $user_permissions);
        ?>
    </nav>

    <nav class="nav-menu-bottom">
        <?php
        navItem('empresas', 'fa-industry', 'Empresas', $module, $user_role, $user_permissions);
        navItem('configuracion', 'fa-cog', 'Configuración', $module, $user_role, $user_permissions);
        ?>
        <a href="logout.php" class="nav-item" style="color: #e74c3c;">
            <i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        Usuario: <strong><?= explode(' ', $_SESSION['user_name'])[0] ?></strong><br>
        Desarrollado por Sistemas Lucyk
    </div>
</aside>

