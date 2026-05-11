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

if (isset($_GET['success'])) $mensaje = "Configuración actualizada correctamente.";
?>
<h1>Configuración del Sistema</h1>
<p>Personaliza la apariencia y el comportamiento de Trans Cargo Hub.</p>

<style>
    .theme-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .theme-card { border: 2px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; transition: 0.3s; position: relative; }
    .theme-radio:checked + .theme-card { border-color: var(--accent); box-shadow: 0 0 10px rgba(0,0,0,0.1); background-color: rgba(0,0,0,0.02); }
    .theme-preview { display: flex; height: 40px; border-radius: 4px; overflow: hidden; margin-bottom: 10px; border: 1px solid #eee; }
    .theme-active-badge { color: var(--accent); font-size: 0.8rem; display: block; margin-top: 5px; }
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