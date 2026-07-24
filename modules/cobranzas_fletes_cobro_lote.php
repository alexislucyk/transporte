<?php
/**
 * Cobranzas - Cobro en Lote de Fletes
 * 
 * Permite seleccionar un pagador de flete y cobrar múltiples viajes
 * facturados en una misma operación (mismo número de recibo y fecha).
 *
 * Multi-tenant: filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$mensaje = '';
$error   = '';

$selected_pagador_id = (int)($_GET['pagador_id'] ?? ($_POST['pagador_id'] ?? 0));

// ─── OBTENER PAGADORES CON VIAJES FACTURADOS ──────────
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id, p.razon_social, p.cuit
    FROM viajes v
    JOIN clientes p ON p.id = v.pagador_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'facturado'
      AND v.pagador_id IS NOT NULL
    ORDER BY p.razon_social ASC
");
$stmt->execute([$active_company_id]);
$pagadores = $stmt->fetchAll();

// ─── OBTENER VIAJES DEL PAGADOR SELECCIONADO ──────────
$viajes_pagador = [];
$total_neto_sum = 0;
$total_fact_sum = 0;
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
          AND v.estado = 'facturado'
          AND v.pagador_id = ?
        ORDER BY v.fecha_carga DESC, v.id DESC
    ");
    $stmt->execute([$active_company_id, $selected_pagador_id]);
    $viajes_pagador = $stmt->fetchAll();

    foreach ($viajes_pagador as $v) {
        $neto = (float)($v['total_flete_neto'] ?? 0);
        $total_neto_sum += $neto;
        $total_fact_sum += $neto * 1.21; // IVA 21% incluido
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

// ─── OBTENER CUENTAS ACTIVAS ─────────────────────────
$stmt = $pdo->prepare("
    SELECT id, nombre, tipo, banco, saldo_actual
    FROM cuentas_empresa
    WHERE transportista_id = ? AND activo = 1
    ORDER BY tipo ASC, nombre ASC
");
$stmt->execute([$active_company_id]);
$cuentas = $stmt->fetchAll();

// ─── VARIABLES PARA MODAL ─────────────────────────────
$showModalLote = false;
$modalLoteData = [
    'selected_ids' => [],
    'recibo_nro' => '',
    'fecha_cobro' => date('Y-m-d'),
    'total_neto' => 0,
    'total_facturado' => 0,
    'count' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview_lote') {
    $showModalLote = true;
    $modalLoteData['selected_ids'] = $_POST['viaje_ids'] ?? [];
    $modalLoteData['recibo_nro'] = trim($_POST['recibo_nro'] ?? '');
    $modalLoteData['fecha_cobro'] = $_POST['fecha_cobro'] ?? date('Y-m-d');
    
    if (!empty($modalLoteData['selected_ids'])) {
        $placeholders = implode(',', array_fill(0, count($modalLoteData['selected_ids']), '?'));
        $params = array_merge($modalLoteData['selected_ids'], [$active_company_id]);
        $stmt = $pdo->prepare("
            SELECT id, total_flete_neto FROM viajes
            WHERE id IN ($placeholders)
              AND transportista_id = ?
              AND activo = 1
              AND estado = 'facturado'
        ");
        $stmt->execute($params);
        $viajes_modal = $stmt->fetchAll();
        
        $total_neto = 0;
        foreach ($viajes_modal as $v) {
            $total_neto += (float)($v['total_flete_neto'] ?? 0);
        }
        $modalLoteData['total_neto'] = $total_neto;
        $modalLoteData['total_facturado'] = $total_neto * 1.21;
        $modalLoteData['count'] = count($viajes_modal);
    }
}

// ─── PROCESAR COBRO EN LOTE ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cobrar_lote') {
    $recibo_nro   = trim($_POST['recibo_nro'] ?? '');
    $fecha_cobro  = $_POST['fecha_cobro'] ?? date('Y-m-d');
    $selected_ids = $_POST['viaje_ids'] ?? [];
    $cuenta_id = (int)($_POST['cuenta_id'] ?? 0);
    $observaciones = trim($_POST['observaciones_cobro'] ?? '');
    
    // Múltiples medios de cobro
    $medios_cobro = $_POST['medios_cobro'] ?? [];
    $medios_importe = $_POST['medios_importe'] ?? [];
    $medios_obs = $_POST['medios_obs'] ?? [];
    
    // Retenciones
    $retenciones_tipos = $_POST['retencion_tipo'] ?? [];
    $retenciones_conceptos = $_POST['retencion_concepto'] ?? [];
    $retenciones_montos = $_POST['retencion_monto'] ?? [];
    
    // Cheque (para compatibilidad con cobro único)
    $cheque_tipo = $_POST['cheque_tipo'] ?? 'comun';
    $cheque_banco = trim($_POST['cheque_banco'] ?? '');
    $cheque_numero = trim($_POST['cheque_numero'] ?? '');
    $cheque_fecha_emision = $_POST['cheque_fecha_emision'] ?? '';
    $cheque_fecha_pago = $_POST['cheque_fecha_pago'] ?? '';
    $cheque_librador = trim($_POST['cheque_librador'] ?? '');
    $cheque_endosante = trim($_POST['cheque_endosante'] ?? '');
    $cheque_importe = (float)($_POST['cheque_importe'] ?? 0);

    if ($recibo_nro === '') {
        $error = 'El número de recibo es obligatorio.';
    } elseif (empty($selected_ids) || !is_array($selected_ids)) {
        $error = 'Debe seleccionar al menos un viaje para cobrar.';
    } elseif ($cuenta_id <= 0) {
        $error = 'Debe seleccionar una cuenta de caja destino.';
    } elseif (empty($medios_cobro) || empty($medios_importe)) {
        $error = 'Debe ingresar al menos un medio de cobro con su importe.';
    } else {
        // Validar que todos los viajes pertenezcan a la empresa y estén facturados
        $ids_validos = [];
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $params = array_merge($selected_ids, [$active_company_id]);

        $stmt = $pdo->prepare("
            SELECT id, total_flete_neto, ctg_nro, carta_porte_nro, otros_docs FROM viajes
            WHERE id IN ($placeholders)
              AND transportista_id = ?
              AND activo = 1
              AND estado = 'facturado'
        ");
        $stmt->execute($params);
        $ids_validos = $stmt->fetchAll();

        if (count($ids_validos) !== count($selected_ids)) {
            $error = 'Algunos viajes seleccionados no son válidos o ya no están disponibles para cobrar.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Verificar que la cuenta exista y pertenezca al tenant
                $stmt = $pdo->prepare("SELECT id, saldo_actual FROM cuentas_empresa WHERE id = ? AND transportista_id = ? AND activo = 1");
                $stmt->execute([$cuenta_id, $active_company_id]);
                $cuenta = $stmt->fetch();
                if (!$cuenta) {
                    throw new Exception('La cuenta seleccionada no existe o no pertenece a la empresa activa.');
                }

                // Calcular totales
                $total_neto_sum = 0;
                foreach ($ids_validos as $row) {
                    $total_neto_sum += (float)($row['total_flete_neto'] ?? 0);
                }
                $total_facturado = $total_neto_sum * 1.21;
                
                // Calcular total retenciones
                $total_retenciones = 0;
                $retenciones_validas = [];
                foreach ($retenciones_tipos as $i => $tipo) {
                    $tipo = trim($tipo);
                    $monto = (float)($retenciones_montos[$i] ?? 0);
                    if ($tipo !== '' && $monto > 0) {
                        $total_retenciones += $monto;
                        $retenciones_validas[] = [
                            'tipo' => $tipo,
                            'concepto' => trim($retenciones_conceptos[$i] ?? ''),
                            'monto' => $monto
                        ];
                    }
                }
                
                // Validar suma de medios de cobro
                $total_medios = 0;
                $medios_validos = [];
                foreach ($medios_cobro as $i => $medio) {
                    $medio = trim($medio);
                    $importe = (float)($medios_importe[$i] ?? 0);
                    $obs = trim($medios_obs[$i] ?? '');
                    if ($medio !== '' && $importe > 0) {
                        $total_medios += $importe;
                        $medios_validos[] = [
                            'medio' => $medio,
                            'importe' => $importe,
                            'obs' => $obs
                        ];
                    }
                }
                
                if (empty($medios_validos)) {
                    throw new Exception('Debe ingresar al menos un medio de cobro válido con importe mayor a 0.');
                }
                
                $monto_neto_cobrado = $total_facturado - $total_retenciones;
                
                // Validar que los medios de cobro coincidan con el total
                if (abs($total_medios - $total_facturado) > 0.01) {
                    throw new Exception("La suma de los medios de cobro ($ " . number_format($total_medios, 2, ',', '.') . ") no coincide con el total facturado ($ " . number_format($total_facturado, 2, ',', '.') . ").");
                }

                // Insertar cobro principal (usar el primer medio como principal para compatibilidad)
                $stmt = $pdo->prepare("
                    INSERT INTO cobros_fletes 
                        (transportista_id, viaje_id, cuenta_id, fecha_cobro, monto_total_facturado, monto_neto_cobrado, total_retenciones, medio_cobro, observaciones, recibo_nro, created_by)
                    VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $active_company_id,
                    $cuenta_id,
                    $fecha_cobro,
                    $total_facturado,
                    $monto_neto_cobrado,
                    $total_retenciones,
                    $medios_validos[0]['medio'],
                    $observaciones ?: null,
                    $recibo_nro,
                    $currentUserId ?: null
                ]);
                $cobro_id = $pdo->lastInsertId();

                // Insertar retenciones
                if (!empty($retenciones_validas)) {
                    $stmt_ret = $pdo->prepare("
                        INSERT INTO cobros_fletes_retenciones (cobro_id, tipo, concepto, monto)
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($retenciones_validas as $ret) {
                        $stmt_ret->execute([$cobro_id, $ret['tipo'], $ret['concepto'] ?: null, $ret['monto']]);
                    }
                }

                // Insertar múltiples medios de cobro
                $stmt_medio = $pdo->prepare("
                    INSERT INTO cobros_fletes_medios (cobro_id, medio_cobro, importe, observaciones)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($medios_validos as $medio) {
                    $stmt_medio->execute([
                        $cobro_id,
                        $medio['medio'],
                        $medio['importe'],
                        $medio['obs'] ?: null
                    ]);
                    
                    // Si es cheque, guardar datos del cheque (solo para el primer cheque encontrado)
                    if ($medio['medio'] === 'cheque' && $cheque_importe > 0) {
                        $stmt_cheque = $pdo->prepare("
                            INSERT INTO cobros_fletes_cheques 
                                (cobro_id, tipo_cheque, banco, numero_cheque, fecha_emision, fecha_pago, librador, endosante, importe)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt_cheque->execute([
                            $cobro_id,
                            $cheque_tipo,
                            $cheque_banco ?: null,
                            $cheque_numero ?: null,
                            $cheque_fecha_emision ?: null,
                            $cheque_fecha_pago ?: null,
                            $cheque_librador ?: null,
                            $cheque_endosante ?: null,
                            $cheque_importe
                        ]);
                    }
                }

                // Actualizar viajes y crear movimientos
                $updateStmt = $pdo->prepare("
                    UPDATE viajes
                    SET estado = 'cobrado',
                        fecha_cobro = ?,
                        observaciones = CASE
                            WHEN observaciones IS NULL OR observaciones = '' THEN ?
                            ELSE CONCAT(observaciones, ' | ', ?)
                        END
                    WHERE id = ?
                      AND transportista_id = ?
                      AND activo = 1
                      AND estado = 'facturado'
                ");

                $nuevo_saldo = (float)$cuenta['saldo_actual'] + $monto_neto_cobrado;
                $movimiento_por_viaje = $monto_neto_cobrado / count($ids_validos);

                foreach ($ids_validos as $row) {
                    $updateStmt->execute([
                        $fecha_cobro,
                        $observaciones ?: "Cobro lote (Recibo: {$recibo_nro}, Cobro ID: {$cobro_id})",
                        $observaciones ?: "Cobro lote (Recibo: {$recibo_nro}, Cobro ID: {$cobro_id})",
                        $row['id'],
                        $active_company_id
                    ]);

                    // Insertar movimiento por cada viaje
                    $ref_label = !empty($row['ctg_nro']) ? ('CTG ' . $row['ctg_nro']) :
                                 (!empty($row['carta_porte_nro']) ? ('CP ' . $row['carta_porte_nro']) :
                                 (!empty($row['otros_docs']) ? $row['otros_docs'] : ('Viaje #' . $row['id'])));
                    $concepto = "Cobro lote {$recibo_nro}: {$ref_label}";
                    
                    $stmt_mov = $pdo->prepare("
                        INSERT INTO cuentas_movimientos 
                            (transportista_id, cuenta_id, tipo, concepto, referencia_tipo, referencia_id, monto, saldo_resultante, fecha_movimiento, observaciones, created_by)
                        VALUES (?, ?, 'entrada', ?, 'cobro_flete_lote', ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_mov->execute([
                        $active_company_id,
                        $cuenta_id,
                        $concepto,
                        $cobro_id,
                        $movimiento_por_viaje,
                        $nuevo_saldo,
                        $fecha_cobro,
                        $observaciones ?: null,
                        $currentUserId ?: null
                    ]);
                }

                // Actualizar saldo de la cuenta una sola vez
                $pdo->prepare("UPDATE cuentas_empresa SET saldo_actual = ? WHERE id = ?")
                    ->execute([$nuevo_saldo, $cuenta_id]);

                $pdo->commit();

                $mensaje = "Recibo N° <strong>" . htmlspecialchars($recibo_nro) . "</strong> registrado exitosamente. <strong>" . count($ids_validos) . "</strong> viaje(s) cobrado(s) por un total de <strong>$ " . number_format($monto_neto_cobrado, 2, ',', '.') . "</strong>.";
                if ($total_retenciones > 0) {
                    $mensaje .= " (Retenciones: $ " . number_format($total_retenciones, 2, ',', '.') . ")";
                }
                
                // Refrescar datos
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
                          AND v.estado = 'facturado'
                          AND v.pagador_id = ?
                        ORDER BY v.fecha_carga DESC, v.id DESC
                    ");
                    $stmt->execute([$active_company_id, $selected_pagador_id]);
                    $viajes_pagador = $stmt->fetchAll();
                    $total_neto_sum = 0;
                    $total_fact_sum = 0;
                    foreach ($viajes_pagador as $v) {
                        $neto = (float)($v['total_flete_neto'] ?? 0);
                        $total_neto_sum += $neto;
                        $total_fact_sum += $neto * 1.21;
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al cobrar en lote: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #27ae60, #2ecc71, #16a085); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-check-double" style="color:#27ae60; margin-right:8px;"></i>
                Cobro en Lote
            </h2>
            <div style="margin-top:4px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Agrupe múltiples viajes facturados del mismo pagador en un solo cobro
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
    <h3 style="margin-top:0;">
        <i class="fas fa-user-tie"></i> Seleccionar Pagador de Flete
    </h3>

    <form method="GET" action="cobranzas_fletes_cobro_lote" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="flex:1; min-width:250px; margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                <i class="fas fa-building"></i> Pagador
            </label>
            <select name="pagador_id" class="input-field" required style="width:100%;"
                    onchange="this.form.submit()">
                <option value="">-- Seleccione un pagador --</option>
                <?php foreach ($pagadores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $selected_pagador_id === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['razon_social']) ?> (CUIT: <?= htmlspecialchars($p['cuit']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($selected_pagador_id > 0): ?>
        <div style="padding:8px 16px; background:#e8f5e9; border-radius:8px; border:1px solid #a5d6a7;">
            <div style="font-size:0.75rem; color:#666;">Viajes disponibles</div>
            <div style="font-weight:bold; font-size:1.2rem; color:#27ae60;"><?= count($viajes_pagador) ?></div>
        </div>
        <div style="padding:8px 16px; background:#fff3e0; border-radius:8px; border:1px solid #ffe0b2;">
            <div style="font-size:0.75rem; color:#666;">Total Neto</div>
            <div style="font-weight:bold; font-size:1.2rem; color:#e67e22;">$ <?= number_format($total_neto_sum, 2, ',', '.') ?></div>
        </div>
        <div style="padding:8px 16px; background:#e3f2fd; border-radius:8px; border:1px solid #bbdefb;">
            <div style="font-size:0.75rem; color:#666;">Total con IVA</div>
            <div style="font-weight:bold; font-size:1.2rem; color:#1565c0;">$ <?= number_format($total_fact_sum, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($selected_pagador_id > 0 && !empty($viajes_pagador)): ?>
<!-- ─── FORMULARIO DE COBRO ──────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-receipt"></i> Datos del Cobro
        <span style="font-size:0.85rem; font-weight:normal; opacity:0.7; margin-left:auto;">
            Pagador: <strong><?= htmlspecialchars($pagador_nombre) ?></strong>
            <?php if ($pagador_cuit): ?>| CUIT: <?= htmlspecialchars($pagador_cuit) ?><?php endif; ?>
        </span>
    </h3>

    <form method="POST" action="cobranzas_fletes_cobro_lote?pagador_id=<?= $selected_pagador_id ?>" id="loteForm">
        <input type="hidden" name="action" value="cobrar_lote">
        <input type="hidden" name="pagador_id" value="<?= $selected_pagador_id ?>">

        <div style="display:grid; grid-template-columns: 2fr 1.5fr; gap:12px; margin-bottom:16px; align-items:end;">
            <div class="form-group" style="margin:0;">
                <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                    <i class="fas fa-hashtag"></i> N° Recibo *
                </label>
                <input type="text" name="recibo_nro" class="input-field" required
                       placeholder="REC 00001-00000001" style="width:100%;">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                    <i class="far fa-calendar-alt"></i> Fecha de Cobro
                </label>
                <input type="date" name="fecha_cobro" class="input-field"
                       value="<?= date('Y-m-d') ?>" style="width:100%;">
            </div>
        </div>

        <!-- ─── TABLA DE VIAJES ─────────────────────── -->
        <div class="table-container" style="margin-bottom:16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)"
                                   style="width:18px; height:18px; cursor:pointer;">
                        </th>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Origen → Destino</th>
                        <th>Patente</th>
                        <th style="text-align:right;">TN Desc.</th>
                        <th style="text-align:right;">Neto</th>
                        <th style="text-align:right;">Total Fact.</th>
                        <th>Fecha Fact.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_pagador as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $neto = (float)($v['total_flete_neto'] ?? 0);
                    $total_fact = $neto * 1.21;
                    $tn_desc = (float)($v['peso_neto'] ?? 0);
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" name="viaje_ids[]" value="<?= (int)$v['id'] ?>"
                                   class="viaje-checkbox" style="width:18px; height:18px; cursor:pointer;"
                                   data-neto="<?= $neto ?>"
                                   data-total="<?= $total_fact ?>"
                                   onchange="updateTotals()">
                        </td>
                        <td style="font-weight:600;"><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td style="font-size:0.9rem;">
                            <?= htmlspecialchars($v['origen'] ?? '-') ?>
                            <i class="fas fa-arrow-right" style="color:#999; font-size:0.7rem; margin:0 3px;"></i>
                            <?= htmlspecialchars($v['destino'] ?? '-') ?>
                        </td>
                        <td><?= htmlspecialchars($v['vehiculo_dominio'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= number_format($tn_desc, 2, ',', '.') ?></td>
                        <td style="text-align:right;">$ <?= number_format($neto, 2, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            $ <?= number_format($total_fact, 2, ',', '.') ?>
                        </td>
                        <td><?= htmlspecialchars(formatDate($v['factura_fecha'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold; background:#f8f9fa;">
                        <td></td>
                        <td colspan="6" style="text-align:right;">Totales:</td>
                        <td style="text-align:right; color:#27ae60;" id="totalNetoDisplay">
                            $ <?= number_format($total_neto_sum, 2, ',', '.') ?>
                        </td>
                        <td style="text-align:right; color:#27ae60;" id="totalFactDisplay">
                            $ <?= number_format($total_fact_sum, 2, ',', '.') ?>
                        </td>
                        <td></td>
                    </tr>
                    <tr style="font-weight:bold; background:#f3e5f5;">
                        <td></td>
                        <td colspan="6" style="text-align:right; color:#9b59b6;">
                            <span id="selectedCount">0</span> viaje(s) seleccionado(s)
                        </td>
                        <td style="text-align:right; color:#9b59b6;" id="selectedNetoDisplay">
                            $ 0,00
                        </td>
                        <td style="text-align:right; color:#9b59b6;" id="selectedTotalDisplay">
                            $ 0,00
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #eee; padding-top:14px;">
            <a class="btn-secondary" href="cobranzas" style="padding:10px 18px; text-decoration:none;">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="button" class="btn-primary" style="background:#27ae60; border:none; padding:10px 18px; display:inline-flex; align-items:center; gap:8px;"
                    onclick="abrirModalCobroLote()">
                <i class="fas fa-check-double"></i> Cobrar en Lote
            </button>
        </div>
    </form>
</div>

<!-- ─── MODAL COBRO EN LOTE ─────────────────────────── -->
<div id="modal-cobro-lote" class="modal">
    <div class="modal-content" style="max-width:750px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #27ae60, #2ecc71); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-check-double" style="margin-right:8px;"></i> Registrar Cobro en Lote
            </h3>
            <span class="close-modal" onclick="cerrarModalCobroLote()" style="color:#fff; font-size:1.2rem; cursor:pointer;">&times;</span>
        </div>
        <form method="POST" id="form-cobro-lote">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="cobrar_lote">
                <input type="hidden" name="pagador_id" value="<?= $selected_pagador_id ?>">
                <input type="hidden" name="recibo_nro" id="lote-recibo-nro">
                <input type="hidden" name="fecha_cobro" id="lote-fecha-cobro">
                
                <!-- Los viajes seleccionados se pasan como array hidden -->
                <?php if ($showModalLote && !empty($modalLoteData['selected_ids'])): ?>
                    <?php foreach ($modalLoteData['selected_ids'] as $id): ?>
                        <input type="hidden" name="viaje_ids[]" value="<?= (int)$id ?>">
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Info del lote -->
                <div style="background:#f0fdf4; border-radius:8px; padding:12px; border:1px solid #bbf7d0; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-weight:bold; font-size:1.05rem;">
                                <i class="fas fa-layer-group"></i> Cobro en Lote
                            </span>
                            <div style="font-size:0.85rem; color:#666; margin-top:2px;">
                                <?= $modalLoteData['count'] ?> viaje(s) seleccionado(s)
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.75rem; color:#666;">Total Facturado</div>
                            <div style="font-weight:bold; font-size:1.2rem; color:#16a34a;" id="lote-total-display">
                                $ <?= number_format($modalLoteData['total_facturado'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <!-- Cuenta destino -->
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                            <i class="fas fa-wallet"></i> Cuenta Destino *
                        </label>
                        <select name="cuenta_id" class="input-field" required style="width:100%;">
                            <option value="">-- Seleccionar cuenta --</option>
                            <?php foreach ($cuentas as $c): 
                                $tipo_label = match($c['tipo']) {
                                    'banco' => '🏦',
                                    'billetera_virtual' => '📱',
                                    'caja_efectivo' => '💵',
                                    default => '📦'
                                };
                            ?>
                                <option value="<?= (int)$c['id'] ?>">
                                    <?= $tipo_label ?> <?= htmlspecialchars($c['nombre']) ?> 
                                    <?php if ($c['banco']): ?>(<?= htmlspecialchars($c['banco']) ?>)<?php endif; ?>
                                    - Saldo: $ <?= number_format((float)$c['saldo_actual'], 2, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fecha de cobro -->
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                            <i class="far fa-calendar-alt"></i> Fecha de Cobro
                        </label>
                        <input type="date" name="fecha_cobro" class="input-field" value="<?= date('Y-m-d') ?>" style="width:100%;">
                    </div>
                </div>

                <!-- Medios de Cobro (múltiples) -->
                <div style="background:#f0f9ff; border-radius:8px; padding:14px; border:1px solid #bae6fd; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="margin:0; font-size:0.95rem; color:#0369a1;">
                            <i class="fas fa-credit-card"></i> Medios de Cobro
                        </h4>
                        <button type="button" class="btn-primary btn-sm" onclick="agregarLoteMedioCobro()" style="background:#0284c7; border:none; padding:6px 12px; font-size:0.8rem; cursor:pointer;">
                            <i class="fas fa-plus"></i> Agregar Medio
                        </button>
                    </div>
                    <div id="lote-medios-container">
                        <div class="lote-medio-fila" style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;">
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Medio</label>
                                <select name="medios_cobro[]" class="input-field lote-medio-select" style="width:100%; font-size:0.85rem;" onchange="toggleLoteChequeFieldsMedio(this)">
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="transferencia">🏦 Transferencia</option>
                                    <option value="cheque">📄 Cheque</option>
                                    <option value="mercadopago">📱 Mercado Pago</option>
                                    <option value="otro">📦 Otro</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Importe $</label>
                                <input type="number" step="0.01" min="0" name="medios_importe[]" class="input-field lote-medio-importe" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularLoteNeto()" onkeyup="calcularLoteNeto()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Observaciones</label>
                                <input type="text" name="medios_obs[]" class="input-field" placeholder="Opcional" style="width:100%; font-size:0.85rem;">
                            </div>
                            <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularLoteNeto();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0 0 0; border-top:1px solid #bae6fd; margin-top:8px;">
                        <span style="font-weight:bold; font-size:0.9rem;">Total Medios:</span>
                        <span style="font-weight:bold; font-size:1rem; color:#0284c7;" id="lote-total-medios">$ 0,00</span>
                    </div>
                </div>

                <!-- Datos del Cheque (oculto por defecto) -->
                <div id="lote-cheque-fields" style="display:none; background:#fffbeb; border-radius:8px; padding:14px; border:1px solid #fde68a; margin-bottom:14px;">
                    <h4 style="margin:0 0 10px 0; font-size:0.95rem; color:#92400e;">
                        <i class="fas fa-money-check"></i> Datos del Cheque
                    </h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Tipo de Cheque</label>
                            <select name="cheque_tipo" class="input-field" style="width:100%;">
                                <option value="comun">Común (24hs)</option>
                                <option value="diferido">Diferido</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Banco</label>
                            <input type="text" name="cheque_banco" class="input-field" placeholder="Banco" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">N° Cheque</label>
                            <input type="text" name="cheque_numero" class="input-field" placeholder="Número" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Importe *</label>
                            <input type="number" step="0.01" min="0" name="cheque_importe" class="input-field" value="0" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Fecha Emisión</label>
                            <input type="date" name="cheque_fecha_emision" class="input-field" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Fecha Pago / Cobro</label>
                            <input type="date" name="cheque_fecha_pago" class="input-field" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Librador</label>
                            <input type="text" name="cheque_librador" class="input-field" placeholder="Quien emite" style="width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.8rem;">Endosante</label>
                            <input type="text" name="cheque_endosante" class="input-field" placeholder="A quien se endosa" style="width:100%;">
                        </div>
                    </div>
                </div>

                <!-- Retenciones -->
                <div style="background:#fef2f2; border-radius:8px; padding:14px; border:1px solid #fecaca; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="margin:0; font-size:0.95rem; color:#991b1b;">
                            <i class="fas fa-percent"></i> Retenciones
                        </h4>
                        <button type="button" class="btn-primary btn-sm" onclick="agregarLoteRetencion()" style="background:#dc2626; border:none; padding:6px 12px; font-size:0.8rem; cursor:pointer;">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                    <div id="lote-retenciones-container">
                        <div class="lote-retencion-fila" style="display:grid; grid-template-columns: 1fr 1.5fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;">
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Tipo</label>
                                <select name="retencion_tipo[]" class="input-field" style="width:100%; font-size:0.85rem;">
                                    <option value="">--</option>
                                    <option value="IVA">IVA</option>
                                    <option value="Ganancias">Ganancias</option>
                                    <option value="Ingresos Brutos">Ingresos Brutos</option>
                                    <option value="SUSS">SUSS</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Concepto</label>
                                <input type="text" name="retencion_concepto[]" class="input-field" placeholder="Detalle" style="width:100%; font-size:0.85rem;">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Monto $</label>
                                <input type="number" step="0.01" min="0" name="retencion_monto[]" class="input-field lote-retencion-monto" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularLoteNeto()" onkeyup="calcularLoteNeto()">
                            </div>
                            <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularLoteNeto();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0 0 0; border-top:1px solid #fecaca; margin-top:8px;">
                        <span style="font-weight:bold; font-size:0.9rem;">Total Retenciones:</span>
                        <span style="font-weight:bold; font-size:1rem; color:#dc2626;" id="lote-total-retenciones">$ 0,00</span>
                    </div>
                </div>

                <!-- Resumen final -->
                <div style="background:#f0fdf4; border-radius:8px; padding:14px; border:2px solid #86efac; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.85rem; color:#166534;">Total Facturado</div>
                            <div style="font-weight:bold; font-size:1.1rem;" id="lote-resumen-total">$ 0,00</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:0.85rem; color:#dc2626;">Retenciones</div>
                            <div style="font-weight:bold; font-size:1.1rem; color:#dc2626;" id="lote-resumen-retenciones">$ 0,00</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.85rem; color:#166534;">Neto a Cobrar</div>
                            <div style="font-weight:bold; font-size:1.3rem; color:#16a34a;" id="lote-resumen-neto">$ 0,00</div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:bold; display:block; margin-bottom:4px; font-size:0.9rem;">
                        <i class="fas fa-comment"></i> Observaciones
                    </label>
                    <textarea name="observaciones_cobro" class="input-field" rows="2" placeholder="Notas adicionales sobre el cobro..." style="width:100%; resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px; border-top:1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="cerrarModalCobroLote()" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#27ae60; border:none; padding:10px 18px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-check-double"></i> Confirmar Cobro en Lote
                </button>
            </div>
        </form>
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
    let selectedNeto = 0;
    let selectedTotal = 0;
    let count = 0;

    checkboxes.forEach(cb => {
        selectedNeto += parseFloat(cb.dataset.neto || 0);
        selectedTotal += parseFloat(cb.dataset.total || 0);
        count++;
    });

    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedNetoDisplay').textContent = '$ ' + formatNumber(selectedNeto);
    document.getElementById('selectedTotalDisplay').textContent = '$ ' + formatNumber(selectedTotal);
}

function formatNumber(num) {
    return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function validateSelection() {
    const checkboxes = document.querySelectorAll('.viaje-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Debe seleccionar al menos un viaje para cobrar.');
        return false;
    }
    return false; // No enviar directamente, abrir modal
}

// ─── FUNCIONES MODAL COBRO LOTE ───────────────────
function abrirModalCobroLote() {
    const checkboxes = document.querySelectorAll('.viaje-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Debe seleccionar al menos un viaje para cobrar.');
        return;
    }
    
    // Obtener valores del formulario
    const reciboNro = document.querySelector('input[name="recibo_nro"]').value;
    const fechaCobro = document.querySelector('input[name="fecha_cobro"]').value;
    
    if (!reciboNro.trim()) {
        alert('Ingrese el número de recibo.');
        return;
    }
    
    // Guardar valores en el modal
    document.getElementById('lote-recibo-nro').value = reciboNro;
    document.getElementById('lote-fecha-cobro').value = fechaCobro;
    
    // Calcular totales para el resumen
    let totalNeto = 0;
    checkboxes.forEach(cb => {
        totalNeto += parseFloat(cb.dataset.neto || 0);
    });
    const totalFacturado = totalNeto * 1.21;
    
    document.getElementById('lote-total-display').textContent = '$ ' + totalFacturado.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('lote-resumen-total').textContent = '$ ' + totalFacturado.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Resetear retenciones
    const container = document.getElementById('lote-retenciones-container');
    container.innerHTML = '';
    agregarLoteRetencion();
    
    // Resetear medios de cobro
    const mediosContainer = document.getElementById('lote-medios-container');
    mediosContainer.innerHTML = '';
    agregarLoteMedioCobro();
    
    // Ocultar campos de cheque
    document.getElementById('lote-cheque-fields').style.display = 'none';
    
    calcularLoteNeto();
    openModal('modal-cobro-lote');
}

function cerrarModalCobroLote() {
    closeModal('modal-cobro-lote');
}


function toggleLoteChequeFieldsMedio(selectElement) {
    const medio = selectElement.value;
    const chequeFields = document.getElementById('lote-cheque-fields');
    chequeFields.style.display = (medio === 'cheque') ? 'block' : 'none';
}

function agregarLoteRetencion() {
    const container = document.getElementById('lote-retenciones-container');
    const fila = document.createElement('div');
    fila.className = 'lote-retencion-fila';
    fila.style.cssText = 'display:grid; grid-template-columns: 1fr 1.5fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;';
    fila.innerHTML = `
        <div class="form-group" style="margin:0;">
            <select name="retencion_tipo[]" class="input-field" style="width:100%; font-size:0.85rem;">
                <option value="">--</option>
                <option value="IVA">IVA</option>
                <option value="Ganancias">Ganancias</option>
                <option value="Ingresos Brutos">Ingresos Brutos</option>
                <option value="SUSS">SUSS</option>
                <option value="Otro">Otro</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <input type="text" name="retencion_concepto[]" class="input-field" placeholder="Detalle" style="width:100%; font-size:0.85rem;">
        </div>
        <div class="form-group" style="margin:0;">
            <input type="number" step="0.01" min="0" name="retencion_monto[]" class="input-field lote-retencion-monto" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularLoteNeto()" onkeyup="calcularLoteNeto()">
        </div>
        <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularLoteNeto();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(fila);
}

function agregarLoteMedioCobro() {
    const container = document.getElementById('lote-medios-container');
    const fila = document.createElement('div');
    fila.className = 'lote-medio-fila';
    fila.style.cssText = 'display:grid; grid-template-columns: 1.5fr 1fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;';
    fila.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Medio</label>
            <select name="medios_cobro[]" class="input-field lote-medio-select" style="width:100%; font-size:0.85rem;" onchange="toggleLoteChequeFieldsMedio(this)">
                <option value="efectivo">💵 Efectivo</option>
                <option value="transferencia">🏦 Transferencia</option>
                <option value="cheque">📄 Cheque</option>
                <option value="mercadopago">📱 Mercado Pago</option>
                <option value="otro">📦 Otro</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Importe $</label>
            <input type="number" step="0.01" min="0" name="medios_importe[]" class="input-field lote-medio-importe" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularLoteNeto()" onkeyup="calcularLoteNeto()">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Observaciones</label>
            <input type="text" name="medios_obs[]" class="input-field" placeholder="Opcional" style="width:100%; font-size:0.85rem;">
        </div>
        <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularLoteNeto();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(fila);
}

function calcularLoteNeto() {
    const checkboxes = document.querySelectorAll('.viaje-checkbox:checked');
    let totalNeto = 0;
    checkboxes.forEach(cb => {
        totalNeto += parseFloat(cb.dataset.neto || 0);
    });
    const totalFacturado = totalNeto * 1.21;
    
    const montos = document.querySelectorAll('.lote-retencion-monto');
    let totalRet = 0;
    montos.forEach(input => {
        const val = parseFloat(input.value) || 0;
        totalRet += val;
    });
    
    document.getElementById('lote-total-retenciones').textContent = '$ ' + totalRet.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('lote-resumen-retenciones').textContent = '$ ' + totalRet.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const neto = totalFacturado - totalRet;
    document.getElementById('lote-resumen-neto').textContent = '$ ' + neto.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php elseif ($selected_pagador_id > 0 && empty($viajes_pagador)): ?>
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-check-circle" style="color:#27ae60; font-size:3rem; display:block; margin-bottom:12px;"></i>
    <h3 style="margin:0 0 8px 0;">No hay viajes pendientes</h3>
    <p style="opacity:0.7; margin:0;">
        El pagador <strong><?= htmlspecialchars($pagador_nombre) ?></strong> no tiene viajes facturados pendientes de cobro.
    </p>
    <a href="cobranzas_fletes_cobro_lote" class="btn-primary" style="margin-top:16px; display:inline-block; text-decoration:none;">
        <i class="fas fa-redo"></i> Seleccionar otro pagador
    </a>
</div>
<?php elseif (empty($pagadores)): ?>
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-info-circle" style="color:#3498db; font-size:3rem; display:block; margin-bottom:12px;"></i>
    <h3 style="margin:0 0 8px 0;">Sin viajes disponibles</h3>
    <p style="opacity:0.7; margin:0;">
        No hay viajes facturados con pagador de flete asignado para cobrar en lote.
    </p>
    <a href="cobranzas" class="btn-primary" style="margin-top:16px; display:inline-block; text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Volver a Cobranzas
    </a>
</div>
<?php endif; ?>