-- Agregar campos para vincular pagos/adelantos con viajes
ALTER TABLE `chofer_pagos` 
ADD COLUMN `viaje_id` int(11) DEFAULT NULL AFTER `chofer_id`,
ADD COLUMN `ctg_nro` varchar(50) DEFAULT NULL AFTER `viaje_id`,
ADD COLUMN `adelanto_total` decimal(15,2) DEFAULT NULL AFTER `ctg_nro`,
ADD KEY `viaje_id` (`viaje_id`);

ALTER TABLE `chofer_pagos`
ADD CONSTRAINT `chofer_pagos_ibfk_2` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE SET NULL;