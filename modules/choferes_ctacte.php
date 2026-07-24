<?php
/**
 * Modulo de Cuenta Corriente de Choferes - Trans Cargo Hub
 * Multi-tenant 100% aislado por transportista_id.
 * Muestra el historial de pagos a choferes y permite registrar nuevos.
 * Estilo visual: viajes_detalle (tarjetas con gradiente, columnas de color, avatares)
 */

function choferOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM choferes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;

// Capturar mensaje por redirect
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case '1':
            $mensaje = "Pago registrado exitosamente.";
            break;
        case '2':
            $mensaje = "Gasto registrado exitosamente.";
            break;
    }
}

$chofer_id = isset($_GET['chofer_id']) ? (int)$_GET['chofer_id'] : 0;

if ($chofer_id <= 0) {
    $stmt = $pdo->prepare("SELECT id, CONCAT(apellido, ', ', nombre) as nombre, porcentaje_ganancia, telefono FROM choferes WHERE transportista_id = ? AND activo = 1 ORDER BY apellido, nombre ASC");
    $stmt->execute([$active_company_id]);
    $choferes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, CONCAT(apellido, ', ', nombre) as nombre, porcentaje_ganancia, telefono FROM choferes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$chofer_id, $active_company_id]);
    $chofer = $stmt->fetch();

    if (!$chofer) {
        $error = "Chofer no encontrado o no pertenece a la empresa activa.";
        $chofer = null;
    } else {
        // Obtener pagos desde chofer_pagos
        $stmt = $pdo->prepare("SELECT * FROM chofer_pagos WHERE chofer_id = ? ORDER BY fecha ASC, id ASC");
        $stmt->execute([$chofer_id]);
        $pagos = $stmt->fetchAll();

        // Obtener viajes del chofer para asociar datos
        $stmt = $pdo->prepare("SELECT id, origen, destino, producto, ctg_nro, fecha_carga, total_flete_bruto, total_flete_neto FROM viajes WHERE chofer_id = ? AND activo = 1");
        $stmt->execute([$chofer_id]);
        $viajes = $stmt->fetchAll();

        // Indexar viajes por id y ctg_nro
        $viajes_por_id = [];
        $viajes_por_ctg = [];
        foreach ($viajes as $v) {
            if (!empty($v['ctg_nro'])) {
                $viajes_por_ctg[$v['ctg_nro']] = $v;
            }
            $viajes_por_id[$v['id']] = $v;
        }

        // Obtener totales de adelantos y gastos de viajes del chofer
        // para agregarlos al detalle de cada adelanto en chofer_pagos
        $stmt = $pdo->prepare("
            SELECT va.viaje_id, COALESCE(SUM(va.monto), 0) as total_adelantos
            FROM viajes_adelantos va
            JOIN viajes v ON va.viaje_id = v.id AND v.chofer_id = ? AND v.activo = 1
            WHERE va.activo = 1
            GROUP BY va.viaje_id
        ");
        $stmt->execute([$chofer_id]);
        $adelantos_por_viaje = $stmt->fetchAll();
        $adel_por_viaje = [];
        foreach ($adelantos_por_viaje as $a) {
            $adel_por_viaje[$a['viaje_id']] = (float)$a['total_adelantos'];
        }

        $stmt = $pdo->prepare("
            SELECT vg.viaje_id, COALESCE(SUM(vg.monto), 0) as total_gastos
            FROM viajes_gastos vg
            JOIN viajes v ON vg.viaje_id = v.id AND v.chofer_id = ? AND v.activo = 1
            WHERE vg.activo = 1 AND vg.pagado_por = 'adelanto'
            GROUP BY vg.viaje_id
        ");
        $stmt->execute([$chofer_id]);
        $gastos_por_viaje = $stmt->fetchAll();
        $gastos_por_viaje_idx = [];
        foreach ($gastos_por_viaje as $g) {
            $gastos_por_viaje_idx[$g['viaje_id']] = (float)$g['total_gastos'];
        }

        // Asociar datos de viaje a cada pago y enriquecer los adelantos
        foreach ($pagos as &$p) {
            $p['origen'] = null;
            $p['destino'] = null;
            $p['producto'] = null;
            $p['viaje_ctg'] = null;

            $viaje_id_match = null;

            // Intentar determinar viaje_id
            if (!empty($p['viaje_id'])) {
                $viaje_id_match = $p['viaje_id'];
            } elseif (!empty($p['ctg_nro']) && isset($viajes_por_ctg[$p['ctg_nro']])) {
                $viaje_id_match = $viajes_por_ctg[$p['ctg_nro']]['id'];
            } elseif (!empty($p['detalle']) && preg_match('/CTG\s*(\d+)/i', $p['detalle'], $m)) {
                $ctg = $m[1];
                if (isset($viajes_por_ctg[$ctg])) {
                    $viaje_id_match = $viajes_por_ctg[$ctg]['id'];
                }
            }

            // Asociar datos de viaje
            if ($viaje_id_match && isset($viajes_por_id[$viaje_id_match])) {
                $v = $viajes_por_id[$viaje_id_match];
                $p['origen'] = $v['origen'];
                $p['destino'] = $v['destino'];
                $p['producto'] = $v['producto'];
                $p['viaje_ctg'] = $v['ctg_nro'];

                // Si es un adelanto, enriquecer con totales
                if ($p['tipo'] === 'adelanto' && isset($adel_por_viaje[$viaje_id_match])) {
                    $total_adel = $adel_por_viaje[$viaje_id_match];
                    $total_gast = $gastos_por_viaje_idx[$viaje_id_match] ?? 0;
                    $p['total_adelantos_viaje'] = $total_adel;
                    $p['total_gastos_viaje'] = $total_gast;
                } else {
                    $p['total_adelantos_viaje'] = 0;
                    $p['total_gastos_viaje'] = 0;
                }
            }
        }
        unset($p);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM chofer_pagos WHERE chofer_id = ?");
        $stmt->execute([$chofer_id]);
        $total = $stmt->fetchColumn();

        // Calcular totales por tipo
        $total_liquidacion = 0;
        $total_adelanto = 0;
        $total_sueldo = 0;
        $total_haber = 0;
        $total_debe = 0;
        foreach ($pagos as $p) {
            switch ($p['tipo']) {
                case 'liquidacion': 
                    $total_liquidacion += (float)$p['monto']; 
                    $total_haber += (float)$p['monto'];
                    break;
                case 'adelanto':    
                    $total_adelanto += (float)$p['monto']; 
                    $total_debe += (float)$p['monto'];
                    break;
                case 'sueldo':      
                    $total_sueldo += (float)$p['monto']; 
                    $total_debe += (float)$p['monto'];
                    break;
                default:
                    $total_debe += (float)$p['monto'];
                    break;
            }
        }
        $saldo_final = $total_haber - $total_debe;

        // Obtener gastos del chofer
        $stmt = $pdo->prepare("SELECT * FROM chofer_gastos WHERE chofer_id = ? ORDER BY fecha ASC, id ASC");
        $stmt->execute([$chofer_id]);
        $gastos = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM chofer_gastos WHERE chofer_id = ?");
        $stmt->execute([$chofer_id]);
        $total_gastos = $stmt->fetchColumn();
    }
}

// ─── PROCESAR POST: NUEVO PAGO ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo_pago' && $chofer_id > 0) {
    $currentRole = $_SESSION['user_role'] ?? 'user';

    if ($currentRole === 'developer' || choferOwner($pdo, $chofer_id, $active_company_id, $currentRole)) {
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $monto = (float)($_POST['monto'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'adelanto';
        $detalle = trim($_POST['detalle'] ?? '');

        if ($monto <= 0) {
            $error = "El monto debe ser mayor a cero.";
        } elseif (!in_array($tipo, ['adelanto', 'sueldo', 'liquidacion', 'otro'])) {
            $error = "Tipo de pago inválido.";
        } else {
            try {
                $sql = "INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$chofer_id, $fecha, $monto, $tipo, $detalle ?: null]);
                header("Location: choferes_ctacte?chofer_id=" . $chofer_id . "&msg=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar pago: " . $e->getMessage();
            }
        }
    } else {
        $error = "No autorizado.";
    }
}

// ─── PROCESAR POST: NUEVO GASTO ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo_gasto' && $chofer_id > 0) {
    $currentRole = $_SESSION['user_role'] ?? 'user';

    if ($currentRole === 'developer' || choferOwner($pdo, $chofer_id, $active_company_id, $currentRole)) {
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $monto = (float)($_POST['monto'] ?? 0);
        $tipo_gasto = $_POST['tipo_gasto'] ?? 'otros';
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($monto <= 0) {
            $error = "El monto debe ser mayor a cero.";
        } elseif (!in_array($tipo_gasto, ['combustible', 'peaje', 'comida', 'alojamiento', 'reparacion', 'otros'])) {
            $error = "Tipo de gasto inválido.";
        } else {
            try {
                $sql = "INSERT INTO chofer_gastos (chofer_id, fecha, monto, tipo_gasto, descripcion) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$chofer_id, $fecha, $monto, $tipo_gasto, $descripcion ?: null]);
                header("Location: choferes_ctacte?chofer_id=" . $chofer_id . "&msg=2");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar gasto: " . $e->getMessage();
            }
        }
    } else {
        $error = "No autorizado.";
    }
}
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <?php if ($chofer_id > 0 && $chofer): ?>
        <a href="choferes_ctacte" style="text-decoration:none; color:var(--accent); margin-bottom:8px; display:inline-block;">
            <i class="fas fa-arrow-left"></i> Volver a choferes
        </a>
        <h1 style="margin:4px 0 0 0;">Cuenta Corriente</h1>
        <?php else: ?>
        <h1 style="margin:0;">Cuenta Corriente - Choferes</h1>
        <p>Registro de pagos y adelantos a choferes de la empresa activa.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($chofer_id <= 0): ?>
<!-- ══════════════════════════════════════════════════════════
     MODO SELECCIÓN: Lista de choferes estilo grid
     ══════════════════════════════════════════════════════════ -->
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:14px;">
    <?php if (empty($choferes)): ?>
    <div class="card" style="grid-column:1/-1; text-align:center; padding:40px;">
        <p style="opacity:0.5;">No hay choferes registrados en la empresa activa.</p>
    </div>
    <?php else: ?>
        <?php foreach($choferes as $c): ?>
        <a href="choferes_ctacte?chofer_id=<?= (int)$c['id'] ?>" style="text-decoration:none; color:inherit;">
            <div style="background:#fff; border-radius:12px; padding:16px; border:1px solid #e0e0e0; transition:all 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.06);"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';this.style.borderColor='var(--accent)';"
                 onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.06)';this.style.borderColor='#e0e0e0';">
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="background:linear-gradient(135deg, #e65100, #ff9800); width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:#fff; flex-shrink:0; box-shadow:0 2px 6px rgba(230,81,0,0.3);">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:bold; font-size:1rem; color:#333; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </div>
                        <?php if (!empty($c['telefono'])): ?>
                        <div style="font-size:0.8rem; color:#888; margin-top:2px;">
                            <i class="fas fa-phone" style="opacity:0.5;"></i> <?= htmlspecialchars($c['telefono']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ((float)$c['porcentaje_ganancia'] > 0): ?>
                        <div style="font-size:0.75rem; color:#2e7d32; margin-top:2px;">
                            <i class="fas fa-percentage"></i> <?= number_format((float)$c['porcentaje_ganancia'], 1) ?>%
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

<?php elseif ($chofer): ?>
<!-- ══════════════════════════════════════════════════════════
     MODO DETALLE: Cuenta corriente del chofer
     ══════════════════════════════════════════════════════════ -->

<!-- ─── TARJETA PRINCIPAL: Info del Chofer ────────────── -->
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #e65100, #ff9800, #fdd835, #2e7d32); position:absolute; top:0; left:0; right:0;"></div>

    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div style="background:linear-gradient(135deg, #e65100, #ff9800); width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:#fff; flex-shrink:0; box-shadow:0 3px 8px rgba(230,81,0,0.3);">
            <i class="fas fa-user-circle"></i>
        </div>
        <div style="flex:1;">
            <h2 style="margin:0; font-size:1.4rem;"><?= htmlspecialchars($chofer['nombre']) ?></h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:4px;">
                <?php if (!empty($chofer['telefono'])): ?>
                <span style="background:#fff3e0; color:#e65100; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:bold; border:1px solid #ffcc80;">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($chofer['telefono']) ?>
                </span>
                <?php endif; ?>
                <?php if ((float)$chofer['porcentaje_ganancia'] > 0): ?>
                <span style="background:#e8f5e9; color:#2e7d32; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:bold; border:1px solid #a5d6a7;">
                    <i class="fas fa-percentage"></i> <?= number_format((float)$chofer['porcentaje_ganancia'], 1) ?>%
                </span>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- ─── TARJETA: RESUMEN POR TIPO ────────────────────── -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:14px; margin-bottom:20px;">
    <div style="background:linear-gradient(135deg, #fff8e1, #fff); border-radius:12px; padding:16px; border:1px solid #ffe082; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#f57f17; font-weight:bold;">
            <i class="fas fa-hand-holding-usd"></i> Adelantos
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#e65100; margin-top:4px;">
            $ <?= number_format($total_adelanto, 2, ',', '.') ?>
        </div>
    </div>
    <div style="background:linear-gradient(135deg, #e8f5e9, #fff); border-radius:12px; padding:16px; border:1px solid #a5d6a7; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#2e7d32; font-weight:bold;">
            <i class="fas fa-check-circle"></i> Liquidaciones
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#1b5e20; margin-top:4px;">
            $ <?= number_format($total_liquidacion, 2, ',', '.') ?>
        </div>
    </div>
    <div style="background:linear-gradient(135deg, #e3f2fd, #fff); border-radius:12px; padding:16px; border:1px solid #90caf9; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#1565c0; font-weight:bold;">
            <i class="fas fa-wallet"></i> Sueldos
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#0d47a1; margin-top:4px;">
            $ <?= number_format($total_sueldo, 2, ',', '.') ?>
        </div>
    </div>
    <div style="background:linear-gradient(135deg, #ffebee, #fff); border-radius:12px; padding:16px; border:1px solid #ef9a9a; text-align:center;">
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#c62828; font-weight:bold;">
            <i class="fas fa-receipt"></i> Gastos
        </div>
        <div style="font-size:1.3rem; font-weight:bold; color:#b71c1c; margin-top:4px;">
            $ <?= number_format($total_gastos, 2, ',', '.') ?>
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

<!-- ─── FILA: NUEVO PAGO + NUEVO GASTO (en la misma línea) ── -->
<div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
    
    <!-- TARJETA: NUEVO PAGO -->
    <div class="card" style="flex:1; min-width:300px; position:relative; overflow:hidden;">
        <div style="height:4px; background:linear-gradient(90deg, var(--accent), #2ecc71); position:absolute; top:0; left:0; right:0;"></div>
        <h3 style="margin:0 0 14px 0;">
            <span style="background:linear-gradient(135deg, var(--primary), #34495e); color:#fff; padding:5px 12px; border-radius:8px; font-size:0.9rem;">
                <i class="fas fa-plus-circle"></i> Registrar Nuevo Pago
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
                        <option value="adelanto">💰 Adelanto</option>
                        <option value="sueldo">💼 Sueldo</option>
                        <option value="liquidacion">✅ Liquidación</option>
                        <option value="otro">📋 Otro</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2; min-width: 180px; margin: 0;">
                    <label><i class="fas fa-pen" style="color:var(--accent);"></i> Detalle</label>
                    <input type="text" name="detalle" class="input-field" placeholder="Concepto del pago">
                </div>
                <button type="submit" class="btn-primary" style="height: 38px; white-space:nowrap;">
                    <i class="fas fa-save"></i> Agregar
                </button>
            </div>
        </form>
    </div>

    <!-- TARJETA: NUEVO GASTO -->
    <div class="card" style="flex:1; min-width:300px; position:relative; overflow:hidden;">
        <div style="height:4px; background:linear-gradient(90deg, #e74c3c, #c0392b); position:absolute; top:0; left:0; right:0;"></div>
        <h3 style="margin:0 0 14px 0;">
            <span style="background:linear-gradient(135deg, #c0392b, #e74c3c); color:#fff; padding:5px 12px; border-radius:8px; font-size:0.9rem;">
                <i class="fas fa-receipt"></i> Registrar Nuevo Gasto
            </span>
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="nuevo_gasto">
            <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                    <label><i class="far fa-calendar-alt" style="color:#e74c3c;"></i> Fecha</label>
                    <input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                    <label><i class="fas fa-dollar-sign" style="color:#e74c3c;"></i> Monto</label>
                    <input type="number" step="0.01" name="monto" class="input-field" placeholder="0.00" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 130px; margin: 0;">
                    <label><i class="fas fa-tag" style="color:#e74c3c;"></i> Tipo de Gasto</label>
                    <select name="tipo_gasto" class="input-field">
                        <option value="combustible">⛽ Combustible</option>
                        <option value="peaje">🛣️ Peaje</option>
                        <option value="comida">🍽️ Comida</option>
                        <option value="alojamiento">🏨 Alojamiento</option>
                        <option value="reparacion">🔧 Reparación</option>
                        <option value="otros">📋 Otros</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2; min-width: 180px; margin: 0;">
                    <label><i class="fas fa-pen" style="color:#e74c3c;"></i> Descripción</label>
                    <input type="text" name="descripcion" class="input-field" placeholder="Descripción del gasto">
                </div>
                <button type="submit" class="btn-primary" style="height: 38px; white-space:nowrap; background:linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-save"></i> Agregar Gasto
                </button>
            </div>
        </form>
    </div>

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
        <p style="text-align:center; padding:30px; opacity:0.5;">No hay movimientos registrados para este chofer.</p>
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
                        'adelanto' => '<span class="badge" style="background:#f39c12; color:#fff;">💰 Adelanto</span>',
                        'gasto_adelanto' => '<span class="badge" style="background:#e74c3c; color:#fff;">💳 Gasto (Adelanto)</span>',
                        'sueldo' => '<span class="badge" style="background:#2980b9; color:#fff;">💼 Sueldo</span>',
                        'liquidacion' => '<span class="badge" style="background:#27ae60; color:#fff;">✅ Liquidación</span>',
                        'otro' => '<span class="badge" style="background:#95a5a6; color:#fff;">📋 Otro</span>',
                        default => '<span class="badge">' . htmlspecialchars($p['tipo']) . '</span>'
                    };

                    $monto = (float)$p['monto'];
                    if ($p['tipo'] === 'liquidacion') {
                        $debe = 0;
                        $haber = $monto;
                    } else {
                        $debe = $monto;
                        $haber = 0;
                    }
                    $saldo_acumulado += $haber - $debe;
                ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($p['fecha'])) ?></td>
                    <td>
                        <?= $tipo_badge ?>
                        <?php if (!empty($p['origen'])): ?>
                        <br><small style="opacity:0.7; color:#555;">
                            <i class="fas fa-truck"></i> 
                            <?= htmlspecialchars($p['origen']) ?> → <?= htmlspecialchars($p['destino']) ?>
                            <?php if (!empty($p['producto'])): ?>
                            · <?= htmlspecialchars($p['producto']) ?>
                            <?php endif; ?>
                            <?php if (!empty($p['viaje_ctg'])): ?>
                            · CTG: <?= htmlspecialchars($p['viaje_ctg']) ?>
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                        <?php if (!empty($p['detalle']) && $p['tipo'] !== 'adelanto'): ?>
                        <br><small style="opacity:0.6;"><?= htmlspecialchars($p['detalle']) ?></small>
                        <?php endif; ?>
                        <?php if ($p['tipo'] === 'adelanto' && !empty($p['total_adelantos_viaje'])): ?>
                        <br><small style="opacity:0.8; color:#333; font-weight:bold;">
                            💰 Adelanto $ <?= number_format($p['total_adelantos_viaje'], 2, ',', '.') ?> 
                            - 
                            🧾 Gastos $ <?= number_format($p['total_gastos_viaje'], 2, ',', '.') ?>
                        </small>
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

