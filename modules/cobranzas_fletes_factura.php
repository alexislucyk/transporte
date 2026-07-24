<?php
/**
 * Cobranzas - Facturación de Flete
 * 
 * Pantalla para facturar un viaje descargado.
 * Muestra:
 *   - Cliente, carga transportada, origen-destino
 *   - Tarifa, TN descargadas
 *   - Total a facturar (neto)
 *   - Items adicionales (conceptos extras escritos manualmente)
 *   - IVA 21%, monto del IVA
 *   - Total final de la factura
 *   - Fecha de emisión y posible fecha de cobro
 *
 * Al confirmar, cambia estado a 'facturado' y guarda factura_nro + factura_fecha.
 *
 * Multi-tenant: filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$mensaje = '';
$error   = '';

$viaje_id = (int)($_GET['viaje_id'] ?? ($_POST['viaje_id'] ?? 0));

// ─── HELPERS ──────────────────────────────────────────────
function viajeOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

// ─── VALIDAR VIAJE ────────────────────────────────────────
if ($viaje_id <= 0 || !viajeOwner($pdo, $viaje_id, $active_company_id, $currentRole)) {
    echo '<div class="card" style="text-align:center; padding:60px 20px;">';
    echo '<i class="fas fa-exclamation-triangle fa-4x" style="color:#e74c3c; margin-bottom:20px;"></i>';
    echo '<h2>Viaje no encontrado</h2>';
    echo '<p style="opacity:0.7;">El viaje solicitado no existe o no pertenece a la empresa activa.</p>';
    echo '<a href="cobranzas" class="btn-primary" style="margin-top:20px; display:inline-block;"><i class="fas fa-arrow-left"></i> Volver a Cobranzas</a>';
    echo '</div>';
    return;
}

// ─── OBTENER DATOS DEL VIAJE ───────────────────────────
$stmtViaje = $pdo->prepare("
    SELECT v.*,
           c.razon_social as cliente_nombre,
           c.cuit as cliente_cuit,
           c.direccion as cliente_direccion,
           CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
           ve.dominio as vehiculo_dominio,
           p.razon_social as pagador_nombre
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN choferes ch ON ch.id = v.chofer_id
    LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
    LEFT JOIN clientes p ON p.id = v.pagador_id
    WHERE v.id = ? AND v.transportista_id = ? AND v.activo = 1
");
$stmtViaje->execute([$viaje_id, $active_company_id]);
$viaje = $stmtViaje->fetch();

if (!$viaje) {
    echo '<div class="card" style="text-align:center; padding:60px 20px;">';
    echo '<h2>Viaje no encontrado</h2>';
    echo '<a href="cobranzas" class="btn-primary"><i class="fas fa-arrow-left"></i> Volver</a>';
    echo '</div>';
    return;
}

// Determinar estado y permisos
$estado = $viaje['estado'];
$ya_facturado = ($estado === 'facturado' || $estado === 'cobrado' || $estado === 'liquidado');
$puede_facturar = ($estado === 'descargado');
$puede_editar_factura = ($estado === 'facturado');

// ─── OBTENER ITEMS ADICIONALES DE LA FACTURA ──────────
$stmtItems = $pdo->prepare("SELECT * FROM viaje_factura_items WHERE viaje_id = ? ORDER BY id ASC");
$stmtItems->execute([$viaje_id]);
$factura_items = $stmtItems->fetchAll();

$total_items_suma = 0;
$total_items_resta = 0;
foreach ($factura_items as $item) {
    if ($item['operacion'] === 'suma') {
        $total_items_suma += (float)$item['monto'];
    } else {
        $total_items_resta += (float)$item['monto'];
    }
}

// ─── CÁLCULOS DE FACTURA ─────────────────────────────
$total_neto      = (float)($viaje['total_flete_neto'] ?? 0);
$tarifa          = (float)($viaje['tarifa_tonelada'] ?? 0);
$tn_descargadas  = (float)($viaje['peso_neto'] ?? 0);
$iva_porcentaje  = 21;
$subtotal_con_items = $total_neto + $total_items_suma - $total_items_resta;
$monto_iva       = $subtotal_con_items * ($iva_porcentaje / 100);
$total_final     = $subtotal_con_items + $monto_iva;

// ─── PROCESAR POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // AGREGAR ITEM
    if (isset($_POST['action']) && $_POST['action'] === 'agregar_item' && !$ya_facturado) {
        $descripcion = trim($_POST['item_descripcion'] ?? '');
        $monto = (float)($_POST['item_monto'] ?? 0);
        $operacion = $_POST['item_operacion'] ?? 'suma';

        if ($descripcion === '') {
            $error = 'La descripción del item es obligatoria.';
        } elseif ($monto <= 0) {
            $error = 'El monto debe ser mayor a cero.';
        } elseif (!in_array($operacion, ['suma', 'resta'])) {
            $error = 'Operación inválida.';
        } else {
            try {
                $pdo->prepare("INSERT INTO viaje_factura_items (viaje_id, descripcion, monto, operacion) VALUES (?, ?, ?, ?)")
                    ->execute([$viaje_id, $descripcion, $monto, $operacion]);
                $mensaje = "Item agregado exitosamente.";
                header("Location: cobranzas_fletes_factura?viaje_id=" . $viaje_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al agregar item: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR ITEM
    if (isset($_POST['action']) && $_POST['action'] === 'eliminar_item' && !$ya_facturado) {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            try {
                $pdo->prepare("DELETE FROM viaje_factura_items WHERE id = ? AND viaje_id = ?")
                    ->execute([$item_id, $viaje_id]);
                header("Location: cobranzas_fletes_factura?viaje_id=" . $viaje_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al eliminar item: " . $e->getMessage();
            }
        }
    }

    // FACTURAR / ACTUALIZAR FACTURA
    $factura_nro   = trim($_POST['factura_nro'] ?? '');
    $factura_fecha = $_POST['factura_fecha'] ?? date('Y-m-d');
    $fecha_cobro_estimada = $_POST['fecha_cobro_estimada'] ?? '';

    if ($factura_nro === '') {
        $error = 'El número de factura es obligatorio.';
    } else {
        try {
            if ($puede_facturar) {
                $pdo->prepare("
                    UPDATE viajes
                    SET factura_nro = ?, factura_fecha = ?, fecha_cobro = ?, estado = 'facturado'
                    WHERE id = ? AND transportista_id = ? AND activo = 1 AND estado = 'descargado'
                ")->execute([$factura_nro, $factura_fecha, $fecha_cobro_estimada ?: null, $viaje_id, $active_company_id]);
                $mensaje = "Viaje facturado exitosamente. Factura N°: " . htmlspecialchars($factura_nro);
            } elseif ($puede_editar_factura) {
                $pdo->prepare("
                    UPDATE viajes
                    SET factura_nro = ?, factura_fecha = ?, fecha_cobro = ?
                    WHERE id = ? AND transportista_id = ? AND activo = 1 AND estado = 'facturado'
                ")->execute([$factura_nro, $factura_fecha, $fecha_cobro_estimada ?: null, $viaje_id, $active_company_id]);
                $mensaje = "Factura actualizada exitosamente. Factura N°: " . htmlspecialchars($factura_nro);
            }

            $stmtViaje->execute([$viaje_id, $active_company_id]);
            $viaje = $stmtViaje->fetch();
            $estado = $viaje['estado'];
            $ya_facturado = true;
            $puede_facturar = false;
            $puede_editar_factura = false;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Label del viaje
$viaje_label = '';
if (!empty($viaje['ctg_nro'])) {
    $viaje_label = 'CTG ' . htmlspecialchars($viaje['ctg_nro']);
} elseif (!empty($viaje['carta_porte_nro'])) {
    $viaje_label = 'CP ' . htmlspecialchars($viaje['carta_porte_nro']);
} elseif (!empty($viaje['otros_docs'])) {
    $viaje_label = htmlspecialchars($viaje['otros_docs']);
} else {
    $viaje_label = 'Viaje #' . (int)$viaje['id'];
}
?>
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #9b59b6, #8e44ad, #3498db); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-file-invoice" style="color:#9b59b6; margin-right:8px;"></i>
                Facturación de Flete
            </h2>
            <div style="margin-top:4px;">
                <span style="font-size:1.05rem; font-weight:600;"><?= $viaje_label ?></span>
                <?php if ($ya_facturado): ?>
                    <span class="badge" style="background:#9b59b6; color:#fff; font-size:0.85rem; padding:4px 10px; margin-left:8px;">
                        <i class="fas fa-check-circle"></i> Facturado
                    </span>
                <?php else: ?>
                    <span class="badge" style="background:#e67e22; color:#fff; font-size:0.85rem; padding:4px 10px; margin-left:8px;">
                        <i class="fas fa-hourglass"></i> Pendiente
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn-secondary" href="cobranzas_fletes_pendientes" style="text-decoration:none; padding:10px 14px;">
                <i class="fas fa-arrow-left"></i> Volver a Pendientes
            </a>
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

<div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:20px; align-items:start;">
    <!-- ─── COLUMNA IZQUIERDA: DATOS DEL VIAJE + ITEMS ── -->
    <div>
        <div class="card" style="margin-bottom:20px;">
            <h3 style="margin-top:0; border-bottom:2px solid #9b59b6; padding-bottom:10px;">
                <i class="fas fa-info-circle"></i> Datos del Viaje
            </h3>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <div style="font-size:0.75rem; color:#666; text-transform:uppercase; letter-spacing:0.5px;">Cliente</div>
                    <div style="font-weight:bold; font-size:1rem;"><?= htmlspecialchars($viaje['cliente_nombre'] ?? '-') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#666; text-transform:uppercase; letter-spacing:0.5px;">CUIT</div>
                    <div style="font-weight:bold;"><?= htmlspecialchars($viaje['cliente_cuit'] ?? '-') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#666; text-transform:uppercase; letter-spacing:0.5px;">Producto</div>
                    <div style="font-weight:bold;"><?= htmlspecialchars($viaje['producto'] ?? '-') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#666; text-transform:uppercase; letter-spacing:0.5px;">Pagador</div>
                    <div style="font-weight:bold;"><?= htmlspecialchars($viaje['pagador_nombre'] ?? '-') ?></div>
                </div>
            </div>

            <div style="background:#f8f9fa; border-radius:8px; padding:12px; border:1px dashed #ccc; margin-bottom:14px;">
                <div style="display:flex; align-items:center; gap:8px; justify-content:center;">
                    <div style="text-align:center; flex:1;">
                        <div style="font-size:0.7rem; color:#666; text-transform:uppercase;">Origen</div>
                        <div style="font-weight:bold; color:#2e7d32;"><?= htmlspecialchars($viaje['origen']) ?></div>
                    </div>
                    <div style="color:#bbb; font-size:1.2rem;"><i class="fas fa-arrow-right"></i></div>
                    <div style="text-align:center; flex:1;">
                        <div style="font-size:0.7rem; color:#666; text-transform:uppercase;">Destino</div>
                        <div style="font-weight:bold; color:#c62828;"><?= htmlspecialchars($viaje['destino']) ?></div>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div style="background:#e8f5e9; border-radius:8px; padding:10px; text-align:center; border:1px solid #c8e6c9;">
                    <div style="font-size:0.7rem; color:#666; text-transform:uppercase;">Tarifa x TN</div>
                    <div style="font-weight:bold; font-size:1.1rem; color:#2e7d32;">$ <?= number_format($tarifa, 2, ',', '.') ?></div>
                </div>
                <div style="background:#e3f2fd; border-radius:8px; padding:10px; text-align:center; border:1px solid #bbdefb;">
                    <div style="font-size:0.7rem; color:#666; text-transform:uppercase;">TN Descargadas</div>
                    <div style="font-weight:bold; font-size:1.1rem; color:#1565c0;"><?= number_format($tn_descargadas, 2, ',', '.') ?></div>
                </div>
            </div>

            <div style="background:#fff3e0; border-radius:8px; padding:10px; text-align:center; border:1px solid #ffe0b2;">
                <div style="font-size:0.7rem; color:#666; text-transform:uppercase;">Patente / Chofer</div>
                <div style="font-weight:bold;">
                    <?= htmlspecialchars($viaje['vehiculo_dominio'] ?? '-') ?>
                    <span style="opacity:0.5;">|</span>
                    <?= htmlspecialchars($viaje['chofer_nombre'] ?? '-') ?>
                </div>
            </div>
        </div>

        <!-- ─── ITEMS ADICIONALES DE FACTURA ─────────── -->
        <?php if (!$ya_facturado): ?>
        <div class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
                <h3 style="margin:0;">
                    <i class="fas fa-list"></i> Items Adicionales
                    <span style="font-size:0.8rem; font-weight:normal; opacity:0.6; margin-left:8px;">
                        Conceptos extras que suman o restan al total
                    </span>
                </h3>
                <span style="background:#f0f0f0; padding:4px 12px; border-radius:20px; font-size:0.8rem; opacity:0.7;">
                    <?= count($factura_items) ?> item(s)
                </span>
            </div>

            <form method="POST" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px; padding:14px; background:#f8f9fa; border-radius:8px; border:1px solid #e0e0e0;">
                <input type="hidden" name="action" value="agregar_item">
                <input type="hidden" name="viaje_id" value="<?= (int)$viaje['id'] ?>">
                <div class="form-group" style="flex:2; min-width:200px; margin:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.85rem;"><i class="fas fa-pen"></i> Descripción</label>
                    <input type="text" name="item_descripcion" class="input-field" required placeholder="Ej: Flete adicional, Descuento..." style="width:100%;">
                </div>
                <div class="form-group" style="flex:1; min-width:120px; margin:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.85rem;"><i class="fas fa-dollar-sign"></i> Monto</label>
                    <input type="number" step="0.01" min="0.01" name="item_monto" class="input-field" required placeholder="0.00" style="width:100%;">
                </div>
                <div class="form-group" style="flex:0 0 120px; margin:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.85rem;"><i class="fas fa-arrows-alt-v"></i> Operación</label>
                    <select name="item_operacion" class="input-field" style="width:100%;">
                        <option value="suma">➕ Sumar</option>
                        <option value="resta">➖ Restar</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="height:38px; white-space:nowrap; background:#9b59b6; border:none;">
                    <i class="fas fa-plus"></i> Agregar Item
                </button>
            </form>

            <?php if (empty($factura_items)): ?>
                <p style="text-align:center; padding:20px; opacity:0.5;">No hay items adicionales. Agregue conceptos extras a la factura.</p>
            <?php else: ?>
                <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th style="text-align:right;">Monto</th>
                            <th style="text-align:center;">Operación</th>
                            <th style="text-align:center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal_items = 0;
                        foreach ($factura_items as $item): 
                            $monto_item = (float)$item['monto'];
                            if ($item['operacion'] === 'suma') {
                                $subtotal_items += $monto_item;
                            } else {
                                $subtotal_items -= $monto_item;
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['descripcion']) ?></td>
                            <td style="text-align:right; font-weight:bold;">$ <?= number_format($monto_item, 2, ',', '.') ?></td>
                            <td style="text-align:center;">
                                <?php if ($item['operacion'] === 'suma'): ?>
                                    <span class="badge" style="background:#27ae60; color:#fff;">➕ Suma</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#e74c3c; color:#fff;">➖ Resta</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <form method="POST" style="display:inline;" onsubmit="return appConfirm('¿Eliminar este item?', function(){ this.submit(); }.bind(this), 'Eliminar Item')">
                                    <input type="hidden" name="action" value="eliminar_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="viaje_id" value="<?= (int)$viaje['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#e74c3c; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#f8f9fa;">
                            <td style="text-align:right;">Subtotal Items:</td>
                            <td style="text-align:right; <?= $subtotal_items >= 0 ? 'color:#27ae60;' : 'color:#e74c3c;' ?>">
                                $ <?= number_format($subtotal_items, 2, ',', '.') ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── COLUMNA DERECHA: FACTURA ────────────────── -->
    <div class="card" style="margin-bottom:0;">
        <h3 style="margin-top:0; border-bottom:2px solid #27ae60; padding-bottom:10px;">
            <i class="fas fa-file-invoice-dollar"></i> 
            <?= $ya_facturado ? 'Factura Emitida' : 'Nueva Factura' ?>
        </h3>

        <!-- Resumen de importes -->
        <div style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee;">
                <span>Total Neto (Flete):</span>
                <span style="font-weight:bold;">$ <?= number_format($total_neto, 2, ',', '.') ?></span>
            </div>
            <?php if ($total_items_suma > 0 || $total_items_resta > 0): ?>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; color:#9b59b6;">
                <span>Items Adicionales:</span>
                <span style="font-weight:bold;">
                    <?php if ($total_items_suma > 0): ?>+$ <?= number_format($total_items_suma, 2, ',', '.') ?><?php endif; ?>
                    <?php if ($total_items_resta > 0): ?> / -$ <?= number_format($total_items_resta, 2, ',', '.') ?><?php endif; ?>
                </span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee;">
                <span>Subtotal con Items:</span>
                <span style="font-weight:bold;">$ <?= number_format($subtotal_con_items, 2, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; color:#e67e22;">
                <span>IVA <?= $iva_porcentaje ?>%:</span>
                <span style="font-weight:bold;">$ <?= number_format($monto_iva, 2, ',', '.') ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:12px 0; font-size:1.2rem; border-bottom:2px solid #27ae60;">
                <span style="font-weight:bold;">Total Final:</span>
                <span style="font-weight:bold; color:#27ae60;">$ <?= number_format($total_final, 2, ',', '.') ?></span>
            </div>
        </div>

        <?php if ($ya_facturado && !$puede_editar_factura): ?>
            <div style="background:#f3e5f5; border-radius:8px; padding:14px; border:1px solid #ce93d8;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div>
                        <div style="font-size:0.75rem; color:#666;">Factura N°</div>
                        <div style="font-weight:bold; font-size:1.05rem;"><?= htmlspecialchars($viaje['factura_nro'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#666;">Fecha Emisión</div>
                        <div style="font-weight:bold;"><?= htmlspecialchars(formatDate($viaje['factura_fecha'] ?? '')) ?></div>
                    </div>
                    <?php if (!empty($viaje['fecha_cobro'])): ?>
                    <div>
                        <div style="font-size:0.75rem; color:#666;">Fecha Cobro Est.</div>
                        <div style="font-weight:bold;"><?= htmlspecialchars(formatDate($viaje['fecha_cobro'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top:16px; text-align:center;">
                <a class="btn-primary" href="cobranzas" style="background:#9b59b6; border:none; padding:10px 20px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-wallet"></i> Ir a Cobranzas
                </a>
            </div>

        <?php elseif ($puede_editar_factura): ?>
            <form method="POST">
                <input type="hidden" name="viaje_id" value="<?= (int)$viaje['id'] ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="fas fa-hashtag"></i> N° Factura *</label>
                        <input type="text" name="factura_nro" class="input-field" required value="<?= htmlspecialchars($viaje['factura_nro'] ?? '') ?>" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-alt"></i> Fecha Emisión</label>
                        <input type="date" name="factura_fecha" class="input-field" value="<?= htmlspecialchars($viaje['factura_fecha'] ?? date('Y-m-d')) ?>" style="width:100%;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-check"></i> Posible Fecha de Cobro</label>
                    <input type="date" name="fecha_cobro_estimada" class="input-field" style="width:100%;" value="<?= htmlspecialchars($viaje['fecha_cobro'] ?? '') ?>">
                </div>
                <div style="background:#fff3e0; border-radius:8px; padding:14px; border:1px solid #ffe0b2; margin-bottom:16px;">
                    <div style="font-size:0.85rem; color:#e67e22; margin-bottom:8px;"><i class="fas fa-edit"></i> <strong>Modo Edición</strong></div>
                    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                        <tr><td style="padding:4px 0;">Neto:</td><td style="text-align:right; font-weight:bold;">$ <?= number_format($total_neto, 2, ',', '.') ?></td></tr>
                        <tr style="color:#e67e22;"><td style="padding:4px 0;">IVA <?= $iva_porcentaje ?>%:</td><td style="text-align:right; font-weight:bold;">$ <?= number_format($monto_iva, 2, ',', '.') ?></td></tr>
                        <tr style="border-top:2px solid #27ae60; font-size:1.1rem;"><td style="padding:6px 0; font-weight:bold;">Total Factura:</td><td style="text-align:right; font-weight:bold; color:#27ae60;">$ <?= number_format($total_final, 2, ',', '.') ?></td></tr>
                    </table>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #eee; padding-top:14px;">
                    <a class="btn-secondary" href="cobranzas" style="padding:10px 18px; text-decoration:none;"><i class="fas fa-times"></i> Cancelar</a>
                    <button type="submit" class="btn-primary" style="background:#e67e22; border:none; padding:10px 18px;"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>

        <?php elseif ($puede_facturar): ?>
            <form method="POST">
                <input type="hidden" name="viaje_id" value="<?= (int)$viaje['id'] ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="fas fa-hashtag"></i> N° Factura *</label>
                        <input type="text" name="factura_nro" class="input-field" required placeholder="A 00001-00000001" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-alt"></i> Fecha Emisión</label>
                        <input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" style="width:100%;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-check"></i> Posible Fecha de Cobro</label>
                    <input type="date" name="fecha_cobro_estimada" class="input-field" style="width:100%;" placeholder="Estimación de cuándo se cobraría">
                    <div style="font-size:0.75rem; color:#888; margin-top:3px;"><i class="fas fa-info-circle"></i> Opcional. Fecha estimada de cobro.</div>
                </div>
                <div style="background:#e8f5e9; border-radius:8px; padding:14px; border:1px solid #a5d6a7; margin-bottom:16px;">
                    <div style="font-size:0.85rem; color:#2e7d32; margin-bottom:8px;"><i class="fas fa-calculator"></i> <strong>Resumen de Factura</strong></div>
                    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                        <tr><td style="padding:4px 0;">Neto:</td><td style="text-align:right; font-weight:bold;">$ <?= number_format($total_neto, 2, ',', '.') ?></td></tr>
                        <?php if ($total_items_suma > 0 || $total_items_resta > 0): ?>
                        <tr><td style="padding:4px 0;">Items:</td><td style="text-align:right; font-weight:bold; color:#9b59b6;">$ <?= number_format($total_items_suma - $total_items_resta, 2, ',', '.') ?></td></tr>
                        <?php endif; ?>
                        <tr style="color:#e67e22;"><td style="padding:4px 0;">IVA <?= $iva_porcentaje ?>%:</td><td style="text-align:right; font-weight:bold;">$ <?= number_format($monto_iva, 2, ',', '.') ?></td></tr>
                        <tr style="border-top:2px solid #27ae60; font-size:1.1rem;"><td style="padding:6px 0; font-weight:bold;">Total Factura:</td><td style="text-align:right; font-weight:bold; color:#27ae60;">$ <?= number_format($total_final, 2, ',', '.') ?></td></tr>
                    </table>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #eee; padding-top:14px;">
                    <a class="btn-secondary" href="cobranzas_fletes_pendientes" style="padding:10px 18px; text-decoration:none;"><i class="fas fa-times"></i> Cancelar</a>
                    <button type="submit" class="btn-primary" style="background:#9b59b6; border:none; padding:10px 18px;"><i class="fas fa-check"></i> Confirmar Factura</button>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align:center; padding:20px; background:#fdedec; border-radius:8px; border:1px solid #e74c3c;">
                <i class="fas fa-exclamation-circle fa-2x" style="color:#e74c3c; margin-bottom:8px; display:block;"></i>
                <p style="margin:0; font-weight:bold;">Este viaje no puede ser facturado desde aquí.</p>
                <p style="margin:4px 0 0 0; opacity:0.7;">Estado actual: <strong><?= htmlspecialchars($estado) ?></strong></p>
                <a href="cobranzas" class="btn-primary" style="margin-top:12px; display:inline-block; text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver a Cobranzas</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</file_content>
</file_content>
</write_to_file>