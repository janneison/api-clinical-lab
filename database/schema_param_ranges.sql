-- ============================================================
-- Tipos de resultado, rangos por reactivo y flags extendidos
-- Ejecutar después de schema_exam_catalog.sql
-- ============================================================

-- 1. Nuevas columnas en exam_parameters (con verificación de existencia)
DROP PROCEDURE IF EXISTS sp_add_param_columns;

DELIMITER //
CREATE PROCEDURE sp_add_param_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'exam_parameters'
          AND COLUMN_NAME  = 'tipo_resultado'
    ) THEN
        ALTER TABLE exam_parameters
            ADD COLUMN tipo_resultado ENUM('numerico','texto','booleano') NOT NULL DEFAULT 'numerico';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'exam_parameters'
          AND COLUMN_NAME  = 'etiqueta_booleano'
    ) THEN
        ALTER TABLE exam_parameters
            ADD COLUMN etiqueta_booleano ENUM('normal_alto','positivo_negativo','reactivo_no_reactivo') NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lab_result_values'
          AND COLUMN_NAME  = 'valor_booleano'
    ) THEN
        ALTER TABLE lab_result_values
            ADD COLUMN valor_booleano TINYINT(1) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lab_result_values'
          AND COLUMN_NAME  = 'reactivo'
    ) THEN
        ALTER TABLE lab_result_values
            ADD COLUMN reactivo VARCHAR(150) NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_param_columns();
DROP PROCEDURE IF EXISTS sp_add_param_columns;

-- Expandir el ENUM de flag (MODIFY es seguro aunque ya tenga los valores)
ALTER TABLE lab_result_values
    MODIFY COLUMN flag ENUM(
        'normal','alto','bajo','critico','indeterminado',
        'positivo','negativo','reactivo','no_reactivo'
    ) NOT NULL DEFAULT 'indeterminado';

-- 2. Rangos de referencia por reactivo
CREATE TABLE IF NOT EXISTS exam_parameter_ranges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id  INT           NOT NULL,
    reactivo      VARCHAR(150)  NOT NULL,
    valor_min_ref DECIMAL(10,4) NULL,
    valor_max_ref DECIMAL(10,4) NULL,
    sexo          CHAR(1)       NOT NULL DEFAULT '*',
    edad_min      TINYINT       NULL,
    edad_max      TINYINT       NULL,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_epr_parameter FOREIGN KEY (parameter_id) REFERENCES exam_parameters(id) ON DELETE CASCADE,
    INDEX idx_epr_parameter (parameter_id),
    UNIQUE KEY uq_epr_param_reactivo_sexo_edad (parameter_id, reactivo, sexo, edad_min, edad_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
