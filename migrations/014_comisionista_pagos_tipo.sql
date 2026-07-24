    -- Migration 014: Add tipo column to comisionista_pagos table
    -- This allows distinguishing between:
    --   'comision' = commission earned automatically (haber)
    --   'pago'     = payment made to comisionista (debe)
    -- Enables cuenta corriente for comisionistas with debit/credit tracking

    ALTER TABLE `comisionista_pagos`
    ADD COLUMN `tipo` enum('comision','pago') NOT NULL DEFAULT 'pago' 
    AFTER `monto`;

    -- Update existing records: pagos are retroactively marked as legacy pagos
    -- (we can't know which were commissions vs payments, so they remain as 'pago')
    UPDATE `comisionista_pagos` SET `tipo` = 'pago' WHERE `tipo` IS NULL OR `tipo` = '';