<?php
/**
 * Modulo de Detalle de Viaje - Trans Cargo Hub
 * Muestra informacion completa del viaje, gastos, adelantos y acciones por estado.
 * Spec: base.md seccion 4
 */

$mensaje = "";
$error = "";
$active_company_id = $_SESSION['active_company_id'] ?? 0;
$currentRole = $_SESSION['user_role'] ?? 'user';
$viaje_id = (int)($_GET['viaje_id'] ?? 0);

// ─── HELPERS ──────────────────────────────────────────────
function viajeOwner(PDO $pdo, int $id, int $tenantId, string $currentRole): bool {
    if ($currentRole === 'developer') return true;
    $stmt = $pdo->prepare("SELECT id FROM viajes WHERE id = ? AND transportista_id = ? AND activo = 1");
    $stmt->execute([$id, $tenantId]);
    return (bool)$stmt->fetchColumn();
}

// ─── VALIDAR QUE EL VIAJE EXISTA Y PERTENEZCA AL TENANT ──
if ($viaje_id <= 0 || !viajeOwner($pdo, $viaje_id, $active_company_id, $currentRole)) {
    echo '<div class="card" style="text-align:center; padding:60px 20px;">';
    echo '<i class="fas fa-exclamation-triangle fa-4x" style="color:#e74c3c; margin-bottom:20px;"></i>';
    echo '<h2>Viaje no encontrado</h2>';
    echo '<p style="opacity:0.7;">El viaje solicitado no existe o no pertenece a la empresa activa.</p>';
    echo '<a href="viajes" class="btn-primary" style="margin-top:20px; display:inline-block;"><i class="fas fa-arrow-left"></i> Volver a Viajes</a>';
    echo '</div>';
    return;
}

// ─── OBTENER DATOS DEL VIAJE ───────────────────────────
$sql = "SELECT v.*,
               c.razon_social as cliente_nombre,
               CONCAT(ch.apellido, ', ', ch.nombre) as chofer_nombre,
               ve.dominio as vehiculo_dominio,
               p.razon_social as pagador_nombre,
               co.razon_social as comisionista_nombre
        FROM viajes v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN choferes ch ON ch.id = v.chofer_id
        LEFT JOIN vehiculos ve ON ve.id = v.vehiculo_id
        LEFT JOIN clientes p ON p.id = v.pagador_id
        LEFT JOIN clientes co ON co.id = v.comisionista_id
        WHERE v.id = ? AND v.transportista_id = ? AND v.activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$viaje_id, $active_company_id]);
$viaje = $stmt->fetch();

if (!$viaje) {
    echo '<div class="card" style="text-align:center; padding:60px 20px;">';
    echo '<i class="fas fa-exclamation-triangle fa-4x" style="color:#e74c3c; margin-bottom:20px;"></i>';
    echo '<h2>Viaje no encontrado</h2>';
    echo '<p style="opacity:0.7;">El viaje solicitado no existe o fue eliminado.</p>';
    echo '<a href="viajes" class="btn-primary" style="margin-top:20px; display:inline-block;"><i class="fas fa-arrow-left"></i> Volver a Viajes</a>';
    echo '</div>';
    return;
}

