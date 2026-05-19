-- ============================================================
-- Centros de salud, pacientes y relaciones
-- Ejecutar después de schema_auth.sql
-- ============================================================

-- Centros de salud
CREATE TABLE IF NOT EXISTS health_centers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    ciudad      VARCHAR(100) NULL,
    direccion   VARCHAR(255) NULL,
    telefono    VARCHAR(30)  NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relación N:M aliado ↔ centro de salud
CREATE TABLE IF NOT EXISTS aliado_health_center (
    aliado_id        VARCHAR(100) NOT NULL,
    health_center_id INT          NOT NULL,
    PRIMARY KEY (aliado_id, health_center_id),
    CONSTRAINT fk_ahc_aliado  FOREIGN KEY (aliado_id)        REFERENCES aliados(id)         ON DELETE CASCADE,
    CONSTRAINT fk_ahc_center  FOREIGN KEY (health_center_id) REFERENCES health_centers(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pacientes
CREATE TABLE IF NOT EXISTS patients (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento   VARCHAR(20)  NOT NULL,
    identificacion   VARCHAR(50)  NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    sexo             CHAR(1)      NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nuevas columnas en lab_orders (solo si no existen)
DROP PROCEDURE IF EXISTS sp_add_order_columns;

DELIMITER //
CREATE PROCEDURE sp_add_order_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lab_orders'
          AND COLUMN_NAME  = 'patient_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN patient_id INT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lab_orders'
          AND COLUMN_NAME  = 'health_center_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN health_center_id INT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA    = DATABASE()
          AND TABLE_NAME      = 'lab_orders'
          AND CONSTRAINT_NAME = 'fk_lo_patient'
    ) THEN
        ALTER TABLE lab_orders
            ADD CONSTRAINT fk_lo_patient FOREIGN KEY (patient_id) REFERENCES patients(id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA    = DATABASE()
          AND TABLE_NAME      = 'lab_orders'
          AND CONSTRAINT_NAME = 'fk_lo_health_center'
    ) THEN
        ALTER TABLE lab_orders
            ADD CONSTRAINT fk_lo_health_center FOREIGN KEY (health_center_id) REFERENCES health_centers(id);
    END IF;
END //
DELIMITER ;

CALL sp_add_order_columns();
DROP PROCEDURE IF EXISTS sp_add_order_columns;