<!-- ─── TABLA: HISTORIAL DE GASTOS ───────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <h3 style="margin:0;"><i class="fas fa-receipt"></i> Historial de Gastos</h3>
        <span style="background:#f0f0f0; padding:4px 12px; border-radius:20px; font-size:0.8rem; opacity:0.7;">
            <?= count($gastos) ?> registro(s)
        </span>
    </div>

    <?php if (empty($gastos)): ?>
        <p style="text-align:center; padding:30px; opacity:0.5;">No hay gastos registrados para este chofer.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo de Gasto</th>
                    <th>Descripción</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($gastos as $g): 
                    $tipo_badge = match($g['tipo_gasto']) {
                        'combustible' => '<span class="badge" style="background:#f39c12; color:#fff;">⛽ Combustible</span>',
                        'peaje' => '<span class="badge" style="background:#3498db; color:#fff;">🛣️ Peaje</span>',
                        'comida' => '<span class="badge" style="background:#e67e22; color:#fff;">🍽️ Comida</span>',
                        'alojamiento' => '<span class="badge" style="background:#9b59b6; color:#fff;">🏨 Alojamiento</span>',
                        'reparacion' => '<span class="badge" style="background:#e74c3c; color:#fff;">🔧 Reparación</span>',
                        'otros' => '<span class="badge" style="background:#95a5a6; color:#fff;">📋 Otros</span>',
                        default => '<span class="badge">' . htmlspecialchars($g['tipo_gasto']) . '</span>'
                    };
                ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($g['fecha'])) ?></td>
                    <td><?= $tipo_badge ?></td>
                    <td><?= !empty($g['descripcion']) ? htmlspecialchars($g['descripcion']) : '-' ?></td>
                    <td style="text-align:right; font-weight:bold; color:#e74c3c;">
                        $ <?= number_format((float)$g['monto'], 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="3" style="text-align:right;">Total Gastos:</td>
                    <td style="text-align:right; color:#e74c3c;">
                        $ <?= number_format($total_gastos, 2, ',', '.') ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
