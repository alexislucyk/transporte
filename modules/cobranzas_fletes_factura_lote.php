<?php
/**
 * Cobranzas - Facturación en Lote de Fletes
 * 
 * Permite seleccionar un pagador de flete y facturar múltiples viajes
 * descargados en una misma factura (mismo número y fecha).
 * Soporta items adicionales por cada viaje.
 *
 * Multi-tenant: filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$mensaje = '';
$error   = '';

$selected_pagador_id = (int)($_GET['pagador_id'] ?? ($_POST['pagador_id'] ?? 0));

// ─── PROCESAR POST DE ITEMS ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $viaje_id_item = (int)($_POST['viaje_id'] ?? 0);

    // AGREGAR ITEM
    if ($_POST['action'] === 'agregar_item_lote' && $viaje_id_item > 0) {
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
                    ->execute([$viaje_id_item, $descripcion, $monto, $operacion]);
                $mensaje = "Item agregado exitosamente.";
                header("Location: cobranzas_fletes_factura_lote?pagador_id=" . $selected_pagador_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al agregar item: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR ITEM
    if ($_POST['action'] === 'eliminar_item_lote' && $viaje_id_item > 0) {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            try {
                $pdo->prepare("DELETE FROM viaje_factura_items WHERE id = ? AND viaje_id = ?")
                    ->execute([$item_id, $viaje_id_item]);
                header("Location: cobranzas_fletes_factura_lote?pagador_id=" . $selected_pagador_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al eliminar item: " . $e->getMessage();
            }
        }
    }
}

// ─── OBTENER PAGADORES CON VIAJES DESCARGADOS ──────────
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id, p.razon_social, p.cuit
    FROM viajes v
    JOIN clientes p ON p.id = v.pagador_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'descargado'
      AND v.pagador_id IS NOT NULL
    ORDER BY p.razon_social ASC
");
$stmt->execute([$active_company_id]);
$pagadores = $stmt->fetchAll();

// ─── OBTENER VIAJES DEL PAGADOR SELECCIONADO ──────────
$viajes_pagador = [];
$total_neto_sum = 0;
if ($selected_pagador_id > 0) {
    $stmt = $pdo->prepare("
        SELECT v.*,
               c.razon_social as cliente_nombre,
               CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
               ve.dominio as vehiculo_dominio
        FROM viajes v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN choferes ch ON ch.id = v.chofer_id
        LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
        WHERE v.transportista_id = ?
          AND v.activo = 1
          AND v.estado = 'descargado'
          AND v.pagador_id = ?
        ORDER BY v.fecha_carga DESC, v.id DESC
    ");
    $stmt->execute([$active_company_id, $selected_pagador_id]);
    $viajes_pagador = $stmt->fetchAll();

    // Obtener items adicionales de todos los viajes del pagador
    $viaje_ids = array_column($viajes_pagador, 'id');
    $items_por_viaje = [];
    if (!empty($viaje_ids)) {
        $placeholders = implode(',', array_fill(0, count($viaje_ids), '?'));
        $stmtItems = $pdo->prepare("SELECT * FROM viaje_factura_items WHERE viaje_id IN ($placeholders) ORDER BY viaje_id, id ASC");
        $stmtItems->execute($viaje_ids);
        $todos_items = $stmtItems->fetchAll();
        foreach ($todos_items as $it) {
            $items_por_viaje[$it['viaje_id']][] = $it;
        }
    }

    foreach ($viajes_pagador as $k => $v) {
        $total_neto_sum += (float)($v['total_flete_neto'] ?? 0);
        $viajes_pagador[$k]['_items'] = $items_por_viaje[$v['id']] ?? [];
    }
}

// ─── OBTENER DATOS DEL PAGADOR ─────────────────────────
$pagador_nombre = '';
$pagador_cuit = '';
if ($selected_pagador_id > 0) {
    $stmt = $pdo->prepare("SELECT razon_social, cuit FROM clientes WHERE id = ? AND transportista_id = ?");
    $stmt->execute([$selected_pagador_id, $active_company_id]);
    $pagador_data = $stmt->fetch();
    if ($pagador_data) {
        $pagador_nombre = $pagador_data['razon_social'];
        $pagador_cuit = $pagador_data['cuit'];
    }
}

// ─── PROCESAR FACTURACIÓN EN LOTE ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'facturar_lote') {
    $factura_nro   = trim($_POST['factura_nro'] ?? '');
    $factura_fecha = $_POST['factura_fecha'] ?? date('Y-m-d');
    $fecha_cobro_estimada = $_POST['fecha_cobro_estimada'] ?? '';
    $selected_ids  = $_POST['viaje_ids'] ?? [];

    if ($factura_nro === '') {
        $error = 'El número de factura es obligatorio.';
    } elseif (empty($selected_ids) || !is_array($selected_ids)) {
        $error = 'Debe seleccionar al menos un viaje para facturar.';
    } else {
        $ids_validos = [];
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $params = array_merge($selected_ids, [$active_company_id]);

        $stmt = $pdo->prepare("
            SELECT id, total_flete_neto FROM viajes
            WHERE id IN ($placeholders)
              AND transportista_id = ?
              AND activo = 1
              AND estado = 'descargado'
        ");
        $stmt->execute($params);
        $ids_validos = $stmt->fetchAll();

        if (count($ids_validos) !== count($selected_ids)) {
            $error = 'Algunos viajes seleccionados no son válidos o ya no están disponibles para facturar.';
        } else {
            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare("
                    UPDATE viajes
                    SET factura_nro = ?, factura_fecha = ?, fecha_cobro = ?, estado = 'facturado'
                    WHERE id = ? AND transportista_id = ? AND activo = 1 AND estado = 'descargado'
                ");

                $updated_count = 0;
                foreach ($ids_validos as $row) {
                    $updateStmt->execute([
                        $factura_nro, $factura_fecha, $fecha_cobro_estimada ?: null,
                        $row['id'], $active_company_id
                    ]);
                    $updated_count += $updateStmt->rowCount();
                }

                $pdo->commit();
                $mensaje = "Factura N° <strong>" . htmlspecialchars($factura_nro) . "</strong> emitida exitosamente para <strong>{$updated_count}</strong> viaje(s).";
                
                // Refrescar datos
                if ($selected_pagador_id > 0) {
                    $stmt = $pdo->prepare("
                        SELECT v.*, c.razon_social as cliente_nombre,
                               CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
                               ve.dominio as vehiculo_dominio
                        FROM viajes v
                        LEFT JOIN clientes c ON c.id = v.cliente_id
                        LEFT JOIN choferes ch ON ch.id = v.chofer_id
                        LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
                        WHERE v.transportista_id = ? AND v.activo = 1 AND v.estado = 'descargado' AND v.pagador_id = ?
                        ORDER BY v.fecha_carga DESC, v.id DESC
                    ");
                    $stmt->execute([$active_company_id, $selected_pagador_id]);
                    $viajes_pagador = $stmt->fetchAll();
                    $total_neto_sum = 0;
                    foreach ($viajes_pagador as $v) {
                        $total_neto_sum += (float)($v['total_flete_neto'] ?? 0);
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error al facturar en lote: " . $e->getMessage();
            }
        }
    }
}
?>
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #9b59b6, #8e44ad, #e67e22); position:absolute; top:0; left:0; right:0;"></div>
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-layer-group" style="color:#9b59b6; margin-right:8px;"></i>
                Facturación en Lote
            </h2>
            <div style="margin-top:4px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Agrupe múltiples viajes del mismo pagador en una sola factura
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn-secondary" href="cobranzas" style="text-decoration:none; padding:10px 14px;">
                <i class="fas fa-arrow-left"></i> Volver a Cobranzas
            </a>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success" style="padding:14px 18px; margin-bottom:18px; border-left:5px solid #27ae60; background:#eafaf1; border-radius:6px;">
    <i class="fas fa-check-circle" style="color:#27ae60; margin-right:6px;"></i> <?= $mensaje ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error" style="padding:14px 18px; margin-bottom:18px; border-left:5px solid #e74c3c; background:#fdedec; border-radius:6px;">
    <i class="fas fa-exclamation-triangle" style="color:#e74c3c; margin-right:6px;"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ─── SELECCIÓN DE PAGADOR ─────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0;"><i class="fas fa-user-tie"></i> Seleccionar Pagador de Flete</h3>
    <form method="GET" action="cobranzas_fletes_factura_lote" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="flex:1; min-width:250px; margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="fas fa-building"></i> Pagador</label>
            <select name="pagador_id" class="input-field" required style="width:100%;" onchange="this.form.submit()">
                <option value="">-- Seleccione un pagador --</option>
                <?php foreach ($pagadores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selected_pagador_id === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['razon_social']) ?> (CUIT: <?= htmlspecialchars($p['cuit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($selected_pagador_id > 0): ?>
        <div style="padding:8px 16px; background:#f3e5f5; border-radius:8px; border:1px solid #ce93d8;">
            <div style="font-size:0.75rem; color:#666;">Viajes disponibles</div>
            <div style="font-weight:bold; font-size:1.2rem; color:#9b59b6;"><?= count($viajes_pagador) ?></div>
        </div>
        <div style="padding:8px 16px; background:#e8f5e9; border-radius:8px; border:1px solid #a5d6a7;">
            <div style="font-size:0.75rem; color:#666;">Total Neto</div>
            <div style="font-weight:bold; font-size:1.2rem; color:#27ae60;">$ <?= number_format($total_neto_sum, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($selected_pagador_id > 0 && !empty($viajes_pagador)): ?>
<!-- ─── FORMULARIO DE FACTURACIÓN ────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-file-invoice"></i> Datos de la Factura
        <span style="font-size:0.85rem; font-weight:normal; opacity:0.7; margin-left:auto;">
            Pagador: <strong><?= htmlspecialchars($pagador_nombre) ?></strong>
            <?php if ($pagador_cuit): ?>| CUIT: <?= htmlspecialchars($pagador_cuit) ?><?php endif; ?>
        </span>
    </h3>

    <form method="POST" action="cobranzas_fletes_factura_lote?pagador_id=<?= $selected_pagador_id ?>" id="loteForm">
        <input type="hidden" name="action" value="facturar_lote">
        <input type="hidden" name="pagador_id" value="<?= $selected_pagador_id ?>">

        <div style="display:grid; grid-template-columns: 2fr 1.5fr 1.5fr; gap:12px; margin-bottom:16px; align-items:end;">
            <div class="form-group" style="margin:0;">
                <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="fas fa-hashtag"></i> N° Factura *</label>
                <input type="text" name="factura_nro" class="input-field" required placeholder="A 00001-00000001" style="width:100%;">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-alt"></i> Fecha Emisión</label>
                <input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>" style="width:100%;">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;"><i class="far fa-calendar-check"></i> Posible Fecha de Cobro</label>
                <input type="date" name="fecha_cobro_estimada" class="input-field" style="width:100%;" placeholder="Estimación de cobro">
            </div>
        </div>

        <!-- ─── TABLA DE VIAJES ─────────────────────── -->
        <div class="table-container" style="margin-bottom:16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width:18px; height:18px; cursor:pointer;">
                        </th>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Origen → Destino</th>
                        <th>Patente</th>
                        <th style="text-align:right;">TN Desc.</th>
                        <th style="text-align:right;">Neto</th>
                        <th style="text-align:center;">Items</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_pagador as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $monto = (float)($v['total_flete_neto'] ?? 0);
                    $tn_desc = (float)($v['peso_neto'] ?? 0);
                    $items_count = count($v['_items'] ?? []);
                    $items_total = 0;
                    foreach ($v['_items'] ?? [] as $it) {
                        $items_total += ($it['operacion'] === 'suma' ? 1 : -1) * (float)$it['monto'];
                    }
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" name="viaje_ids[]" value="<?= (int)$v['id'] ?>"
                                   class="viaje-checkbox" style="width:18px; height:18px; cursor:pointer;"
                                   data-monto="<?= $monto ?>" data-items="<?= $items_total ?>"
                                   onchange="updateTotals()">
                        </td>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td style="font-size:0.9rem;">
                            <?= htmlspecialchars($v['origen'] ?? '-') ?>
                            <i class="fas fa-arrow-right" style="color:#999; font-size:0.7rem; margin:0 4px;"></i>
                            <?= htmlspecialchars($v['destino'] ?? '-') ?>
                        </td>
                        <td><?= htmlspecialchars($v['vehiculo_dominio'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= number_format($tn_desc, 2, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">$ <?= number_format($monto, 2, ',', '.') ?></td>
                        <td style="text-align:center;">
                            <button type="button" class="btn-primary btn-sm" style="background:#9b59b6; border:none; padding:4px 10px; font-size:0.8rem;"
                                    onclick="openItemsModal(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($label, ENT_QUOTES) ?>')">
                                <i class="fas fa-list"></i> <?= $items_count ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold; background:#f8f9fa;">
                        <td></td>
                        <td colspan="6" style="text-align:right;">Totales Neto:</td>
                        <td style="text-align:right; color:#27ae60;" id="totalNetoDisplay">$ <?= number_format($total_neto_sum, 2, ',', '.') ?></td>
                    </tr>
                    <tr style="font-weight:bold; background:#f3e5f5;">
                        <td></td>
                        <td colspan="6" style="text-align:right; color:#9b59b6;">
                            <span id="selectedCount">0</span> viaje(s) seleccionado(s)
                        </td>
                        <td style="text-align:right; color:#9b59b6;" id="selectedTotalDisplay">$ 0,00</td>
                    </tr>
                    <tr style="font-weight:bold; background:#fff3e0;">
                        <td></td>
                        <td colspan="6" style="text-align:right; color:#e67e22;">IVA 21% (sobre seleccionados)</td>
                        <td style="text-align:right; color:#e67e22;" id="ivaDisplay">$ 0,00</td>
                    </tr>
                    <tr style="font-weight:bold; background:#e8f5e9; font-size:1.1rem;">
                        <td></td>
                        <td colspan="6" style="text-align:right; color:#27ae60;">Total Factura (seleccionados)</td>
                        <td style="text-align:right; color:#27ae60; font-size:1.2rem;" id="totalFinalDisplay">$ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #eee; padding-top:14px;">
            <a class="btn-secondary" href="cobranzas" style="padding:10px 18px; text-decoration:none;"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn-primary" style="background:#9b59b6; border:none; padding:10px 18px;" onclick="return validateSelection()">
                <i class="fas fa-check"></i> Confirmar Facturación en Lote
            </button>
        </div>
    </form>
</div>

<!-- ─── MODAL DE ITEMS POR VIAJE ────────────────────── -->
<div id="modal-items-lote" class="modal">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3 style="margin:0;"><i class="fas fa-list"></i> Items Adicionales - <span id="modalItemsTitle"></span></h3>
            <span class="close-modal" onclick="closeModal('modal-items-lote')">&times;</span>
        </div>
        <div class="modal-body" id="modalItemsBody">
            <!-- Se carga dinámicamente -->
        </div>
    </div>
</div>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.viaje-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateTotals();
}

function updateTotals() {
    const checkboxes = document.querySelectorAll('.viaje-checkbox:checked');
    let selectedTotal = 0;
    let count = 0;

    checkboxes.forEach(cb => {
        selectedTotal += parseFloat(cb.dataset.monto || 0);
        count++;
    });

    const iva = selectedTotal * 0.21;
    const totalFinal = selectedTotal + iva;

    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedTotalDisplay').textContent = '$ ' + formatNumber(selectedTotal);
    document.getElementById('ivaDisplay').textContent = '$ ' + formatNumber(iva);
    document.getElementById('totalFinalDisplay').textContent = '$ ' + formatNumber(totalFinal);
}

function formatNumber(num) {
    return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function validateSelection() {
    const checkboxes = document.querySelectorAll('.viaje-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Debe seleccionar al menos un viaje para facturar.');
        return false;
    }
    return confirm('¿Está seguro de facturar ' + checkboxes.length + ' viaje(s) con los datos ingresados?');
}

function openItemsModal(viajeId, label) {
    document.getElementById('modalItemsTitle').textContent = label;
    const body = document.getElementById('modalItemsBody');
    const pagadorId = <?= $selected_pagador_id ?>;
    
    // Cargar items via AJAX
    fetch('ajax_get_viaje_items.php?viaje_id=' + viajeId + '&pagador_id=' + pagadorId)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;
            openModal('modal-items-lote');
        })
        .catch(() => {
            body.innerHTML = '<p style="text-align:center;padding:20px;color:#e74c3c;">Error al cargar items.</p>';
            openModal('modal-items-lote');
        });
}
</script>

<?php elseif ($selected_pagador_id > 0 && empty($viajes_pagador)): ?>
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-check-circle" style="color:#27ae60; font-size:3rem; display:block; margin-bottom:12px;"></i>
    <h3 style="margin:0 0 8px 0;">No hay viajes pendientes</h3>
    <p style="opacity:0.7; margin:0;">El pagador <strong><?= htmlspecialchars($pagador_nombre) ?></strong> no tiene viajes descargados pendientes de facturar.</p>
    <a href="cobranzas_fletes_factura_lote" class="btn-primary" style="margin-top:16px; display:inline-block; text-decoration:none;"><i class="fas fa-redo"></i> Seleccionar otro pagador</a>
</div>
<?php elseif (empty($pagadores)): ?>
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-info-circle" style="color:#3498db; font-size:3rem; display:block; margin-bottom:12px;"></i>
    <h3 style="margin:0 0 8px 0;">Sin viajes disponibles</h3>
    <p style="opacity:0.7; margin:0;">No hay viajes descargados con pagador de flete asignado para facturar en lote.</p>
    <a href="cobranzas" class="btn-primary" style="margin-top:16px; display:inline-block; text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver a Cobranzas</a>
</div>
<?php endif; ?>
</file_content>
</write_to_file>