// ─── PROCESAR ACCIONES POST ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─── AGREGAR GASTO ─────────────────────────────────
    if ($action === 'agregar_gasto') {
        $tipo_gasto   = $_POST['tipo_gasto'] ?? '';
        $monto        = (float)($_POST['monto'] ?? 0);
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $pagado_por   = $_POST['pagado_por'] ?? 'empresa';
        $fecha        = $_POST['fecha'] ?? date('Y-m-d');

        if (empty($tipo_gasto) || $monto <= 0) {
            $error = "Tipo de gasto y monto son obligatorios.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO viajes_gastos (viaje_id, tipo_gasto, monto, descripcion, pagado_por, fecha, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$viaje_id, $tipo_gasto, $monto, $descripcion ?: null, $pagado_por, $fecha]);
                $mensaje = "Gasto registrado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al registrar gasto: " . $e->getMessage();
            }
        }
    }

    // ─── AGREGAR ADELANTO ─────────────────────────────
    if ($action === 'agregar_adelanto') {
        $monto_adelanto = (float)($_POST['monto_adelanto'] ?? 0);
        $metodo_pago    = trim($_POST['metodo_pago'] ?? '');
        $fecha_adelanto = $_POST['fecha_adelanto'] ?? date('Y-m-d');

        if ($monto_adelanto <= 0) {
            $error = "El monto del adelanto debe ser mayor a 0.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO viajes_adelantos (viaje_id, monto, fecha, metodo_pago, activo) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$viaje_id, $monto_adelanto, $fecha_adelanto, $metodo_pago ?: null]);

                // NOTA CONTABLE:
                // El adelanto cargado al viaje NO se registra como movimiento en la cuenta corriente del chofer.
                // Se registra recién en la descarga cuando se calcula el SOBRANTE del adelanto respecto a los gastos pagados por adelanto.
                // (Evita registros falsos: adelanto -> se usa para gastos -> luego podría no sobrar nada).

                $mensaje = "Adelanto registrado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al registrar adelanto: " . $e->getMessage();
            }
        }
    }

    // ─── REGISTRAR DESCARGA ───────────────────────────
    if ($action === 'descargar') {
        $id = $viaje_id;

        $usar_bruto_tara = isset($_POST['usar_bruto_tara']) ? (int)$_POST['usar_bruto_tara'] : 0;

        // Modo por defecto: se ingresa el neto descargado directamente.
        // Modo alternativo (cuando el usuario lo activa): se ingresa bruto y tara y se calcula el neto.
        $peso_neto_real = (float)($_POST['peso_neto_real'] ?? 0);
        $peso_bruto_real = 0;
        $peso_tara_real  = 0;

        if ($viaje['estado'] !== 'en_viaje') {
            $error = "Solo se puede descargar un viaje en estado 'En Viaje'.";
        } elseif ($peso_neto_real <= 0) {
            // Validación del modo por defecto (neta)
            $error = "El Peso Neto descargado debe ser mayor a 0.";
        } elseif ($usar_bruto_tara === 1) {
            $peso_bruto_real = (float)($_POST['peso_bruto_real'] ?? 0);
            $peso_tara_real  = (float)($_POST['peso_tara_real'] ?? 0);

            if ($peso_bruto_real <= 0) {
                $error = "El Peso Bruto real debe ser mayor a 0.";
            } elseif ($peso_tara_real < 0) {
                $error = "La Tara no puede ser negativa.";
            } else {
                $peso_neto_real = max(0, $peso_bruto_real - $peso_tara_real);
            }
        }

        if (!$error) {
            // Preservar consistencia del TN Estimado vs Descargado.
            // - Si se cargó Bruto/Tara: usamos lo ingresado.
            // - Si se cargó Solo Neto: NO queremos sobrescribir el peso_bruto estimado (TN Est.).
            //   En ese caso calculamos la tara necesaria para que el neto (GENERATED) quede = peso_neto_real.
            if ($usar_bruto_tara !== 1) {
                $peso_bruto_real = (float)($viaje['peso_bruto'] ?? 0);
                if ($peso_bruto_real > 0) {
                    $peso_tara_real = max(0, $peso_bruto_real - $peso_neto_real);
                } else {
                    // fallback: si por algún motivo el peso_bruto estimado no existe, usamos tara 0
                    $peso_tara_real = 0;
                }
            }

            $tarifa = (float)$viaje['tarifa_tonelada'];
            $chofer_pct = (float)$viaje['chofer_porcentaje'];
            $total_flete_bruto = ($peso_bruto_real > 0) ? ($peso_bruto_real * $tarifa) : ($peso_neto_real * $tarifa);
            $total_flete_neto  = $peso_neto_real * $tarifa;

            try {
                $pdo->prepare("UPDATE viajes SET 
                    peso_bruto = ?, peso_tara = ?,
                    total_flete_bruto = ?, total_flete_neto = ?,
                    estado = 'descargado'
                    WHERE id = ? AND transportista_id = ? AND activo = 1")
                    ->execute([$peso_bruto_real, $peso_tara_real, $total_flete_bruto, $total_flete_neto, $id, $active_company_id]);

                // Impacto contable: registrar en cuenta corriente del chofer
                if (!empty($viaje['chofer_porcentaje']) && $viaje['chofer_porcentaje'] > 0 && !empty($viaje['chofer_id'])) {
                    $ganancia_chofer = $total_flete_neto * ($chofer_pct / 100);
                    // Detalle del viaje para que el movimiento muestre CTG o CP (nunca solo número de viaje)
                    $detalle_ref_liq = '';
                    if (!empty($viaje['ctg_nro'])) {
                        $detalle_ref_liq = 'CTG ' . $viaje['ctg_nro'];
                    } elseif (!empty($viaje['carta_porte_nro'])) {
                        $detalle_ref_liq = 'CP ' . $viaje['carta_porte_nro'];
                    } elseif (!empty($viaje['otros_docs'])) {
                        $detalle_ref_liq = $viaje['otros_docs'];
                    } else {
                        $detalle_ref_liq = 'Viaje #' . $id;
                    }

                    $stmt_pago = $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, 'liquidacion', ?)");
                    $stmt_pago->execute([$viaje['chofer_id'], date('Y-m-d'), $ganancia_chofer, "Liquidación de {$detalle_ref_liq}"]);

                    // Segundo movimiento: si el adelanto alcanzó para gastos, el sobrante queda como adelanto en la cta cte del chofer.
                    $stmtTA = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM viajes_adelantos WHERE viaje_id = ? AND activo = 1");
                    $stmtTA->execute([$id]);
                    $total_adelantos = (float)$stmtTA->fetchColumn();

                    $stmtG = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM viajes_gastos WHERE viaje_id = ? AND activo = 1 AND pagado_por = 'adelanto'");
                    $stmtG->execute([$id]);
                    $gastos_pagados_por_adelanto = (float)$stmtG->fetchColumn();

                    $sobrante = $total_adelantos - $gastos_pagados_por_adelanto;

                    if ($sobrante > 0) {
                        // Referencia del viaje para que coincida visualmente con otros movimientos.
                        $detalle_ref = '';
                        if (!empty($viaje['ctg_nro'])) {
                            $detalle_ref = 'CTG ' . $viaje['ctg_nro'];
                        } elseif (!empty($viaje['carta_porte_nro'])) {
                            $detalle_ref = 'CP ' . $viaje['carta_porte_nro'];
                        } elseif (!empty($viaje['otros_docs'])) {
                            $detalle_ref = $viaje['otros_docs'];
                        } else {
                            $detalle_ref = 'Viaje #' . $id;
                        }

                        $stmt_sobrante = $pdo->prepare("INSERT INTO chofer_pagos (chofer_id, fecha, monto, tipo, detalle) VALUES (?, ?, ?, 'adelanto', ?)");
                        $stmt_sobrante->execute([
                            $viaje['chofer_id'],
                            date('Y-m-d'),
                            $sobrante,
                            "Sobrante Adelanto {$detalle_ref}"
                        ]);
                    }
                }

                $mensaje = "Descarga registrada exitosamente. Peso Neto: " . number_format($peso_neto_real, 2, ',', '.') . " TN.";
            } catch (PDOException $e) {
                $error = "Error al registrar descarga: " . $e->getMessage();
            }
        }
    }

    // ─── FACTURAR VIAJE ────────────────────────────────
    if ($action === 'facturar') {
        $factura_nro    = trim($_POST['factura_nro'] ?? '');
        $factura_fecha  = $_POST['factura_fecha'] ?? date('Y-m-d');

        if (empty($factura_nro)) {
            $error = "El número de factura es obligatorio.";
        } else {
            try {
                $pdo->prepare("UPDATE viajes SET factura_nro = ?, factura_fecha = ?, estado = 'facturado' WHERE id = ? AND transportista_id = ?")
                    ->execute([$factura_nro, $factura_fecha, $viaje_id, $active_company_id]);
                $mensaje = "Viaje facturado exitosamente. Factura N°: " . htmlspecialchars($factura_nro);
            } catch (PDOException $e) {
                $error = "Error al facturar: " . $e->getMessage();
            }
        }
    }

    // ─── COBRAR VIAJE ──────────────────────────────────
    if ($action === 'cobrar') {
        $fecha_cobro    = $_POST['fecha_cobro'] ?? date('Y-m-d');
        $medio_cobro    = trim($_POST['medio_cobro'] ?? '');
        $monto_cobro    = (float)($_POST['monto_cobro'] ?? 0);
        $retenciones    = (float)($_POST['retenciones'] ?? 0);
        $observaciones_cobro = trim($_POST['observaciones_cobro'] ?? '');

        if ($monto_cobro <= 0) {
            $error = "El monto cobrado debe ser mayor a 0.";
        } else {
            try {
                $pdo->prepare("UPDATE viajes SET fecha_cobro = ?, estado = 'cobrado', observaciones = CASE WHEN observaciones IS NULL OR observaciones = '' THEN ? ELSE CONCAT(observaciones, ' | ', ?) END WHERE id = ? AND transportista_id = ?")
                    ->execute([$fecha_cobro, $observaciones_cobro ?: null, $observaciones_cobro ?: null, $viaje_id, $active_company_id]);
                $mensaje = "Cobro registrado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al registrar cobro: " . $e->getMessage();
            }
        }
    }

    // ─── BORRAR GASTO ──────────────────────────────────
    if ($action === 'borrar_gasto') {
        $gasto_id = (int)($_POST['gasto_id'] ?? 0);
        if ($gasto_id > 0) {
            try {
                $pdo->prepare("UPDATE viajes_gastos SET activo = 0 WHERE id = ? AND viaje_id = ?")->execute([$gasto_id, $viaje_id]);
                $mensaje = "Gasto eliminado.";
            } catch (PDOException $e) {
                $error = "Error al eliminar gasto: " . $e->getMessage();
            }
        }
    }

    // ─── BORRAR ADELANTO ───────────────────────────────
    if ($action === 'borrar_adelanto') {
        $adelanto_id = (int)($_POST['adelanto_id'] ?? 0);
        if ($adelanto_id > 0) {
            try {
                // 1) Desactivar el registro (consistente con modelo actual de viajes_adelantos)
                $pdo->prepare("UPDATE viajes_adelantos SET activo = 0 WHERE id = ? AND viaje_id = ?")
                    ->execute([$adelanto_id, $viaje_id]);

                // 2) Revertir el movimiento en la CTA cte del chofer (chofer_pagos no tiene 'activo')
                //    Buscamos el adelanto para obtener monto/fecha/metodo y así identificar la fila creada en chofer_pagos.
                $stmtA = $pdo->prepare("SELECT * FROM viajes_adelantos WHERE id = ? AND viaje_id = ? LIMIT 1");
                $stmtA->execute([$adelanto_id, $viaje_id]);
                $a = $stmtA->fetch();

                if (!empty($a) && !empty($viaje['chofer_id'])) {
                    $detalle_ref = '';
                    if (!empty($viaje['ctg_nro'])) {
                        $detalle_ref = 'CTG ' . $viaje['ctg_nro'];
                    } elseif (!empty($viaje['carta_porte_nro'])) {
                        $detalle_ref = 'CP ' . $viaje['carta_porte_nro'];
                    } elseif (!empty($viaje['otros_docs'])) {
                        $detalle_ref = $viaje['otros_docs'];
                    } else {
                        $detalle_ref = 'Viaje #' . $viaje_id;
                    }

                    $detalle_mov = "Adelanto {$detalle_ref}";

                    $sql_delete = "DELETE FROM chofer_pagos
                                   WHERE chofer_id = ?
                                     AND tipo = 'adelanto'
                                     AND fecha = ?
                                     AND monto = ?
                                     AND detalle = ?";
                    $pdo->prepare($sql_delete)->execute([
                        $viaje['chofer_id'],
                        $a['fecha'],
                        $a['monto'],
                        $detalle_mov
                    ]);
                }

                $mensaje = "Adelanto eliminado.";
            } catch (PDOException $e) {
                $error = "Error al eliminar adelanto: " . $e->getMessage();
            }
        }
    }

    // Refrescar datos del viaje después de cambios
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$viaje_id, $active_company_id]);
    $viaje = $stmt->fetch();
    }

