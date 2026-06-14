-- ============================================================
-- Seguridad: bloqueo por intentos fallidos y recuperación de contraseña
-- Ejecutar sobre la base de datos existente (idempotente)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_add_security_columns;

DELIMITER //
CREATE PROCEDURE sp_add_security_columns()
BEGIN
    -- Contador de intentos fallidos de login
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'failed_login_attempts'
    ) THEN
        ALTER TABLE users ADD COLUMN failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0;
    END IF;

    -- Fecha/hora hasta la que el usuario está bloqueado (NULL = no bloqueado)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'locked_until'
    ) THEN
        ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL;
    END IF;

    -- Token de recuperación de contraseña (hash SHA-256 del token enviado por email)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'password_reset_token'
    ) THEN
        ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL;
    END IF;

    -- Expiración del token de recuperación
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'password_reset_expires'
    ) THEN
        ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_security_columns();
DROP PROCEDURE IF EXISTS sp_add_security_columns;
