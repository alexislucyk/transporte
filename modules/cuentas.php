<?php
/**
 * Módulo Cuentas - Trans Cargo Hub
 * 
 * Reemplaza tesoreria.php
 * Registra cuentas de bancos, billeteras virtuales y caja de efectivo
 * donde van a ingresar los pagos de los fletes.
 *
 * Multi-tenant estricto:
 *   - Toda lectura/escritura filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$mensaje = '';
$error   = '';

// ─── AJAX: OBTENER MOVIMIENTOS ─────────────────────
if (isset($_GET['ajax_movimientos']) && $_GET['ajax_movimientos'] === '1') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $cuenta_id = (int)($_GET['cuenta_id'] ?? 0);

    if ($cuenta_id <= 0) {
        echo json_encode(['error' => 'ID de cuenta inválido']);
        exit;
    }

    // Verificar que la cuenta pertenezca al tenant
    $check = $pdo->prepare("SELECT id, nombre FROM cuentas_empresa WHERE id = ? AND transportista_id = ? AND activo = 1");
    $check->execute([$cuenta_id, $active_company_id]);
    $cuenta = $check->fetch();

    if (!$cuenta) {
        echo json_encode(['error' => 'Cuenta no encontrada']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT cm.*,
               u.username as creado_por_usuario,
               cf.viaje_id,
               v.ctg_nro,
               v.carta_porte_nro,
               v.otros_docs
        FROM cuentas_movimientos cm
        LEFT JOIN users u ON cm.created_by = u.id
        LEFT JOIN cobros_fletes cf ON cf.id = cm.referencia_id AND cm.referencia_tipo = 'cobro_flete'
        LEFT JOIN viajes v ON v.id = cf.viaje_id
        WHERE cm.cuenta_id = ? AND cm.transportista_id = ? AND cm.activo = 1
        ORDER BY cm.fecha_movimiento DESC, cm.id DESC
    ");
    $stmt->execute([$cuenta_id, $active_company_id]);
    $movimientos = $stmt->fetchAll();

    // Build reference label for each movement
    foreach ($movimientos as &$mov) {
        if ($mov['referencia_tipo'] === 'cobro_flete') {
            if (!empty($mov['ctg_nro'])) {
                $mov['ref_label'] = 'CTG ' . $mov['ctg_nro'];
            } elseif (!empty($mov['carta_porte_nro'])) {
                $mov['ref_label'] = 'CP ' . $mov['carta_porte_nro'];
            } elseif (!empty($mov['otros_docs'])) {
                $mov['ref_label'] = $mov['otros_docs'];
            } else {
                $mov['ref_label'] = 'Viaje #' . $mov['viaje_id'];
            }
            // Limpiar el concepto: quitar la parte de referencia para que quede solo "Cobro flete"
            $mov['concepto'] = 'Cobro flete';
        } else {
            $mov['ref_label'] = null;
        }
    }
    unset($mov);

    echo json_encode([
        'success'        => true,
        'cuenta_nombre'  => $cuenta['nombre'],
        'movimientos'    => $movimientos
    ]);
    exit;
}

// ─── PROCESAR FORMULARIOS ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─── CREAR / ACTUALIZAR CUENTA ──────────────────
    if (in_array($action, ['crear', 'editar'])) {
        $id              = (int)($_POST['id'] ?? 0);
        $nombre          = trim($_POST['nombre'] ?? '');
        $tipo            = $_POST['tipo'] ?? 'banco';
        $banco           = trim($_POST['banco'] ?? '');
        $numero_cuenta   = trim($_POST['numero_cuenta'] ?? '');
        $cbu             = trim($_POST['cbu'] ?? '');
        $alias           = trim($_POST['alias'] ?? '');
        $titular         = trim($_POST['titular'] ?? '');
        $cuit_titular    = trim($_POST['cuit_titular'] ?? '');
        $saldo_inicial   = (float)($_POST['saldo_inicial'] ?? 0);

        if ($nombre === '') {
            $error = 'El nombre de la cuenta es obligatorio.';
        } elseif (!in_array($tipo, ['banco', 'billetera_virtual', 'caja_efectivo', 'otro'])) {
            $error = 'Tipo de cuenta inválido.';
        } else {
            try {
                if ($action === 'crear') {
                    $stmt = $pdo->prepare("
                        INSERT INTO cuentas_empresa 
                            (transportista_id, nombre, tipo, banco, numero_cuenta, cbu, alias, titular, cuit_titular, saldo_inicial, saldo_actual)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $active_company_id, $nombre, $tipo,
                        $banco ?: null, $numero_cuenta ?: null, $cbu ?: null, $alias ?: null,
                        $titular ?: null, $cuit_titular ?: null,
                        $saldo_inicial, $saldo_inicial
                    ]);
                    $mensaje = "Cuenta '{$nombre}' creada exitosamente.";
                } else {
                    // Editar: verificar que pertenezca al tenant
                    $check = $pdo->prepare("SELECT id FROM cuentas_empresa WHERE id = ? AND transportista_id = ? AND activo = 1");
                    $check->execute([$id, $active_company_id]);
                    if (!$check->fetchColumn()) {
                        $error = 'Cuenta no encontrada o no pertenece a la empresa activa.';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE cuentas_empresa SET
                                nombre = ?, tipo = ?, banco = ?, numero_cuenta = ?,
                                cbu = ?, alias = ?, titular = ?, cuit_titular = ?
                            WHERE id = ? AND transportista_id = ? AND activo = 1
                        ");
                        $stmt->execute([
                            $nombre, $tipo, $banco ?: null, $numero_cuenta ?: null,
                            $cbu ?: null, $alias ?: null, $titular ?: null, $cuit_titular ?: null,
                            $id, $active_company_id
                        ]);
                        $mensaje = "Cuenta '{$nombre}' actualizada exitosamente.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Error al guardar cuenta: " . $e->getMessage();
            }
        }
    }

    // ─── ELIMINAR (borrado lógico) ──────────────────
    if ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cuentas_empresa SET activo = 0 WHERE id = ? AND transportista_id = ?");
                $stmt->execute([$id, $active_company_id]);
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Cuenta eliminada exitosamente.";
                } else {
                    $error = 'Cuenta no encontrada o no pertenece a la empresa activa.';
                }
            } catch (PDOException $e) {
                $error = "Error al eliminar cuenta: " . $e->getMessage();
            }
        }
    }

    // ─── AJUSTAR SALDO ──────────────────────────────
    if ($action === 'ajustar_saldo') {
        $id     = (int)($_POST['id'] ?? 0);
        $nuevo_saldo = (float)($_POST['nuevo_saldo'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cuentas_empresa SET saldo_actual = ? WHERE id = ? AND transportista_id = ? AND activo = 1");
                $stmt->execute([$nuevo_saldo, $id, $active_company_id]);
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Saldo ajustado exitosamente.";
                } else {
                    $error = 'Cuenta no encontrada o no pertenece a la empresa activa.';
                }
            } catch (PDOException $e) {
                $error = "Error al ajustar saldo: " . $e->getMessage();
            }
        }
    }
}

// ─── LISTAR CUENTAS ACTIVAS ─────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM cuentas_empresa
    WHERE transportista_id = ? AND activo = 1
    ORDER BY tipo ASC, nombre ASC
");
$stmt->execute([$active_company_id]);
$cuentas = $stmt->fetchAll();

// Agrupar por tipo para mostrar
$cuentas_agrupadas = [
    'banco'            => [],
    'billetera_virtual' => [],
    'caja_efectivo'    => [],
    'otro'             => []
];
$total_saldos = 0;
foreach ($cuentas as $c) {
    $t = $c['tipo'];
    if (!isset($cuentas_agrupadas[$t])) $t = 'otro';
    $cuentas_agrupadas[$t][] = $c;
    $total_saldos += (float)($c['saldo_actual'] ?? 0);
}

// Badge de tipo
function badgeTipoCuenta($tipo) {
    return match($tipo) {
        'banco'            => '<span class="badge" style="background:#2c3e50; color:#fff;"><i class="fas fa-university"></i> Banco</span>',
        'billetera_virtual' => '<span class="badge" style="background:#8e44ad; color:#fff;"><i class="fas fa-mobile-alt"></i> Billetera Virtual</span>',
        'caja_efectivo'    => '<span class="badge" style="background:#27ae60; color:#fff;"><i class="fas fa-money-bill-wave"></i> Caja Efectivo</span>',
        default            => '<span class="badge" style="background:#95a5a6; color:#fff;"><i class="fas fa-archive"></i> Otro</span>',
    };
}

// Icono de tipo
function iconoTipoCuenta($tipo) {
    return match($tipo) {
        'banco'            => 'fa-university',
        'billetera_virtual' => 'fa-mobile-alt',
        'caja_efectivo'    => 'fa-money-bill-wave',
        default            => 'fa-archive',
    };
}
?>
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #2c3e50, #3498db, #27ae60); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-wallet" style="color:var(--accent); margin-right:8px;"></i>
                Cuentas
            </h2>
            <div style="margin-top:6px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Bancos, billeteras virtuales y caja de efectivo
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="background:linear-gradient(135deg, #2c3e50, #34495e); color:#fff; padding:10px 18px; border-radius:10px; text-align:center;">
                <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; opacity:0.8;">Saldo Total</div>
                <div style="font-size:1.3rem; font-weight:bold;">$ <?= number_format($total_saldos, 2, ',', '.') ?></div>
            </div>
            <button onclick="openModal('modal-crear')" class="btn-primary" style="background:#27ae60; border:none; padding:10px 14px;">
                <i class="fas fa-plus"></i> Nueva Cuenta
            </button>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success" style="padding:14px 18px; margin-bottom:18px; border-left:5px solid #27ae60; background:#eafaf1; border-radius:6px;">
    <i class="fas fa-check-circle" style="color:#27ae60; margin-right:6px;"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error" style="padding:14px 18px; margin-bottom:18px; border-left:5px solid #e74c3c; background:#fdedec; border-radius:6px;">
    <i class="fas fa-exclamation-triangle" style="color:#e74c3c; margin-right:6px;"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (empty($cuentas)): ?>
<div class="card" style="text-align:center; padding:50px 20px;">
    <i class="fas fa-wallet fa-4x" style="color:#ccc; margin-bottom:16px;"></i>
    <h3 style="margin:0 0 8px 0;">No hay cuentas registradas</h3>
    <p style="opacity:0.7; margin:0 0 20px 0;">Creá una cuenta para comenzar a registrar los cobros de fletes.</p>
    <button onclick="openModal('modal-crear')" class="btn-primary" style="background:#27ae60; border:none; padding:12px 20px;">
        <i class="fas fa-plus"></i> Crear Primera Cuenta
    </button>
</div>
<?php else: ?>
    <?php $total_general = 0; ?>
    <?php foreach (['banco' => 'Bancos', 'billetera_virtual' => 'Billeteras Virtuales', 'caja_efectivo' => 'Caja de Efectivo', 'otro' => 'Otras Cuentas'] as $tipo_key => $tipo_label): ?>
        <?php if (!empty($cuentas_agrupadas[$tipo_key])): ?>
        <div class="card" style="margin-bottom:20px;">
            <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
                <i class="fas <?= iconoTipoCuenta($tipo_key) ?>" style="color:var(--accent);"></i>
                <?= $tipo_label ?>
                <span class="badge" style="background:var(--accent); color:#fff; font-size:0.8rem; padding:4px 10px; margin-left:auto;">
                    <?= count($cuentas_agrupadas[$tipo_key]) ?> cuenta(s)
                </span>
            </h3>
            <div class="table-container">
                <table class="data-table cuentas-table">
                    <colgroup>
                        <col style="min-width:180px;">
                        <col style="min-width:150px;">
                        <col style="min-width:120px;">
                        <col style="min-width:200px;">
                        <col style="min-width:170px;">
                        <col style="min-width:130px;">
                        <col style="min-width:200px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Banco / Entidad</th>
                            <th>N° Cuenta</th>
                            <th>CBU / Alias</th>
                            <th>Titular</th>
                            <th style="text-align:right;">Saldo Actual</th>
                            <th style="text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cuentas_agrupadas[$tipo_key] as $c): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                                <div style="font-size:0.75rem; margin-top:2px;"><?= badgeTipoCuenta($c['tipo']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($c['banco'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($c['numero_cuenta'] ?? '-') ?></td>
                            <td style="font-size:0.85rem;">
                                <?php if (!empty($c['cbu'])): ?>
                                    <div><strong>CBU:</strong> <?= htmlspecialchars($c['cbu']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($c['alias'])): ?>
                                    <div><strong>Alias:</strong> <?= htmlspecialchars($c['alias']) ?></div>
                                <?php endif; ?>
                                <?php if (empty($c['cbu']) && empty($c['alias'])): ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($c['titular'] ?? '-') ?>
                                <?php if (!empty($c['cuit_titular'])): ?>
                                    <div style="font-size:0.75rem; opacity:0.7;">CUIT: <?= htmlspecialchars($c['cuit_titular']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; font-weight:bold; font-size:1.05rem; color:#27ae60;">
                                $ <?= number_format((float)$c['saldo_actual'], 2, ',', '.') ?>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                                    <button onclick="verMovimientos(<?= (int)$c['id'] ?>)" class="btn-primary btn-sm" style="background:#8e44ad; border:none; padding:6px 10px; font-size:0.8rem; cursor:pointer;" title="Movimientos">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <button onclick="editarCuenta(<?= (int)$c['id'] ?>)" class="btn-primary btn-sm" style="background:#3498db; border:none; padding:6px 10px; font-size:0.8rem; cursor:pointer;" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="ajustarSaldo(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= number_format((float)$c['saldo_actual'], 2, '.', '') ?>)" class="btn-primary btn-sm" style="background:#e67e22; border:none; padding:6px 10px; font-size:0.8rem; cursor:pointer;">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return appConfirm('¿Eliminar la cuenta «<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>»?', function(){ this.submit(); }.bind(this), 'Eliminar Cuenta')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" style="background:#e74c3c; border:none; color:#fff; padding:6px 10px; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php
                        $subtotal = array_sum(array_map(function($c) { return (float)($c['saldo_actual'] ?? 0); }, $cuentas_agrupadas[$tipo_key]));
                        $total_general += $subtotal;
                        ?>
                        <tr style="font-weight:bold; background:#f8f9fa;">
                            <td colspan="5" style="text-align:right;">Subtotal <?= $tipo_label ?>:</td>
                            <td style="text-align:right; color:#27ae60; font-size:1.05rem;">
                                $ <?= number_format($subtotal, 2, ',', '.') ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <!-- Total General consolidado -->
    <div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, #2c3e50, #34495e); color:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 8px;">
            <span style="font-weight:bold; font-size:1.1rem;">Total General</span>
            <span style="font-weight:bold; font-size:1.3rem;">$ <?= number_format($total_general, 2, ',', '.') ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- ─── MODAL CREAR CUENTA ─────────────────────────── -->
<div id="modal-crear" class="modal">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #27ae60, #2ecc71); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-plus-circle" style="margin-right:8px;"></i> Nueva Cuenta
            </h3>
            <span class="close-modal" onclick="closeModal('modal-crear')" style="color:#fff; font-size:1.2rem;">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="crear">

                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Nombre de la Cuenta *</label>
                    <input type="text" name="nombre" class="input-field" required placeholder="Ej: Cuenta Corriente Banco Nación" style="width:100%;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Tipo de Cuenta *</label>
                    <select name="tipo" class="input-field" required style="width:100%;">
                        <option value="banco">Banco</option>
                        <option value="billetera_virtual">Billetera Virtual</option>
                        <option value="caja_efectivo">Caja de Efectivo</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Banco / Entidad</label>
                        <input type="text" name="banco" class="input-field" placeholder="Ej: Banco Nación" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">N° Cuenta</label>
                        <input type="text" name="numero_cuenta" class="input-field" placeholder="Número de cuenta" style="width:100%;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">CBU</label>
                        <input type="text" name="cbu" class="input-field" placeholder="22 dígitos" maxlength="22" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Alias</label>
                        <input type="text" name="alias" class="input-field" placeholder="Alias CBU" style="width:100%;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Titular</label>
                        <input type="text" name="titular" class="input-field" placeholder="Nombre del titular" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">CUIT Titular</label>
                        <input type="text" name="cuit_titular" class="input-field" placeholder="11 dígitos" maxlength="11" style="width:100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Saldo Inicial</label>
                    <input type="number" step="0.01" min="0" name="saldo_inicial" class="input-field" value="0" style="width:100%;">
                    <div style="font-size:0.75rem; color:#888; margin-top:3px;">
                        <i class="fas fa-info-circle"></i> Saldo con el que se crea la cuenta.
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px; border-top:1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-crear')" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#27ae60; border:none; padding:10px 18px;">
                    <i class="fas fa-save"></i> Guardar Cuenta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── MODAL EDITAR CUENTA ────────────────────────── -->
<div id="modal-editar" class="modal">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #3498db, #2980b9); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-edit" style="margin-right:8px;"></i> Editar Cuenta
            </h3>
            <span class="close-modal" onclick="closeModal('modal-editar')" style="color:#fff; font-size:1.2rem;">&times;</span>
        </div>
        <form method="POST" id="form-editar">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="edit-id">

                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Nombre de la Cuenta *</label>
                    <input type="text" name="nombre" id="edit-nombre" class="input-field" required style="width:100%;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Tipo de Cuenta *</label>
                    <select name="tipo" id="edit-tipo" class="input-field" required style="width:100%;">
                        <option value="banco">Banco</option>
                        <option value="billetera_virtual">Billetera Virtual</option>
                        <option value="caja_efectivo">Caja de Efectivo</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Banco / Entidad</label>
                        <input type="text" name="banco" id="edit-banco" class="input-field" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">N° Cuenta</label>
                        <input type="text" name="numero_cuenta" id="edit-numero_cuenta" class="input-field" style="width:100%;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">CBU</label>
                        <input type="text" name="cbu" id="edit-cbu" class="input-field" maxlength="22" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Alias</label>
                        <input type="text" name="alias" id="edit-alias" class="input-field" style="width:100%;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">Titular</label>
                        <input type="text" name="titular" id="edit-titular" class="input-field" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px;">CUIT Titular</label>
                        <input type="text" name="cuit_titular" id="edit-cuit_titular" class="input-field" maxlength="11" style="width:100%;">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px; border-top:1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-editar')" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#3498db; border:none; padding:10px 18px;">
                    <i class="fas fa-save"></i> Actualizar Cuenta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── MODAL AJUSTAR SALDO ────────────────────────── -->
<div id="modal-ajustar-saldo" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #e67e22, #d35400); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-dollar-sign" style="margin-right:8px;"></i> Ajustar Saldo
            </h3>
            <span class="close-modal" onclick="closeModal('modal-ajustar-saldo')" style="color:#fff; font-size:1.2rem;">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="ajustar_saldo">
                <input type="hidden" name="id" id="ajuste-id">

                <p style="margin-top:0;" id="ajuste-nombre-display"></p>

                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px;">Nuevo Saldo</label>
                    <input type="number" step="0.01" name="nuevo_saldo" id="ajuste-saldo" class="input-field" required style="width:100%; font-size:1.2rem;">
                    <div style="font-size:0.75rem; color:#888; margin-top:3px;">
                        <i class="fas fa-info-circle"></i> Ingresá el saldo actualizado de la cuenta.
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px; border-top:1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-ajustar-saldo')" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#e67e22; border:none; padding:10px 18px;">
                    <i class="fas fa-check"></i> Ajustar Saldo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── MODAL MOVIMIENTOS ─────────────────────────── -->
<div id="modal-movimientos" class="modal">
    <div class="modal-content" style="max-width:800px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #8e44ad, #9b59b6); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-list" style="margin-right:8px;"></i> Movimientos de Cuenta
            </h3>
            <span class="close-modal" onclick="closeModal('modal-movimientos')" style="color:#fff; font-size:1.2rem;">&times;</span>
        </div>
        <div class="modal-body" style="padding:16px;">
            <div id="movimientos-loading" style="text-align:center; padding:30px;">
                <i class="fas fa-spinner fa-spin fa-2x" style="color:#8e44ad;"></i>
                <p style="margin-top:10px; opacity:0.7;">Cargando movimientos...</p>
            </div>
            <div id="movimientos-content" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                    <h4 id="mov-cuenta-nombre" style="margin:0; font-size:1rem; color:#8e44ad;"></h4>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span id="mov-total-entradas" style="font-size:0.85rem; color:#27ae60; font-weight:bold;"></span>
                        <span style="opacity:0.5;">|</span>
                        <span id="mov-total-salidas" style="font-size:0.85rem; color:#e74c3c; font-weight:bold;"></span>
                    </div>
                </div>
                <div id="movimientos-error" style="display:none; padding:14px 18px; margin-bottom:12px; border-left:5px solid #e74c3c; background:#fdedec; border-radius:6px; color:#e74c3c;"></div>
                <div class="table-container">
                    <table class="data-table" style="font-size:0.85rem;">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th>Referencia</th>
                                <th style="text-align:right;">Monto</th>
                                <th style="text-align:right;">Saldo Resultante</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="movimientos-tbody">
                            <tr id="movimientos-vacio">
                                <td colspan="7" style="text-align:center; padding:30px; opacity:0.6;">
                                    <i class="fas fa-inbox fa-2x" style="display:block; margin-bottom:8px;"></i>
                                    No hay movimientos registrados en esta cuenta.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:flex-end; border-top:1px solid #eee;">
            <button type="button" class="btn-secondary" onclick="closeModal('modal-movimientos')" style="padding:10px 18px;">Cerrar</button>
        </div>
    </div>
</div>

<script>
// ─── DATOS DE CUENTAS PARA EDICIÓN ──────────────────
const cuentasData = <?= json_encode($cuentas) ?>;

function editarCuenta(id) {
    const c = cuentasData.find(item => item.id == id);
    if (!c) return;

    document.getElementById('edit-id').value = c.id;
    document.getElementById('edit-nombre').value = c.nombre || '';
    document.getElementById('edit-tipo').value = c.tipo || 'banco';
    document.getElementById('edit-banco').value = c.banco || '';
    document.getElementById('edit-numero_cuenta').value = c.numero_cuenta || '';
    document.getElementById('edit-cbu').value = c.cbu || '';
    document.getElementById('edit-alias').value = c.alias || '';
    document.getElementById('edit-titular').value = c.titular || '';
    document.getElementById('edit-cuit_titular').value = c.cuit_titular || '';

    openModal('modal-editar');
}

function ajustarSaldo(id, nombre, saldoActual) {
    document.getElementById('ajuste-id').value = id;
    document.getElementById('ajuste-nombre-display').innerHTML = '<strong>Cuenta:</strong> ' + nombre;
    document.getElementById('ajuste-saldo').value = saldoActual;
    openModal('modal-ajustar-saldo');
}

// ─── MOVIMIENTOS ────────────────────────────────────
function verMovimientos(cuentaId) {
    const loading = document.getElementById('movimientos-loading');
    const content = document.getElementById('movimientos-content');
    const errorDiv = document.getElementById('movimientos-error');
    const tbody = document.getElementById('movimientos-tbody');
    const vacio = document.getElementById('movimientos-vacio');

    // Resetear vista
    loading.style.display = 'block';
    content.style.display = 'none';
    errorDiv.style.display = 'none';
    openModal('modal-movimientos');

    fetch('cuentas?ajax_movimientos=1&cuenta_id=' + cuentaId)
        .then(res => res.json())
        .then(data => {
            loading.style.display = 'none';

            if (data.error) {
                errorDiv.textContent = data.error;
                errorDiv.style.display = 'block';
                content.style.display = 'block';
                return;
            }

            document.getElementById('mov-cuenta-nombre').textContent = 'Cuenta: ' + data.cuenta_nombre;

            // Limpiar filas (excepto la fila de vacío)
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }

            if (!data.movimientos || data.movimientos.length === 0) {
                tbody.appendChild(vacio);
                document.getElementById('mov-total-entradas').textContent = '';
                document.getElementById('mov-total-salidas').textContent = '';
                content.style.display = 'block';
                return;
            }

            let totalEntradas = 0;
            let totalSalidas = 0;

            data.movimientos.forEach(function(m) {
                const tr = document.createElement('tr');

                const monto = parseFloat(m.monto) || 0;
                const saldoRes = parseFloat(m.saldo_resultante) || 0;

                if (m.tipo === 'entrada') totalEntradas += monto;
                else totalSalidas += monto;

                // Fecha
                const tdFecha = document.createElement('td');
                tdFecha.textContent = m.fecha_movimiento;
                tdFecha.style.whiteSpace = 'nowrap';
                tr.appendChild(tdFecha);

                // Tipo badge
                const tdTipo = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'badge';
                if (m.tipo === 'entrada') {
                    badge.style.cssText = 'background:#27ae60; color:#fff;';
                    badge.innerHTML = '<i class="fas fa-arrow-down"></i> Entrada';
                } else {
                    badge.style.cssText = 'background:#e74c3c; color:#fff;';
                    badge.innerHTML = '<i class="fas fa-arrow-up"></i> Salida';
                }
                tdTipo.appendChild(badge);
                tr.appendChild(tdTipo);

                // Concepto
                const tdConcepto = document.createElement('td');
                tdConcepto.textContent = m.concepto || '-';
                tr.appendChild(tdConcepto);

                // Referencia
                const tdRef = document.createElement('td');
                tdRef.style.fontSize = '0.8rem';
                if (m.ref_label) {
                    tdRef.textContent = m.ref_label;
                } else {
                    tdRef.textContent = '-';
                }
                tr.appendChild(tdRef);

                // Monto
                const tdMonto = document.createElement('td');
                tdMonto.style.textAlign = 'right';
                tdMonto.style.fontWeight = 'bold';
                tdMonto.style.color = m.tipo === 'entrada' ? '#27ae60' : '#e74c3c';
                tdMonto.textContent = '$ ' + monto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                tr.appendChild(tdMonto);

                // Saldo resultante
                const tdSaldo = document.createElement('td');
                tdSaldo.style.textAlign = 'right';
                tdSaldo.style.fontSize = '0.85rem';
                tdSaldo.textContent = saldoRes > 0 ? '$ ' + saldoRes.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
                tr.appendChild(tdSaldo);

                // Observaciones
                const tdObs = document.createElement('td');
                tdObs.style.fontSize = '0.8rem';
                tdObs.style.maxWidth = '180px';
                tdObs.style.overflow = 'hidden';
                tdObs.style.textOverflow = 'ellipsis';
                tdObs.textContent = m.observaciones || '-';
                tr.appendChild(tdObs);

                tbody.appendChild(tr);
            });

            document.getElementById('mov-total-entradas').textContent =
                'Entradas: $ ' + totalEntradas.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('mov-total-salidas').textContent =
                'Salidas: $ ' + totalSalidas.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            content.style.display = 'block';
        })
        .catch(function(err) {
            loading.style.display = 'none';
            errorDiv.textContent = 'Error al cargar movimientos: ' + err.message;
            errorDiv.style.display = 'block';
            content.style.display = 'block';
        });
}
</script>