// ─── OBTENER GASTOS Y ADELANTOS ────────────────────────

$gastos = [];
$stmt = $pdo->prepare("SELECT * FROM viajes_gastos WHERE viaje_id = ? AND activo = 1 ORDER BY fecha DESC, id DESC");
$stmt->execute([$viaje_id]);
$gastos = $stmt->fetchAll();

$total_gastos_empresa = 0;
$total_adelantos = 0;
foreach ($gastos as $g) {
    if ($g['pagado_por'] === 'empresa') $total_gastos_empresa += (float)$g['monto'];
}

$adelantos = [];
$stmt = $pdo->prepare("SELECT * FROM viajes_adelantos WHERE viaje_id = ? AND activo = 1 ORDER BY fecha DESC, id DESC");
$stmt->execute([$viaje_id]);
$adelantos = $stmt->fetchAll();

foreach ($adelantos as $a) {
    $total_adelantos += (float)$a['monto'];
}

// ─── CALCULAR DIFERENCIAL (TN) ──────────────────────────
// Diferencia entre el peso estimado y el descargado (neto real).
$diferencial_tn = 0;
    if ($viaje['estado'] === 'descargado' || $viaje['estado'] === 'facturado' || $viaje['estado'] === 'cobrado' || $viaje['estado'] === 'liquidado' || $viaje['estado'] === 'en_viaje') {

    // peso_estimado guarda la TN estimada original (separada de la TN bruta usada en descarga)
    $peso_estimado_tn = (float)($viaje['peso_estimado'] ?? $viaje['peso_bruto']);

    // peso_neto es GENERATED ALWAYS AS (peso_bruto - peso_tara)
    $peso_descargado_neto_tn = (float)$viaje['peso_neto'];
    $diferencial_tn = $peso_descargado_neto_tn - $peso_estimado_tn;
}


