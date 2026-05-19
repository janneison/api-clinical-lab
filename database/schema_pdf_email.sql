-- ============================================================
-- PDF de resultados y log de envíos por email
-- ============================================================

DROP PROCEDURE IF EXISTS sp_add_patient_contact;

DELIMITER //
CREATE PROCEDURE sp_add_patient_contact()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'email'
    ) THEN
        ALTER TABLE patients ADD COLUMN email VARCHAR(150) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'telefono'
    ) THEN
        ALTER TABLE patients ADD COLUMN telefono VARCHAR(30) NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_patient_contact();
DROP PROCEDURE IF EXISTS sp_add_patient_contact;

CREATE TABLE IF NOT EXISTS result_email_log (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    email_destino    VARCHAR(150) NOT NULL,
    estado           ENUM('enviado','error') NOT NULL,
    error_mensaje    TEXT NULL,
    enviado_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rel_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
