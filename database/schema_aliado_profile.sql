-- ============================================================
-- Perfil extendido de aliados: nit, direccion, email, logo
-- ============================================================

DROP PROCEDURE IF EXISTS sp_add_aliado_columns;

DELIMITER //
CREATE PROCEDURE sp_add_aliado_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aliados' AND COLUMN_NAME = 'nit'
    ) THEN
        ALTER TABLE aliados ADD COLUMN nit VARCHAR(20) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aliados' AND COLUMN_NAME = 'direccion'
    ) THEN
        ALTER TABLE aliados ADD COLUMN direccion VARCHAR(255) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aliados' AND COLUMN_NAME = 'email'
    ) THEN
        ALTER TABLE aliados ADD COLUMN email VARCHAR(150) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aliados' AND COLUMN_NAME = 'logo_path'
    ) THEN
        ALTER TABLE aliados ADD COLUMN logo_path VARCHAR(255) NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_aliado_columns();
DROP PROCEDURE IF EXISTS sp_add_aliado_columns;
