<?php
/**
 * Cobranzas - Liquidación de Fletes (Por Cobrar / Liquidar)
 * 
 * Muestra los viajes facturados pendientes de cobro y cobrados.
 * Permite registrar cobros con:
 *   - Cuenta de caja destino (cuentas_empresa)
 *   - Retenciones detalladas (IVA, Ganancias, IIBB, SUSS, Otro)
 *   - Medio de pago (efectivo, transferencia, cheque)
 *   - Datos completos del cheque si aplica
 *   - Cálculo automático del neto a cobrar
 *
 * Multi-tenant: filtra por transportista_id = $_SESSION['active_company_id']
 */

$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$currentUserId = $_SESSION['user_id'] ?? 0;
$mensaje = '';
$error   = '';

// ─── AUTO-ABRIR MODAL SI VIENE DE REDIRECCIÓN ──────────
$autoCobrarViajeId = (int)($_GET['cobrar_viaje_id'] ?? 0);

// ─── PROCESAR COBRO ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cobrar') {
    $viaje_id = (int)($_POST['viaje_id'] ?? 0);
    $cuenta_id = (int)($_POST['cuenta_id'] ?? 0);
    $fecha_cobro = $_POST['fecha_cobro'] ?? date('Y-m-d');
    $monto_total_facturado = (float)($_POST['monto_total_facturado'] ?? 0);
    $observaciones = trim($_POST['observaciones_cobro'] ?? '');
    
    // Múltiples medios de cobro
    $medios_cobro = $_POST['medios_cobro'] ?? [];
    $medios_importe = $_POST['medios_importe'] ?? [];
    $medios_obs = $_POST['medios_obs'] ?? [];
    
    // Retenciones
    $retenciones_tipos = $_POST['retencion_tipo'] ?? [];
    $retenciones_conceptos = $_POST['retencion_concepto'] ?? [];
    $retenciones_montos = $_POST['retencion_monto'] ?? [];
    
    // Cheque (para compatibilidad)
    $cheque_tipo = $_POST['cheque_tipo'] ?? 'comun';
    $cheque_banco = trim($_POST['cheque_banco'] ?? '');
    $cheque_numero = trim($_POST['cheque_numero'] ?? '');
    $cheque_fecha_emision = $_POST['cheque_fecha_emision'] ?? '';
    $cheque_fecha_pago = $_POST['cheque_fecha_pago'] ?? '';
    $cheque_librador = trim($_POST['cheque_librador'] ?? '');
    $cheque_endosante = trim($_POST['cheque_endosante'] ?? '');
    $cheque_importe = (float)($_POST['cheque_importe'] ?? 0);

    if ($viaje_id <= 0) {
        $error = 'ID de viaje inválido.';
    } elseif ($cuenta_id <= 0) {
        $error = 'Debe seleccionar una cuenta de caja destino.';
    } elseif ($monto_total_facturado <= 0) {
        $error = 'El monto total facturado debe ser mayor a 0.';
    } elseif (empty($medios_cobro) || empty($medios_importe)) {
        $error = 'Debe ingresar al menos un medio de cobro con su importe.';
    } else {
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
        
        $monto_neto_cobrado = $monto_total_facturado - $total_retenciones;
        
        // Validar que los medios de cobro coincidan con el total
        if (abs($total_medios - $monto_total_facturado) > 0.01) {
            throw new Exception("La suma de los medios de cobro ($ " . number_format($total_medios, 2, ',', '.') . ") no coincide con el total facturado ($ " . number_format($monto_total_facturado, 2, ',', '.') . ").");
        }

        try {
            $pdo->beginTransaction();

            // Verificar que el viaje exista y esté facturado
            $stmt = $pdo->prepare("
                SELECT v.id, v.total_flete_neto, v.estado, v.ctg_nro, v.carta_porte_nro, v.otros_docs
                FROM viajes v
                WHERE v.id = ? AND v.transportista_id = ? AND v.activo = 1 AND v.estado = 'facturado'
            ");
            $stmt->execute([$viaje_id, $active_company_id]);
            $viaje_check = $stmt->fetch();

            if (!$viaje_check) {
                throw new Exception('El viaje no existe, no pertenece a la empresa activa o no está en estado "facturado".');
            }

            // Verificar que la cuenta exista y pertenezca al tenant
            $stmt = $pdo->prepare("SELECT id, saldo_actual FROM cuentas_empresa WHERE id = ? AND transportista_id = ? AND activo = 1");
            $stmt->execute([$cuenta_id, $active_company_id]);
            $cuenta = $stmt->fetch();
            if (!$cuenta) {
                throw new Exception('La cuenta seleccionada no existe o no pertenece a la empresa activa.');
            }

            // Insertar cobro principal (usar el primer medio como principal para compatibilidad)
            $stmt = $pdo->prepare("
                INSERT INTO cobros_fletes 
                    (transportista_id, viaje_id, cuenta_id, fecha_cobro, monto_total_facturado, monto_neto_cobrado, total_retenciones, medio_cobro, observaciones, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $active_company_id,
                $viaje_id,
                $cuenta_id,
                $fecha_cobro,
                $monto_total_facturado,
                $monto_neto_cobrado,
                $total_retenciones,
                $medios_validos[0]['medio'],
                $observaciones ?: null,
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

            // Actualizar saldo de la cuenta
            $nuevo_saldo = (float)$cuenta['saldo_actual'] + $monto_neto_cobrado;
            $pdo->prepare("UPDATE cuentas_empresa SET saldo_actual = ? WHERE id = ?")
                ->execute([$nuevo_saldo, $cuenta_id]);

            // Insertar movimiento en cuentas_movimientos
            $ref_label = !empty($viaje_check['ctg_nro']) ? ('CTG ' . $viaje_check['ctg_nro']) :
                         (!empty($viaje_check['carta_porte_nro']) ? ('CP ' . $viaje_check['carta_porte_nro']) :
                         (!empty($viaje_check['otros_docs']) ? $viaje_check['otros_docs'] : ('Viaje #' . $viaje_id)));
            $concepto = 'Cobro flete: ' . $ref_label;
            $stmt_mov = $pdo->prepare("
                INSERT INTO cuentas_movimientos 
                    (transportista_id, cuenta_id, tipo, concepto, referencia_tipo, referencia_id, monto, saldo_resultante, fecha_movimiento, observaciones, created_by)
                VALUES (?, ?, 'entrada', ?, 'cobro_flete', ?, ?, ?, ?, ?, ?)
            ");
            $stmt_mov->execute([
                $active_company_id,
                $cuenta_id,
                $concepto,
                $cobro_id,
                $monto_neto_cobrado,
                $nuevo_saldo,
                $fecha_cobro,
                $observaciones ?: null,
                $currentUserId ?: null
            ]);

            // Marcar viaje como cobrado
            $pdo->prepare("
                UPDATE viajes
                SET estado = 'cobrado',
                    fecha_cobro = ?,
                    observaciones = CASE
                        WHEN observaciones IS NULL OR observaciones = '' THEN ?
                        ELSE CONCAT(observaciones, ' | ', ?)
                    END
                WHERE id = ? AND transportista_id = ? AND activo = 1
            ")->execute([
                $fecha_cobro,
                $observaciones ?: "Cobro registrado (ID: {$cobro_id})",
                $observaciones ?: "Cobro registrado (ID: {$cobro_id})",
                $viaje_id,
                $active_company_id
            ]);

            $pdo->commit();
            $mensaje = "Cobro registrado exitosamente. Neto cobrado: \$ " . number_format($monto_neto_cobrado, 2, ',', '.');
            if ($total_retenciones > 0) {
                $mensaje .= " (Retenciones: \$ " . number_format($total_retenciones, 2, ',', '.') . ")";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar cobro: " . $e->getMessage();
        }
    }
}

// ─── PROCESAR LIQUIDACIÓN ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'liquidar') {
    $viaje_id = (int)($_POST['viaje_id'] ?? 0);

    if ($viaje_id <= 0) {
        $error = 'ID de viaje inválido.';
    } else {
        $stmt = $pdo->prepare("
            SELECT v.*, ch.id as chofer_id_real, ch.apellido, ch.nombre, ch.porcentaje_ganancia
            FROM viajes v
            LEFT JOIN choferes ch ON ch.id = v.chofer_id
            WHERE v.id = ? AND v.transportista_id = ? AND v.activo = 1 AND v.estado = 'cobrado'
        ");
        $stmt->execute([$viaje_id, $active_company_id]);
        $viaje_liq = $stmt->fetch();

        if (!$viaje_liq) {
            $error = 'El viaje no existe, no pertenece a la empresa activa o no está en estado "cobrado".';
        } elseif (empty($viaje_liq['chofer_id_real'])) {
            $error = 'El viaje no tiene un chofer asignado para liquidar.';
        } else {
            try {
                $pdo->beginTransaction();

                $total_neto = (float)($viaje_liq['total_flete_neto'] ?? 0);
                $chofer_pct = (float)($viaje_liq['chofer_porcentaje'] ?? 0);
                $ganancia_chofer = $total_neto * ($chofer_pct / 100);

                $detalle_ref = '';
                if (!empty($viaje_liq['ctg_nro'])) {
                    $detalle_ref = 'CTG ' . $viaje_liq['ctg_nro'];
                } elseif (!empty($viaje_liq['carta_porte_nro'])) {
                    $detalle_ref = 'CP ' . $viaje_liq['carta_porte_nro'];
                } elseif (!empty($viaje_liq['otros_docs'])) {
                    $detalle_ref = $viaje_liq['otros_docs'];
                } else {
                    $detalle_ref = 'Viaje #' . $viaje_id;
                }

                $stmt_pago = $pdo->prepare("
                    INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle)
                    VALUES (?, ?, ?, 'liquidacion', ?)
                ");
                $stmt_pago->execute([
                    $viaje_liq['chofer_id_real'],
                    date('Y-m-d'),
                    $ganancia_chofer,
                    "Liquidación final {$detalle_ref}"
                ]);

                $pdo->prepare("
                    UPDATE viajes
                    SET estado = 'liquidado',
                        acreditado_chofer = 1
                    WHERE id = ? AND transportista_id = ? AND activo = 1
                ")->execute([$viaje_id, $active_company_id]);

                $pdo->commit();
                $mensaje = "Viaje liquidado exitosamente. Se acreditaron \$ " . number_format($ganancia_chofer, 2, ',', '.') . " a {$viaje_liq['apellido']}, {$viaje_liq['nombre']}.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al liquidar: " . $e->getMessage();
            }
        }
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

// ─── LISTADO 1: VIAJES FACTURADOS (Por Cobrar) ──────────
$stmt = $pdo->prepare("
    SELECT v.*,
           c.razon_social as cliente_nombre,
           CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
           ve.dominio as vehiculo_dominio,
           p.razon_social as pagador_nombre
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN choferes ch ON ch.id = v.chofer_id
    LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
    LEFT JOIN clientes p ON p.id = v.pagador_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'facturado'
    ORDER BY v.factura_fecha DESC, v.id DESC
");
$stmt->execute([$active_company_id]);
$viajes_facturados = $stmt->fetchAll();

// ─── LISTADO 2: VIAJES COBRADOS (Por Liquidar) ─────────
$stmt = $pdo->prepare("
    SELECT v.*,
           c.razon_social as cliente_nombre,
           CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
           ve.dominio as vehiculo_dominio,
           p.razon_social as pagador_nombre,
           cf.id as cobro_id,
           cf.monto_neto_cobrado,
           cf.total_retenciones,
           cf.medio_cobro,
           cf.cuenta_id,
           ce.nombre as cuenta_nombre,
           ce.tipo as cuenta_tipo
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN choferes ch ON ch.id = v.chofer_id
    LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
    LEFT JOIN clientes p ON p.id = v.pagador_id
    LEFT JOIN cobros_fletes cf ON cf.viaje_id = v.id AND cf.activo = 1
    LEFT JOIN cuentas_empresa ce ON ce.id = cf.cuenta_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'cobrado'
    ORDER BY v.fecha_cobro DESC, v.id DESC
");
$stmt->execute([$active_company_id]);
$viajes_cobrados = $stmt->fetchAll();

// ─── LISTADO 3: VIAJES LIQUIDADOS ──────────────────────
$stmt = $pdo->prepare("
    SELECT v.*,
           c.razon_social as cliente_nombre,
           CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
           ve.dominio as vehiculo_dominio,
           p.razon_social as pagador_nombre
    FROM viajes v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN choferes ch ON ch.id = v.chofer_id
    LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
    LEFT JOIN clientes p ON p.id = v.pagador_id
    WHERE v.transportista_id = ?
      AND v.activo = 1
      AND v.estado = 'liquidado'
    ORDER BY v.fecha_cobro DESC, v.id DESC
    LIMIT 20
");
$stmt->execute([$active_company_id]);
$viajes_liquidados = $stmt->fetchAll();

// Helper para badge de medio de cobro
function badgeMedioCobro($medio) {
    return match($medio) {
        'efectivo'      => '<span class="badge" style="background:#27ae60; color:#fff;"><i class="fas fa-money-bill-wave"></i> Efectivo</span>',
        'transferencia' => '<span class="badge" style="background:#3498db; color:#fff;"><i class="fas fa-exchange-alt"></i> Transferencia</span>',
        'cheque'        => '<span class="badge" style="background:#e67e22; color:#fff;"><i class="fas fa-money-check"></i> Cheque</span>',
        'mercadopago'   => '<span class="badge" style="background:#8e44ad; color:#fff;"><i class="fas fa-mobile-alt"></i> Mercado Pago</span>',
        default         => '<span class="badge" style="background:#95a5a6; color:#fff;"><i class="fas fa-archive"></i> ' . htmlspecialchars($medio) . '</span>',
    };
}
?>
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <div style="height:6px; background:linear-gradient(90deg, #27ae60, #2ecc71, #1abc9c); position:absolute; top:0; left:0; right:0;"></div>

    <div style="padding:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; font-weight:800;">
                <i class="fas fa-money-check-alt" style="color:#27ae60; margin-right:8px;"></i>
                Fletes a Liquidar
            </h2>
            <div style="margin-top:6px; opacity:0.7; font-size:0.95rem;">
                <i class="fas fa-info-circle"></i> Gestión de cobros y liquidación de choferes
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
    <i class="fas fa-check-circle" style="color:#27ae60; margin-right:6px;"></i> <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error" style="padding:14px 18px; margin-bottom:18px; border-left:5px solid #e74c3c; background:#fdedec; border-radius:6px;">
    <i class="fas fa-exclamation-triangle" style="color:#e74c3c; margin-right:6px;"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ─── SECCIÓN 1: FACTURADOS (Por Cobrar) ────────── -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-file-invoice" style="color:#9b59b6;"></i> Viajes Facturados (Por Cobrar)
        <span class="badge" style="background:#9b59b6; color:#fff; font-size:0.8rem; padding:4px 10px; margin-left:auto;">
            <?= count($viajes_facturados) ?> pendientes
        </span>
    </h3>

    <?php if (empty($viajes_facturados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">
            <i class="fas fa-check-circle" style="color:#27ae60; font-size:1.5rem; display:block; margin-bottom:8px;"></i>
            No hay viajes facturados pendientes de cobro.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Origen → Destino</th>
                        <th>Factura</th>
                        <th style="text-align:right;">Neto</th>
                        <th style="text-align:right;">Total Facturado</th>
                        <th>Fecha Emisión</th>
                        <th style="text-align:center;">Cobrar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_facturados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $neto    = (float)($v['total_flete_neto'] ?? 0);
                    $total_fact = $neto * 1.21; // IVA 21% incluido
                    $factura_nro = $v['factura_nro'] ?? '';
                    $factura_fecha = $v['factura_fecha'] ?? '';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td style="font-size:0.85rem;">
                            <?= htmlspecialchars($v['origen'] ?? '-') ?>
                            <i class="fas fa-arrow-right" style="color:#999; font-size:0.65rem; margin:0 3px;"></i>
                            <?= htmlspecialchars($v['destino'] ?? '-') ?>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:1px;">
                                <span style="font-weight:bold; font-size:0.9rem;"><?= htmlspecialchars($factura_nro ?: '-') ?></span>
                            </div>
                        </td>
                        <td style="text-align:right;">$ <?= number_format($neto, 2, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            $ <?= number_format($total_fact, 2, ',', '.') ?>
                        </td>
                        <td><?= htmlspecialchars(formatDate($factura_fecha)) ?></td>
                        <td style="text-align:center;">
                            <button onclick="abrirModalCobro(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($label, ENT_QUOTES) ?>', <?= number_format($total_fact, 2, '.', '') ?>, <?= number_format($neto, 2, '.', '') ?>)" 
                                    class="btn-primary" style="background:#27ae60; border:none; padding:8px 14px; font-size:0.85rem; cursor:pointer;">
                                <i class="fas fa-check-double"></i> Cobrar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                    $total_netos = array_sum(array_map(function($v) { return (float)($v['total_flete_neto'] ?? 0); }, $viajes_facturados));
                    $total_facturados = $total_netos * 1.21;
                    ?>
                    <tr style="font-weight:bold; background:#f8f9fa;">
                        <td colspan="4" style="text-align:right;">Totales:</td>
                        <td style="text-align:right;">$ <?= number_format($total_netos, 2, ',', '.') ?></td>
                        <td style="text-align:right; color:#27ae60;">$ <?= number_format($total_facturados, 2, ',', '.') ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ─── SECCIÓN 2: COBRADOS (Por Liquidar) ─────────── -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-hand-holding-usd" style="color:#e67e22;"></i> Viajes Cobrados
        <span class="badge" style="background:#e67e22; color:#fff; font-size:0.8rem; padding:4px 10px; margin-left:auto;">
            <?= count($viajes_cobrados) ?> pendientes
        </span>
    </h3>

    <?php if (empty($viajes_cobrados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">
            <i class="fas fa-check-circle" style="color:#27ae60; font-size:1.5rem; display:block; margin-bottom:8px;"></i>
            No hay viajes cobrados pendientes de liquidar.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Chofer</th>
                        <th>Factura</th>
                        <th style="text-align:right;">Neto Cobrado</th>
                        <th>Fecha Cobro</th>
                        <th>Medio / Cuenta</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_cobrados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $neto    = (float)($v['total_flete_neto'] ?? 0);
                    $pct     = (float)($v['chofer_porcentaje'] ?? 0);
                    $ganancia_chofer = $neto * ($pct / 100);
                    $factura_nro = $v['factura_nro'] ?? '';
                    $monto_cobrado = (float)($v['monto_neto_cobrado'] ?? $neto);
                    $medio = $v['medio_cobro'] ?? '';
                    $cuenta_nombre = $v['cuenta_nombre'] ?? '';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['chofer_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($factura_nro ?: '-') ?></td>
                        <td style="text-align:right; font-weight:bold;">$ <?= number_format($monto_cobrado, 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars(formatDate($v['fecha_cobro'] ?? '')) ?></td>
                        <td style="font-size:0.8rem;">
                            <?php if ($medio): ?>
                                <?= badgeMedioCobro($medio) ?>
                                <?php if ($cuenta_nombre): ?>
                                    <div style="margin-top:2px; opacity:0.7;"><?= htmlspecialchars($cuenta_nombre) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="opacity:0.5;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ─── SECCIÓN 3: LIQUIDADOS (Historial) ─────────── -->
<div class="card">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-history" style="color:#95a5a6;"></i> Últimos Viajes Liquidados
        <span class="badge" style="background:#95a5a6; color:#fff; font-size:0.8rem; padding:4px 10px; margin-left:auto;">
            últimos <?= count($viajes_liquidados) ?>
        </span>
    </h3>

    <?php if (empty($viajes_liquidados)): ?>
        <p style="opacity:0.7; text-align:center; padding:20px;">
            No hay viajes liquidados aún.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CTG / Documento</th>
                        <th>Cliente</th>
                        <th>Chofer</th>
                        <th>Factura</th>
                        <th style="text-align:right;">Neto</th>
                        <th>Fecha Cobro</th>
                        <th style="text-align:right;">Ganancia Chofer</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viajes_liquidados as $v):
                    $label = !empty($v['ctg_nro']) ? ('CTG ' . $v['ctg_nro']) :
                             (!empty($v['carta_porte_nro']) ? ('CP ' . $v['carta_porte_nro']) :
                             (!empty($v['otros_docs']) ? (string)$v['otros_docs'] : ('Viaje #' . (int)$v['id'])));
                    $neto    = (float)($v['total_flete_neto'] ?? 0);
                    $pct     = (float)($v['chofer_porcentaje'] ?? 0);
                    $ganancia_chofer = $neto * ($pct / 100);
                ?>
                    <tr style="opacity:0.85;">
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['chofer_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['factura_nro'] ?? '-') ?></td>
                        <td style="text-align:right;">$ <?= number_format($neto, 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars(formatDate($v['fecha_cobro'] ?? '')) ?></td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">
                            $ <?= number_format($ganancia_chofer, 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ─── MODAL COBRO ─────────────────────────────── -->
<div id="modal-cobro" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #27ae60, #2ecc71); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-check-double" style="margin-right:8px;"></i> Registrar Cobro
            </h3>
            <span class="close-modal" onclick="cerrarModalCobro()" style="color:#fff; font-size:1.2rem; cursor:pointer;">&times;</span>
        </div>
        <form method="POST" id="form-cobro">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="cobrar">
                <input type="hidden" name="viaje_id" id="cobro-viaje-id">
                <input type="hidden" name="monto_total_facturado" id="cobro-monto-total">

                <!-- Info del viaje -->
                <div style="background:#f0fdf4; border-radius:8px; padding:12px; border:1px solid #bbf7d0; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-weight:bold; font-size:1.05rem;" id="cobro-viaje-label"></span>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.75rem; color:#666;">Total Facturado</div>
                            <div style="font-weight:bold; font-size:1.2rem; color:#16a34a;" id="cobro-total-display">$ 0,00</div>
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

                <!-- Medio de cobro (oculto, se mantiene para compatibilidad) -->
                <input type="hidden" name="medio_cobro" id="cobro-medio-hidden" value="efectivo">

                <!-- Medios de Cobro (múltiples) -->
                <div style="background:#f0f9ff; border-radius:8px; padding:14px; border:1px solid #bae6fd; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="margin:0; font-size:0.95rem; color:#0369a1;">
                            <i class="fas fa-credit-card"></i> Medios de Cobro
                        </h4>
                        <button type="button" class="btn-primary btn-sm" onclick="agregarMedioCobro()" style="background:#0284c7; border:none; padding:6px 12px; font-size:0.8rem; cursor:pointer;">
                            <i class="fas fa-plus"></i> Agregar Medio
                        </button>
                    </div>
                    <div id="medios-container">
                        <div class="medio-fila" style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;">
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Medio</label>
                                <select name="medios_cobro[]" class="input-field medio-select" style="width:100%; font-size:0.85rem;" onchange="toggleChequeFieldsMedio(this)">
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="transferencia">🏦 Transferencia</option>
                                    <option value="cheque">📄 Cheque</option>
                                    <option value="mercadopago">📱 Mercado Pago</option>
                                    <option value="otro">📦 Otro</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Importe $</label>
                                <input type="number" step="0.01" min="0" name="medios_importe[]" class="input-field medio-importe" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularNetoCobro()" onkeyup="calcularNetoCobro()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Observaciones</label>
                                <input type="text" name="medios_obs[]" class="input-field" placeholder="Opcional" style="width:100%; font-size:0.85rem;">
                            </div>
                            <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularNetoCobro();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0 0 0; border-top:1px solid #bae6fd; margin-top:8px;">
                        <span style="font-weight:bold; font-size:0.9rem;">Total Medios:</span>
                        <span style="font-weight:bold; font-size:1rem; color:#0284c7;" id="total-medios-display">$ 0,00</span>
                    </div>
                </div>

                <!-- Datos del Cheque (oculto por defecto) -->
                <div id="cheque-fields" style="display:none; background:#fffbeb; border-radius:8px; padding:14px; border:1px solid #fde68a; margin-bottom:14px;">
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
                        <button type="button" class="btn-primary btn-sm" onclick="agregarRetencion()" style="background:#dc2626; border:none; padding:6px 12px; font-size:0.8rem; cursor:pointer;">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                    <div id="retenciones-container">
                        <!-- Fila de ejemplo (se clona) -->
                        <div class="retencion-fila" style="display:grid; grid-template-columns: 1fr 1.5fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;">
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
                                <input type="number" step="0.01" min="0" name="retencion_monto[]" class="input-field retencion-monto" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularNetoCobro()" onkeyup="calcularNetoCobro()">
                            </div>
                            <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularNetoCobro();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:8px 0 0 0; border-top:1px solid #fecaca; margin-top:8px;">
                        <span style="font-weight:bold; font-size:0.9rem;">Total Retenciones:</span>
                        <span style="font-weight:bold; font-size:1rem; color:#dc2626;" id="total-retenciones-display">$ 0,00</span>
                    </div>
                </div>

                <!-- Resumen final -->
                <div style="background:#f0fdf4; border-radius:8px; padding:14px; border:2px solid #86efac; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.85rem; color:#166534;">Total Facturado</div>
                            <div style="font-weight:bold; font-size:1.1rem;" id="resumen-total-fact">$ 0,00</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:0.85rem; color:#dc2626;">Retenciones</div>
                            <div style="font-weight:bold; font-size:1.1rem; color:#dc2626;" id="resumen-retenciones">$ 0,00</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.85rem; color:#166534;">Neto a Cobrar</div>
                            <div style="font-weight:bold; font-size:1.3rem; color:#16a34a;" id="resumen-neto">$ 0,00</div>
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
                <button type="button" class="btn-secondary" onclick="cerrarModalCobro()" style="padding:10px 18px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#27ae60; border:none; padding:10px 18px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-check-double"></i> Confirmar Cobro
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($autoCobrarViajeId > 0): ?>
<?php
    $stmt = $pdo->prepare("SELECT id, ctg_nro, carta_porte_nro, otros_docs, total_flete_neto FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1 AND estado = 'facturado'");
    $stmt->execute([$autoCobrarViajeId, $active_company_id]);
    $autoViaje = $stmt->fetch();
?>
<?php if ($autoViaje): 
    $autoLabel = !empty($autoViaje['ctg_nro']) ? ('CTG ' . $autoViaje['ctg_nro']) :
                 (!empty($autoViaje['carta_porte_nro']) ? ('CP ' . $autoViaje['carta_porte_nro']) :
                 (!empty($autoViaje['otros_docs']) ? $autoViaje['otros_docs'] : ('Viaje #' . $autoViaje['id'])));
    $autoNeto = (float)($autoViaje['total_flete_neto'] ?? 0);
    $autoTotalFact = $autoNeto * 1.21;
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        abrirModalCobro(<?= (int)$autoViaje['id'] ?>, '<?= htmlspecialchars($autoLabel, ENT_QUOTES) ?>', <?= number_format($autoTotalFact, 2, '.', '') ?>, <?= number_format($autoNeto, 2, '.', '') ?>);
    }, 300);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script>
// ─── VARIABLES GLOBALES ──────────────────────────
let montoTotalFacturado = 0;

// ─── ABRIR MODAL COBRO ──────────────────────────
function abrirModalCobro(viajeId, label, totalFacturado, neto) {
    montoTotalFacturado = totalFacturado;
    document.getElementById('cobro-viaje-id').value = viajeId;
    document.getElementById('cobro-monto-total').value = totalFacturado;
    document.getElementById('cobro-viaje-label').textContent = label;
    document.getElementById('cobro-total-display').textContent = '$ ' + totalFacturado.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('resumen-total-fact').textContent = '$ ' + totalFacturado.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Resetear retenciones
    const container = document.getElementById('retenciones-container');
    container.innerHTML = '';
    agregarRetencion();
    
    // Resetear medios de cobro (múltiples)
    const mediosContainer = document.getElementById('medios-container');
    mediosContainer.innerHTML = '';
    agregarMedioCobro();
    
    // Ocultar campos de cheque
    document.getElementById('cheque-fields').style.display = 'none';
    
    calcularNetoCobro();
    openModal('modal-cobro');
}

function cerrarModalCobro() {
    closeModal('modal-cobro');
}

// ─── TOGGLE CHEQUE FIELDS ───────────────────────
function toggleChequeFields() {
    const medio = document.getElementById('cobro-medio').value;
    const chequeFields = document.getElementById('cheque-fields');
    chequeFields.style.display = (medio === 'cheque') ? 'block' : 'none';
}

// ─── AGREGAR RETENCIÓN ──────────────────────────
function agregarRetencion() {
    const container = document.getElementById('retenciones-container');
    const fila = document.createElement('div');
    fila.className = 'retencion-fila';
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
            <input type="number" step="0.01" min="0" name="retencion_monto[]" class="input-field retencion-monto" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularNetoCobro()" onkeyup="calcularNetoCobro()">
        </div>
        <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularNetoCobro();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(fila);
}

// ─── AGREGAR MEDIO DE COBRO ─────────────────────
function agregarMedioCobro() {
    const container = document.getElementById('medios-container');
    const fila = document.createElement('div');
    fila.className = 'medio-fila';
    fila.style.cssText = 'display:grid; grid-template-columns: 1.5fr 1fr 1fr 30px; gap:8px; margin-bottom:6px; align-items:end;';
    fila.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Medio</label>
            <select name="medios_cobro[]" class="input-field medio-select" style="width:100%; font-size:0.85rem;" onchange="toggleChequeFieldsMedio(this)">
                <option value="efectivo">💵 Efectivo</option>
                <option value="transferencia">🏦 Transferencia</option>
                <option value="cheque">📄 Cheque</option>
                <option value="mercadopago">📱 Mercado Pago</option>
                <option value="otro">📦 Otro</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Importe $</label>
            <input type="number" step="0.01" min="0" name="medios_importe[]" class="input-field medio-importe" value="0" style="width:100%; font-size:0.85rem;" onchange="calcularNetoCobro()" onkeyup="calcularNetoCobro()">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-weight:bold; display:block; margin-bottom:3px; font-size:0.75rem;">Observaciones</label>
            <input type="text" name="medios_obs[]" class="input-field" placeholder="Opcional" style="width:100%; font-size:0.85rem;">
        </div>
        <button type="button" class="btn-primary btn-sm" onclick="this.parentElement.remove(); calcularNetoCobro();" style="background:#e74c3c; border:none; padding:6px 8px; font-size:0.75rem; cursor:pointer; margin-bottom:0;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(fila);
}

// ─── TOGGLE CHEQUE FIELDS MEDIO ─────────────────
function toggleChequeFieldsMedio(selectElement) {
    const medio = selectElement.value;
    const chequeFields = document.getElementById('cheque-fields');
    chequeFields.style.display = (medio === 'cheque') ? 'block' : 'none';
}

// ─── CALCULAR NETO A COBRAR ─────────────────────
function calcularNetoCobro() {
    const montos = document.querySelectorAll('.retencion-monto');
    let totalRet = 0;
    montos.forEach(input => {
        const val = parseFloat(input.value) || 0;
        totalRet += val;
    });
    
    // Calcular total de medios de cobro
    const mediosImportes = document.querySelectorAll('.medio-importe');
    let totalMedios = 0;
    mediosImportes.forEach(input => {
        const val = parseFloat(input.value) || 0;
        totalMedios += val;
    });
    
    document.getElementById('total-retenciones-display').textContent = '$ ' + totalRet.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('resumen-retenciones').textContent = '$ ' + totalRet.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('total-medios-display').textContent = '$ ' + totalMedios.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const neto = montoTotalFacturado - totalRet;
    document.getElementById('resumen-neto').textContent = '$ ' + neto.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>
