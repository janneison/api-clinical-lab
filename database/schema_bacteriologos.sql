-- ============================================================
-- Bacteriólogos asociados a aliados
-- ============================================================

CREATE TABLE IF NOT EXISTS bacteriologos (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    aliado_id             VARCHAR(100)  NOT NULL,
    tipo_documento        VARCHAR(20)   NOT NULL,
    identificacion        VARCHAR(50)   NOT NULL,
    nombre                VARCHAR(200)  NOT NULL,
    tarjeta_profesional   VARCHAR(50)   NULL,
    universidad           VARCHAR(200)  NULL,
    firma_path            VARCHAR(255)  NULL,
    activo                TINYINT(1)    NOT NULL DEFAULT 1,
    created_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bact_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_bact_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar bacteriologo_id a lab_results
DROP PROCEDURE IF EXISTS sp_add_bacteriologo_to_results;

DELIMITER //
CREATE PROCEDURE sp_add_bacteriologo_to_results()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lab_results'
          AND COLUMN_NAME  = 'bacteriologo_id'
    ) THEN
        ALTER TABLE lab_results
            ADD COLUMN bacteriologo_id INT NULL,
            ADD CONSTRAINT fk_lr_bacteriologo
                FOREIGN KEY (bacteriologo_id) REFERENCES bacteriologos(id);
    END IF;
END //
DELIMITER ;

CALL sp_add_bacteriologo_to_results();
DROP PROCEDURE IF EXISTS sp_add_bacteriologo_to_results;
