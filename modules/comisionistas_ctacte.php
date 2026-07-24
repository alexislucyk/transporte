<?php
/**
 * Modulo de Cuenta Corriente de Comisionistas - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Muestra el historial de pagos a comisionistas y permite registrar nuevos.
 * Estilo visual: viajes_detalle (tarjetas con gradiente, columnas de color, avatares)
 */

function comisionistaOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND transportista_id = ? AND es_comisionista = 1 AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

// Capturar mensaje por redirect
if (isset($_GET['msg'])) {
    $mensaje = "Pago registrado exitosamente.";
}

$cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

if ($cliente_id <= 0) {
    $stmt = $pdo->prepare("SELECT id, razon_social, cuit, telefono FROM clientes WHERE transportista_id = ? AND activo = 1 AND es_comisionista = 1 ORDER BY razon_social ASC");
    $stmt->execute([$active_company_id]);
    $comisionistas = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, razon_social, cuit, telefono FROM clientes WHERE id = ? AND transportista_id = ? AND es_comisionista = 1 AND activo = 1");
    $stmt->execute([$cliente_id, $active_company_id]);
    $comisionista = $stmt->fetch();

    if (!$comisionista) {
        $error = "Comisionista no encontrado o no pertenece a la empresa activa.";
        $comisionista = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM comisionista_pagos WHERE cliente_id = ? ORDER BY fecha ASC, id ASC");
        $stmt->execute([$cliente_id]);
        $pagos = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM comisionista_pagos WHERE cliente_id = ?");
        $stmt->execute([$cliente_id]);
        $total = $stmt->fetchColumn();

        // Calcular totales por tipo
        $total_comision = 0;
        $total_pago = 0;
        $total_haber = 0;
        $total_debe = 0;
        foreach ($pagos as $p) {
            switch ($p['tipo']) {
                case 'comision': 
                    $total_comision += (float)$p['monto']; 
                    $total_haber += (float)$p['monto'];
                    break;
                case 'pago':    
                    $total_pago += (float)$p['monto']; 
                    $total_debe += (float)$p['monto'];
                    break;
                default:
                    $total_debe += (float)$p['monto'];
                    break;
            }
        }
        $saldo_final = $total_haber - $total_debe;
    }
}

