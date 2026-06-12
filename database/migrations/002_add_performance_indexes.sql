-- Migración 002: Índices de rendimiento para WispHub
-- Ejecutar: mysql -u root -D tecnico-administrativo-wirelessdb < database/migrations/002_add_performance_indexes.sql

-- Índice para búsqueda de contratos activos (usado por cron y WispHub)
ALTER TABLE contratos ADD INDEX IF NOT EXISTS idx_contratos_estado (estado);

-- Índice compuesto para cuentas_por_cobrar (usado en cron de corte)
ALTER TABLE cuentas_por_cobrar ADD INDEX IF NOT EXISTS idx_cxc_estado_fecha (estado, fecha_vencimiento);

-- Índice para JOIN con contratos en el cron
ALTER TABLE cuentas_por_cobrar ADD INDEX IF NOT EXISTS idx_cxc_id_contrato (id_contrato);

-- Índice para reportes pendientes (BV auto-approval lookup)
ALTER TABLE pagos_reportados ADD INDEX IF NOT EXISTS idx_pagos_estado (estado);
ALTER TABLE pagos_reportados ADD INDEX IF NOT EXISTS idx_pagos_contrato (id_contrato);

-- Índice para históricos de cobros (JOIN con cuentas_por_cobrar)
ALTER TABLE cobros_manuales_historial ADD INDEX IF NOT EXISTS idx_historial_cxc (id_cobro_cxc);