// Badge de estado
$estado_badge = match($viaje['estado']) {
    'en_viaje'   => '<span class="badge" style="background:#f39c12; color:#fff; font-size:0.9rem; padding:6px 14px;">🚛 En Viaje</span>',
    'descargado' => '<span class="badge" style="background:#3498db; color:#fff; font-size:0.9rem; padding:6px 14px;">📦 Descargado</span>',
    'facturado'  => '<span class="badge" style="background:#9b59b6; color:#fff; font-size:0.9rem; padding:6px 14px;">📄 Facturado</span>',
    'cobrado'    => '<span class="badge" style="background:#27ae60; color:#fff; font-size:0.9rem; padding:6px 14px;">💰 Cobrado</span>',
    'liquidado'  => '<span class="badge" style="background:#95a5a6; color:#fff; font-size:0.9rem; padding:6px 14px;">✅ Liquidado</span>',
    default      => '<span class="badge">' . htmlspecialchars($viaje['estado']) . '</span>'
};
?>
<div id="viajes_detalle-page" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">

    <div>
        <a href="viajes" style="text-decoration:none; color:var(--accent); margin-bottom:8px; display:inline-block;">
            <i class="fas fa-arrow-left"></i> Volver a Viajes
        </a>
        <?php
        // Determinar identificador visible del viaje: CTG > CP > Otros Docs > ID
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
        <h1 style="margin:4px 0 0 0;">Detalle de <?= $viaje_label ?></h1>
        <span><?= $estado_badge ?></span>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php if ($viaje['estado'] === 'en_viaje'): ?>
        <button onclick="openModal('modal-descarga')" class="btn-primary" style="background:#27ae60;">
            <i class="fas fa-weight-hanging"></i> Registrar Descarga
        </button>
        <?php endif; ?>

        <?php if ($viaje['estado'] === 'descargado'): ?>
        <button onclick="openModal('modal-facturar')" class="btn-primary" style="background:#9b59b6;">
            <i class="fas fa-file-invoice"></i> Facturar
        </button>
        <?php endif; ?>
        <?php if ($viaje['estado'] === 'facturado'): ?>
        <button onclick="prepararCobro()" class="btn-primary" style="background:#27ae60;">
            <i class="fas fa-hand-holding-usd"></i> Registrar Cobro
        </button>
        <?php endif; ?>
        <!-- <button onclick="openModal('modal-gasto')" class="btn-secondary">
            <i class="fas fa-plus-circle"></i> Gasto
        </button>
        <button onclick="openModal('modal-adelanto')" class="btn-secondary">
            <i class="fas fa-hand-holding-usd"></i> Adelanto
        </button> -->
    </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DESCARGA (usando estilos de la página)
     ══════════════════════════════════════════════════════════ -->
