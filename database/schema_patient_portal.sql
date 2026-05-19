-- ============================================================
-- Portal de pacientes — acceso passwordless con OTP
-- ============================================================

CREATE TABLE IF NOT EXISTS patient_access_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT          NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expires_at  DATETIME     NOT NULL,
    usado       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pat_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_pat_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