// ─── PROCESAR POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo_pago' && $cliente_id > 0) {
    $currentRole = $_SESSION['user_role'] ?? 'user';

    if ($currentRole === 'developer' || comisionistaOwner($pdo, $cliente_id, $active_company_id, $currentRole)) {
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $monto = (float)($_POST['monto'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'pago';
        $detalle = trim($_POST['detalle'] ?? '');

        if ($monto <= 0) {
            $error = "El monto debe ser mayor a cero.";
        } elseif (!in_array($tipo, ['comision', 'pago'])) {
            $error = "Tipo de pago inválido.";
        } else {
            try {
                $sql = "INSERT INTO comisionista_pagos (cliente_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cliente_id, $fecha, $monto, $tipo, $detalle ?: null]);
                header("Location: comisionistas_ctacte?cliente_id=" . $cliente_id . "&msg=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar pago: " . $e->getMessage();
            }
        }
    } else {
        $error = "No autorizado.";
    }
}
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <?php if ($cliente_id > 0 && $comisionista): ?>
        <a href="comisionistas_ctacte" style="text-decoration:none; color:var(--accent); margin-bottom:8px; display:inline-block;">
            <i class="fas fa-arrow-left"></i> Volver a comisionistas
        </a>
        <h1 style="margin:4px 0 0 0;">Cuenta Corriente</h1>
        <?php else: ?>
        <h1 style="margin:0;">Cuenta Corriente - Comisionistas</h1>
        <p>Registro de pagos y comisiones a comisionistas de la empresa activa.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($cliente_id <= 0): ?>
<!-- ══════════════════════════════════════════════════════════
     MODO SELECCIÓN: Lista de comisionistas estilo grid
     ══════════════════════════════════════════════════════════ -->
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:14px;">
    <?php if (empty($comisionistas)): ?>
    <div class="card" style="grid-column:1/-1; text-align:center; padding:40px;">
        <p style="opacity:0.5;">No hay comisionistas registrados en la empresa activa.</p>
    </div>
    <?php else: ?>
        <?php foreach($comisionistas as $c): ?>
        <a href="comisionistas_ctacte?cliente_id=<?= (int)$c['id'] ?>" style="text-decoration:none; color:inherit;">
            <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e0e0e0; transition:all 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.06);"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';this.style.borderColor='var(--accent)';"
                 onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.06)';this.style.borderColor='#e0e0e0';">
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="background:linear-gradient(135deg, #9b59b6, #8e44ad); width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:#fff; flex-shrink:0; box-shadow:0 2px 6px rgba(155,89,182,0.3);">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:bold; font-size:1rem; color:#333; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($c['razon_social']) ?>
                        </div>
                        <?php if (!empty($c['cuit'])): ?>
                        <div style="font-size:0.8rem; color:#888; margin-top:2px;">
                            <i class="fas fa-id-card" style="opacity:0.5;"></i> <?= htmlspecialchars($c['cuit']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($c['telefono'])): ?>
                        <div style="font-size:0.8rem; color:#888; margin-top:2px;">
                            <i class="fas fa-phone" style="opacity:0.5;"></i> <?= htmlspecialchars($c['telefono']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="color:var(--accent); font-size:1.2rem;">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php elseif ($comisionista): ?>
<!-- ══════════════════════════════════════════════════════════
     MODO DETALLE: Cuenta corriente del comisionista
     ══════════════════════════════════════════════════════════ -->

<!-- ─── TARJETA PRINCIPAL: Info del Comisionista ────────────── -->
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #9b59b6, #8e44ad, #3498db, #2ecc71); position:absolute; top:0; left:0; right:0;"></div>

    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div style="background:linear-gradient(135deg, #9b59b6, #8e44ad); width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:#fff; flex-shrink:0; box-shadow:0 3px 8px rgba(155,89,182,0.3);">
            <i class="fas fa-user-tie"></i>
        </div>
        <div style="flex:1;">
            <h2 style="margin:0; font-size:1.4rem;"><?= htmlspecialchars($comisionista['razon_social']) ?></h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:4px;">
                <?php if (!empty($comisionista['cuit'])): ?>
                <span style="background:#f3e5f5; color:#9b59b6; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:bold; border:1px solid #ce93d8;">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($comisionista['cuit']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($comisionista['telefono'])): ?>
                <span style="background:#e3f2fd; color:#3498db; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:bold; border:1px solid #90caf9;">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($comisionista['telefono']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.75rem; opacity:0.6;">Total Movimientos</div>
            <div style="font-size:1.5rem; font-weight:bold; color:var(--accent);">
                $ <?= number_format($total, 2, ',', '.') ?>
            </div>
        </div>
    </div>
</div>

<!-- ─── TARJETA: RESUMEN POR TIPO ────────────────────── -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:14px; margin-bottom:20px;">
    <div style="background:linear-gradient(135deg, #e8f5e9, #fff); border-radius:12px; padding:16px; border:1px solid #a5d6a7; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#2e7d32; font-weight:bold;">
            <i class="fas fa-check-circle"></i> Comisiones
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#1b5e20; margin-top:4px;">
            $ <?= number_format($total_comision, 2, ',', '.') ?>
        </div>
    </div>
    <div style="background:linear-gradient(135deg, #fff8e1, #fff); border-radius:12px; padding:16px; border:1px solid #ffe082; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#f57f17; font-weight:bold;">
            <i class="fas fa-hand-holding-usd"></i> Pagos
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#e65100; margin-top:4px;">
            $ <?= number_format($total_pago, 2, ',', '.') ?>
        </div>
    </div>
    <div style="background:linear-gradient(135deg, <?= $saldo_final >= 0 ? '#1b5e20' : '#b71c1c' ?>, <?= $saldo_final >= 0 ? '#2e7d32' : '#c62828' ?>); border-radius:12px; padding:16px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.2);">
        <div style="font-size:0.65rem; color:rgba(255,255,255,0.7); text-transform:uppercase; letter-spacing:1px;">
            <i class="fas fa-calculator"></i> Saldo Actual
        </div>
        <div style="font-size:1.4rem; font-weight:bold; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.1); margin-top:2px;">
            $ <?= number_format($saldo_final, 2, ',', '.') ?>
        </div>
    </div>
</div>

<!-- ─── TARJETA: NUEVO PAGO ──────────────────────────── -->
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:4px; background:linear-gradient(90deg, var(--accent), #2ecc71); position:absolute; top:0; left:0; right:0;"></div>
    <h3 style="margin:0 0 14px 0;">
        <span style="background:linear-gradient(135deg, var(--primary), #34495e); color:#fff; padding:5px 12px; border-radius:8px; font-size:0.9rem;">
            <i class="fas fa-plus-circle"></i> Registrar Nuevo Movimiento
        </span>
    </h3>
    <form method="POST">
        <input type="hidden" name="action" value="nuevo_pago">
        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                <label><i class="far fa-calendar-alt" style="color:var(--accent);"></i> Fecha</label>
                <input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                <label><i class="fas fa-dollar-sign" style="color:var(--accent);"></i> Monto</label>
                <input type="number" step="0.01" name="monto" class="input-field" placeholder="0.00" required>
            </div>
            <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                <label><i class="fas fa-tag" style="color:var(--accent);"></i> Tipo</label>
                <select name="tipo" class="input-field">
                    <option value="pago">💰 Pago</option>
                    <option value="comision">✅ Comisión</option>
                </select>
            </div>
            <div class="form-group" style="flex: 2; min-width: 180px; margin: 0;">
                <label><i class="fas fa-pen" style="color:var(--accent);"></i> Detalle</label>
                <input type="text" name="detalle" class="input-field" placeholder="Concepto del movimiento">
            </div>
            <button type="submit" class="btn-primary" style="height: 38px; white-space:nowrap;">
                <i class="fas fa-save"></i> Agregar
            </button>
        </div>
    </form>
</div>

<!-- ─── TABLA: HISTORIAL DE PAGOS ────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <h3 style="margin:0;"><i class="fas fa-history"></i> Historial de Movimientos</h3>
        <span style="background:#f0f0f0; padding:4px 12px; border-radius:20px; font-size:0.8rem; opacity:0.7;">
            <?= count($pagos) ?> registro(s)
        </span>
    </div>

    <?php if (empty($pagos)): ?>
        <p style="text-align:center; padding:30px; opacity:0.5;">No hay movimientos registrados para este comisionista.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Movimiento</th>
                    <th style="text-align:right;">Debe</th>
                    <th style="text-align:right;">Haber</th>
                    <th style="text-align:right;">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $saldo_acumulado = 0;
                foreach($pagos as $p): 
                    $tipo_badge = match($p['tipo']) {
                        'comision' => '<span class="badge" style="background:#27ae60; color:#fff;">✅ Comisión</span>',
                        'pago' => '<span class="badge" style="background:#f39c12; color:#fff;">💰 Pago</span>',
                        default => '<span class="badge">' . htmlspecialchars($p['tipo']) . '</span>'
                    };

                    $es_haber = ($p['tipo'] === 'comision');
                    $monto = (float)$p['monto'];
                    $debe = $es_haber ? 0 : $monto;
                    $haber = $es_haber ? $monto : 0;
                    $saldo_acumulado += $haber - $debe;
                ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($p['fecha'])) ?></td>
                    <td>
                        <?= $tipo_badge ?>
                        <?php if (!empty($p['detalle'])): ?>
                        <br><small style="opacity:0.6;"><?= htmlspecialchars($p['detalle']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; font-weight:bold; <?= $debe > 0 ? 'color:#e74c3c;' : 'color:#999;' ?>">
                        <?= $debe > 0 ? '$ ' . number_format($debe, 2, ',', '.') : '-' ?>
                    </td>
                    <td style="text-align:right; font-weight:bold; <?= $haber > 0 ? 'color:#27ae60;' : 'color:#999;' ?>">
                        <?= $haber > 0 ? '$ ' . number_format($haber, 2, ',', '.') : '-' ?>
                    </td>
                    <td style="text-align:right; font-weight:bold; <?= $saldo_acumulado >= 0 ? 'color:#27ae60;' : 'color:#e74c3c;' ?>">
                        $ <?= number_format($saldo_acumulado, 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="2" style="text-align:right;">Totales:</td>
                    <td style="text-align:right; color:#e74c3c;">$ <?= number_format($total_debe, 2, ',', '.') ?></td>
                    <td style="text-align:right; color:#27ae60;">$ <?= number_format($total_haber, 2, ',', '.') ?></td>
                    <td style="text-align:right; <?= $saldo_final >= 0 ? 'color:#27ae60;' : 'color:#e74c3c;' ?>">
                        $ <?= number_format($saldo_final, 2, ',', '.') ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