<div id="modal-descarga" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header" style="background:linear-gradient(135deg, #2c3e50, #34495e); color:#fff; padding:12px 16px; border-radius:10px 10px 0 0;">
            <h3 style="margin:0; font-size:1.1rem;">
                <i class="fas fa-weight-hanging" style="margin-right:8px;"></i> Registrar Descarga
            </h3>
            <span class="close-modal" onclick="closeModal('modal-descarga')" style="color:#fff; font-size:1.2rem;">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body" style="padding:16px;">
                <input type="hidden" name="action" value="descargar">



                <div style="margin-bottom:12px;">
                    <div class="card" style="margin:0; background:#f8f9fa; border:1px solid #e3e3e3;">
                        <div style="padding:12px;">
                            <p style="margin:0 0 10px 0; opacity:0.85; font-size:0.95rem;">
                                Por defecto registrás <strong>solo el Neto</strong>. Activá la opción si querés cargar <strong>Bruto y Tara</strong>.
                            </p>

                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none;">
                                <input type="checkbox" id="descarga_usar_bruto_tara" name="usar_bruto_tara" value="1" onchange="toggleDescargaBrutoTara()">
                                <span style="font-weight:bold;">Cargar Bruto y Tara</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-weight:bold;">Peso Neto Descargado (TN) *</label>
                    <input type="number" step="0.01" min="0.01" name="peso_neto_real" class="input-field" required placeholder="0.00" style="width:100%;">
                </div>

                <div class="form-group" id="descarga_bruto_tara_block" style="margin-top:10px; display:none;">
                    <label style="font-weight:bold;">Peso Bruto Real (TN)</label>
                    <input type="number" step="0.01" min="0.01" name="peso_bruto_real" class="input-field" placeholder="0.00" style="width:100%;">

                    <div style="margin-top:10px;">
                        <label style="font-weight:bold;">Tara (TN)</label>
                        <input type="number" step="0.01" min="0" name="peso_tara_real" class="input-field" value="0" placeholder="0.00" style="width:100%;">
                    </div>
                </div>

                <script>
                    function toggleDescargaBrutoTara() {
                        const cb = document.getElementById('descarga_usar_bruto_tara');
                        const block = document.getElementById('descarga_bruto_tara_block');
                        if (!cb || !block) return;
                        const enabled = cb.checked;
                        block.style.display = enabled ? 'block' : 'none';
                    }
                    // Asegurar estado inicial: por defecto SIEMPRE solo neto
                    document.addEventListener('DOMContentLoaded', () => {
                        toggleDescargaBrutoTara();
                    });
                </script>

            </div>
            <div class="modal-footer" style="padding:14px 16px; display:flex; justify-content:space-between; gap:12px;">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-descarga')" style="background:#2c3e50; color:#fff; border:none; border-radius:10px; padding:10px 14px;">Cancelar</button>

                <button type="submit" class="btn-primary" style="background:#27ae60; border:none; border-radius:10px; padding:10px 14px;">
                    <i class="fas fa-check" style="margin-right:8px;"></i> Confirmar Descarga
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     TARJETA: INFORMACIÓN DEL VIAJE (ESTILO VISUAL MEJORADO)
     ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px; position:relative; overflow:hidden;">
    <!-- Barra decorativa superior -->
    <div style="height:6px; background:linear-gradient(90deg, #2c3e50, #3498db, #2ecc71, #e67e22); position:absolute; top:0; left:0; right:0;"></div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px; margin-top:8px;">
        <h3 style="margin:0;">
            <span style="background:linear-gradient(135deg, #2c3e50, #34495e); color:#fff; padding:6px 14px; border-radius:8px; font-size:0.95rem;">
                <i class="fas fa-truck"></i> Datos del Viaje
            </span>
        </h3>
    </div>

    <!-- Grid principal - 3 columnas: Cliente/Ruta, Flota, Finanzas -->
    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px;">

        <!-- COLUMNA 1: Cliente y Ruta -->
        <div style="background:linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); border-radius:12px; padding:16px; border:1px solid #bbdefb;">
            <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#1976d2; font-weight:bold; margin-bottom:10px;">
                <i class="fas fa-user-tie"></i> CLIENTE Y RUTA
            </div>
            <div style="margin-bottom:10px;">
                <div style="font-size:0.75rem; color:#666; margin-bottom:1px;">Cliente</div>
                <div style="font-size:1.05rem; font-weight:bold; color:#0d47a1;">
                    <i class="fas fa-building" style="opacity:0.5; margin-right:4px;"></i>
                    <?= htmlspecialchars($viaje['cliente_nombre'] ?? '-') ?>
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <div style="flex:1; min-width:100px;">
                    <div style="font-size:0.75rem; color:#666;">Producto</div>
                    <div style="font-weight:bold; color:#333;">
                        <i class="fas fa-cube" style="color:#f39c12; margin-right:4px;"></i>
                        <?= htmlspecialchars($viaje['producto'] ?? '-') ?>
                    </div>
                </div>
                <div style="flex:1; min-width:100px;">
                    <div style="font-size:0.75rem; color:#666;">Fecha Carga</div>
                    <div style="font-weight:bold; color:#333;">
                        <i class="far fa-calendar-alt" style="color:#e74c3c; margin-right:4px;"></i>
                        <?= htmlspecialchars(formatDate($viaje['fecha_carga'])) ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:8px; background:#fff; border-radius:8px; padding:10px; border:1px dashed #90caf9;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="text-align:center; flex:1;">
                        <div style="font-size:0.65rem; color:#666; text-transform:uppercase;">Origen</div>
                        <div style="font-weight:bold; font-size:0.9rem; color:#2e7d32;">
                            <i class="fas fa-play-circle" style="color:#4caf50;"></i>
                            <?= htmlspecialchars($viaje['origen']) ?>
                        </div>
                    </div>
                    <div style="color:#bbb; font-size:1.2rem;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div style="text-align:center; flex:1;">
                        <div style="font-size:0.65rem; color:#666; text-transform:uppercase;">Destino</div>
                        <div style="font-weight:bold; font-size:0.9rem; color:#c62828;">
                            <i class="fas fa-flag-checkered" style="color:#f44336;"></i>
                            <?= htmlspecialchars($viaje['destino']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA 2: Flota (Vehículo, Chofer) -->
        <div style="background:linear-gradient(135deg, #e8f5e9 0%, #f5f5f5 100%); border-radius:12px; padding:16px; border:1px solid #c8e6c9;">
            <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#388e3c; font-weight:bold; margin-bottom:10px;">
                <i class="fas fa-truck"></i> FLOTA
            </div>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px; background:#fff; border-radius:8px; padding:10px; border:1px solid #e0e0e0;">
                <div style="background:#e8f5e9; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#2e7d32; flex-shrink:0;">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#666;"><?= htmlspecialchars($viaje['vehiculo_dominio'] ?? '-') ?></div>
                    <div style="font-weight:bold; font-size:0.95rem;">
                        <?php if (!empty($viaje['acoplado'])): ?>
                            <span style="color:#555;">+ <i class="fas fa-trailer"></i> <?= htmlspecialchars($viaje['acoplado']) ?></span>
                        <?php else: ?>
                            <span style="color:#999; font-style:italic;">Sin acoplado</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px; background:#fff; border-radius:8px; padding:10px; border:1px solid #e0e0e0;">
                <div style="background:#fff3e0; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#e65100; flex-shrink:0;">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:0.75rem; color:#666;">Chofer</div>
                    <div style="font-weight:bold; font-size:0.95rem; color:#333;">
                        <?= htmlspecialchars($viaje['chofer_nombre'] ?? '<span style="color:#999; font-style:italic;">Sin asignar</span>') ?>
                    </div>
                </div>
                <?php if ((float)$viaje['chofer_porcentaje'] > 0): ?>
                <div style="background:#e8f5e9; padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:bold; color:#2e7d32; border:1px solid #a5d6a7; white-space:nowrap;">
                    <i class="fas fa-percentage"></i> <?= number_format((float)$viaje['chofer_porcentaje'], 1) ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLUMNA 3: Finanzas Rápidas -->
        <div style="background:linear-gradient(135deg, #fff3e0 0%, #f5f5f5 100%); border-radius:12px; padding:16px; border:1px solid #ffe0b2;">
            <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:#e65100; font-weight:bold; margin-bottom:10px;">
                <i class="fas fa-file-invoice-dollar"></i> FINANZAS
            </div>
            
            <!-- Pagador -->
            <div style="margin-bottom:8px; background:#fff; border-radius:8px; padding:8px 10px; border:1px solid #e0e0e0;">
                <div style="font-size:0.7rem; color:#666;">
                    <i class="fas fa-user-check" style="color:#16a085;"></i> Pagador de Flete
                </div>
                <div style="font-weight:bold; font-size:0.9rem; color:#1a5276;">
                    <?= htmlspecialchars($viaje['pagador_nombre'] ?? '-') ?>
                </div>
            </div>

            <!-- Tarifas en badges horizontales -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
                <div style="background:#e8f5e9; border-radius:8px; padding:8px; text-align:center; border:1px solid #c8e6c9;">
                    <div style="font-size:0.65rem; color:#666; text-transform:uppercase;">TN Est.</div>
                    <div style="font-weight:bold; font-size:1rem; color:#2e7d32;">
                        <?= number_format((float)($viaje['peso_estimado'] ?? $viaje['peso_bruto']), 1, ',', '.') ?>
                    </div>
                </div>

                <div style="background:#e3f2fd; border-radius:8px; padding:8px; text-align:center; border:1px solid #bbdefb;">
                    <div style="font-size:0.65rem; color:#666; text-transform:uppercase;">Tarifa</div>
                    <div style="font-weight:bold; font-size:1rem; color:#1565c0;">
                        $<?= number_format((float)$viaje['tarifa_tonelada'], 0, ',', '.') ?>
                    </div>
                </div>
            </div>

            <!-- Total Neto destacado -->
            <div style="margin-top:8px; background:linear-gradient(135deg, #1b5e20, #2e7d32); border-radius:10px; padding:10px 14px; text-align:center; box-shadow:0 2px 8px rgba(46,125,50,0.2);">
                <div style="font-size:0.65rem; color:rgba(255,255,255,0.7); text-transform:uppercase; letter-spacing:1px;">
                    <i class="fas fa-calculator"></i> Total Flete Neto
                </div>
                <div style="font-size:1.4rem; font-weight:bold; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.1);">
                    $ <?= number_format((float)$viaje['total_flete_neto'], 2, ',', '.') ?>
                </div>
            </div>

            <!-- Diferencia TN (Estimado vs Descargado) -->
            <?php if (isset($diferencial_tn) && ((float)$viaje['peso_neto'] > 0 || (float)($viaje['peso_estimado'] ?? 0) > 0)): ?>

                <?php

                    $dtn = (float)$diferencial_tn;
                    $color = ($dtn >= 0) ? '#27ae60' : '#e74c3c';
                    $signo = ($dtn >= 0) ? '+' : '-';
                    $abs = abs($dtn);
                ?>
                <div style="margin-top:8px; background:#fff; border-radius:10px; padding:10px 14px; text-align:center; border:1px solid rgba(0,0,0,0.08);">
                    <div style="font-size:0.65rem; color:#666; text-transform:uppercase; letter-spacing:1px;">
                        <i class="fas fa-weight"></i> Diferencia TN
                    </div>
                    <div style="font-size:1.25rem; font-weight:bold; color:<?= $color ?>;">
                        <?= $signo ?><?= number_format($abs, 2, ',', '.') ?> TN
                    </div>
                    <div style="font-size:0.75rem; color:#777; margin-top:2px;">
                        Est: <?= number_format((float)$viaje['peso_bruto'], 2, ',', '.') ?> TN → Desc: <?= number_format((float)$viaje['peso_neto'], 2, ',', '.') ?> TN
                    </div>
                </div>
            <?php endif; ?>


            <!-- Documentación -->
            <?php 
            $docs_list = [];
            if (!empty($viaje['ctg_nro']))          $docs_list[] = '<span style="background:#e8eaf6; color:#283593; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold;">CTG: ' . htmlspecialchars($viaje['ctg_nro']) . '</span>';
            if (!empty($viaje['carta_porte_nro']))  $docs_list[] = '<span style="background:#fce4ec; color:#c62828; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold;">CP: ' . htmlspecialchars($viaje['carta_porte_nro']) . '</span>';
            if (!empty($viaje['otros_docs']))       $docs_list[] = '<span style="background:#fff3e0; color:#e65100; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold;">📋 ' . htmlspecialchars($viaje['otros_docs']) . '</span>';
            ?>
            <?php if (!empty($docs_list)): ?>
            <div style="margin-top:8px; display:flex; gap:4px; flex-wrap:wrap; justify-content:center;">
                <?= implode(' ', $docs_list) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TARJETA: DOCUMENTACIÓN Y COMISIÓN
     ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0; border-bottom:2px solid var(--accent); padding-bottom:10px;">
        <i class="fas fa-file-alt"></i> Documentación y Comisión
    </h3>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div>
            <strong>CTG N°:</strong><br>
            <span><?= htmlspecialchars($viaje['ctg_nro'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Carta Porte N°:</strong><br>
            <span><?= htmlspecialchars($viaje['carta_porte_nro'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Otros Docs:</strong><br>
            <span><?= htmlspecialchars($viaje['otros_docs'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Comisión:</strong><br>
            <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $viaje['comision_tipo'] ?? 'ninguna'))) ?></span>
        </div>
        <div>
            <strong>Valor Comisión:</strong><br>
            <span>$ <?= number_format((float)$viaje['comision_valor'], 2, ',', '.') ?></span>
        </div>
        <div>
            <strong>Comisionista:</strong><br>
            <span><?= htmlspecialchars($viaje['comisionista_nombre'] ?? '-') ?></span>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     TABLA: GASTOS
     ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <h3 style="margin:0;"><i class="fas fa-receipt"></i> Gastos del Viaje</h3>
        <button onclick="openModal('modal-gasto')" class="btn-primary btn-sm">
            <i class="fas fa-plus"></i> Agregar Gasto
        </button>
    </div>

    <?php if (empty($gastos)): ?>
        <p style="text-align:center; padding:20px; opacity:0.5;">No se registraron gastos para este viaje.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Pagado por</th>
                    <th style="text-align:right;">Monto</th>
                    <th style="text-align:center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($gastos as $g):
                    $badge_pagado = match($g['pagado_por']) {
                        'empresa' => '<span class="badge" style="background:#3498db; color:#fff;">Empresa</span>',
                        'adelanto' => '<span class="badge" style="background:#e67e22; color:#fff;">Adelanto</span>',
                        'descuento_flete' => '<span class="badge" style="background:#e74c3c; color:#fff;">Desc. Flete</span>',
                        default => htmlspecialchars($g['pagado_por'])
                    };
                    $badge_tipo = match($g['tipo_gasto']) {
                        'combustible'      => '<span class="badge" style="background:#2c3e50; color:#fff;">⛽ Combustible</span>',
                        'peaje'            => '<span class="badge" style="background:#16a085; color:#fff;">🛣 Peaje</span>',
                        'playa'            => '<span class="badge" style="background:#8e44ad; color:#fff;">🏗 Playa</span>',
                        'reparacion_ruta'  => '<span class="badge" style="background:#c0392b; color:#fff;">🔧 Reparación</span>',
                        'otros'            => '<span class="badge" style="background:#7f8c8d; color:#fff;">📋 Otros</span>',
                        default            => '<span class="badge">' . htmlspecialchars($g['tipo_gasto']) . '</span>'
                    };
                ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($g['fecha'])) ?></td>
                    <td><?= $badge_tipo ?></td>
                    <td><?= htmlspecialchars($g['descripcion'] ?? '-') ?></td>
                    <td><?= $badge_pagado ?></td>
                    <td style="text-align:right; font-weight:bold;">$ <?= number_format((float)$g['monto'], 2, ',', '.') ?></td>
                    <td style="text-align:center;">
                        <form method="POST" style="display:inline;" onsubmit="return appConfirm('¿Eliminar este gasto?', function(){ this.submit(); }.bind(this), 'Eliminar Gasto', this)" >

                            <input type="hidden" name="action" value="borrar_gasto">
                            <input type="hidden" name="gasto_id" value="<?= (int)$g['id'] ?>">
                            <button type="submit" title="Eliminar gasto" style="background:none; border:none; color:#e74c3c; cursor:pointer;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="4" style="text-align:right;">Total Gastos:</td>
                    <td style="text-align:right;">$ <?= number_format(array_sum(array_column($gastos, 'monto')), 2, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     TABLA: ADELANTOS
     ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <h3 style="margin:0;"><i class="fas fa-hand-holding-usd"></i> Adelantos al Chofer</h3>
        <button onclick="openModal('modal-adelanto')" class="btn-primary btn-sm" style="background:#e67e22;">
            <i class="fas fa-plus"></i> Agregar Adelanto
        </button>
    </div>

    <?php if (empty($adelantos)): ?>
        <p style="text-align:center; padding:20px; opacity:0.5;">No se registraron adelantos para este viaje.</p>
    <?php else: ?>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Método de Pago</th>
                    <th style="text-align:right;">Monto</th>
                    <th style="text-align:center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($adelantos as $a): ?>
                <tr>
                    <td><?= htmlspecialchars(formatDate($a['fecha'])) ?></td>
                    <td><?= htmlspecialchars($a['metodo_pago'] ?? '-') ?></td>
                    <td style="text-align:right; font-weight:bold;">$ <?= number_format((float)$a['monto'], 2, ',', '.') ?></td>
                    <td style="text-align:center;">
                        <form method="POST" style="display:inline;" onsubmit="return appConfirm('¿Eliminar este adelanto?', function(){ this.submit(); }.bind(this), 'Eliminar Adelanto')" >

                            <input type="hidden" name="action" value="borrar_adelanto">
                            <input type="hidden" name="adelanto_id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" title="Eliminar adelanto" style="background:none; border:none; color:#e74c3c; cursor:pointer;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="2" style="text-align:right;">Total Adelantos:</td>
                    <td style="text-align:right;">$ <?= number_format($total_adelantos, 2, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Observaciones -->
<?php if (!empty($viaje['observaciones'])): ?>
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0;">📝 Observaciones</h3>
    <p style="white-space:pre-wrap;"><?= htmlspecialchars($viaje['observaciones']) ?></p>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MODAL: AGREGAR GASTO
     ══════════════════════════════════════════════════════════ -->
<div id="modal-gasto" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 style="margin:0;">Agregar Gasto</h3>
            <span class="close-modal" onclick="closeModal('modal-gasto')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="agregar_gasto">
                <div class="form-group">
                    <label>Tipo de Gasto *</label>
                    <select name="tipo_gasto" class="input-field" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="combustible">⛽ Combustible</option>
                        <option value="peaje">🛣 Peaje</option>
                        <option value="playa">🏗 Playa</option>
                        <option value="reparacion_ruta">🔧 Reparación en Ruta</option>
                        <option value="otros">📋 Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto ($) *</label>
                    <input type="number" step="0.01" min="0.01" name="monto" class="input-field" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" class="input-field" placeholder="Detalle del gasto...">
                </div>
                <div class="form-group">
                    <label>Pagado por</label>
                    <select name="pagado_por" class="input-field">
                        <option value="empresa">Empresa</option>
                        <option value="adelanto">Adelanto (Chofer)</option>
                        <option value="descuento_flete">Descuento del Flete</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" class="input-field" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-gasto')">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Guardar Gasto</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: AGREGAR ADELANTO
     ══════════════════════════════════════════════════════════ -->
<div id="modal-adelanto" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3 style="margin:0;">Registrar Adelanto</h3>
            <span class="close-modal" onclick="closeModal('modal-adelanto')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="agregar_adelanto">
                <div class="form-group">
                    <label>Monto ($) *</label>
                    <input type="number" step="0.01" min="0.01" name="monto_adelanto" class="input-field" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="metodo_pago" class="input-field">
                        <option value="">-- Seleccionar --</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="mercadopago">Mercado Pago</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha_adelanto" class="input-field" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-adelanto')">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#e67e22;">
                    <i class="fas fa-hand-holding-usd"></i> Registrar Adelanto
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: FACTURAR
     ══════════════════════════════════════════════════════════ -->
<div id="modal-facturar" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3 style="margin:0;">Facturar Viaje</h3>
            <span class="close-modal" onclick="closeModal('modal-facturar')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="facturar">
                <p style="margin-top:0; opacity:0.7;">
                    Total a facturar: <strong>$ <?= number_format((float)$viaje['total_flete_neto'], 2, ',', '.') ?></strong>
                </p>
                <div class="form-group">
                    <label>Número de Factura *</label>
                    <input type="text" name="factura_nro" class="input-field" required placeholder="A 00001-00000001">
                </div>
                <div class="form-group">
                    <label>Fecha de Factura</label>
                    <input type="date" name="factura_fecha" class="input-field" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-facturar')">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#9b59b6;">
                    <i class="fas fa-file-invoice"></i> Facturar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: COBRAR
     ══════════════════════════════════════════════════════════ -->
<div id="modal-cobrar" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 style="margin:0;">Registrar Cobro</h3>
            <span class="close-modal" onclick="closeModal('modal-cobrar')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="cobrar">
                <p style="margin-top:0; opacity:0.7;">
                    Factura: <strong><?= htmlspecialchars($viaje['factura_nro'] ?? '-') ?></strong><br>
                    Monto a cobrar: <strong>$ <?= number_format((float)$viaje['total_flete_neto'], 2, ',', '.') ?></strong>
                </p>
                <div class="form-group">
                    <label>Monto Cobrado ($) *</label>
                    <input type="number" step="0.01" min="0.01" name="monto_cobro" class="input-field" required placeholder="0.00"
                           value="<?= number_format((float)$viaje['total_flete_neto'], 2, '.', '') ?>">
                </div>
                <div class="form-group">
                    <label>Retenciones / Descuentos ($)</label>
                    <input type="number" step="0.01" min="0" name="retenciones" class="input-field" value="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Medio de Cobro</label>
                    <select name="medio_cobro" class="input-field">
                        <option value="">-- Seleccionar --</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque / E-Cheq</option>
                        <option value="mercadopago">Mercado Pago</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Cobro</label>
                    <input type="date" name="fecha_cobro" class="input-field" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones_cobro" class="input-field" style="resize:vertical; min-height:50px;" placeholder="Notas sobre el cobro..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modal-cobrar')">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#27ae60;">
                    <i class="fas fa-check-double"></i> Confirmar Cobro
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function prepararCobro() {
    openModal('modal-cobrar');
}
</script>