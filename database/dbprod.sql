-- ============================================================
-- dbprod.sql — Schema completo de producción
-- Clinical Lab API
--
-- Orden de ejecución (dependencias resueltas):
--   1. Tablas base: órdenes, auth, catálogo, centros, pacientes
--   2. Columnas de seguridad en users
--   3. Columnas de perfil en aliados
--   4. Tipos de resultado y rangos por reactivo
--   5. Bacteriólogos
--   6. PDF, email y contacto de pacientes
--   7. Portal de pacientes (tokens OTP)
--   8. Rol médico y asociación usuario ↔ centros de salud
--   9. Stored procedure sp_crear_orden
--  10. Migración de pacientes desde órdenes existentes
--
-- Idempotente: se puede ejecutar varias veces sin error.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- 1. TABLAS BASE
-- ============================================================

-- ------------------------------------------------------------
-- 1a. Órdenes de laboratorio
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS lab_orders (
    id_solicitud_key    VARCHAR(100) PRIMARY KEY,
    id_admision         VARCHAR(100) NOT NULL,
    id_atencion         VARCHAR(100) NULL,
    tipo_documento      VARCHAR(20)  NOT NULL,
    identificacion      VARCHAR(50)  NOT NULL,
    nombre_paciente     VARCHAR(200) NOT NULL,
    sexo                VARCHAR(10)  NOT NULL,
    fecha_nacimiento    DATE         NOT NULL,
    centro_salud        VARCHAR(150) NOT NULL,
    fecha_orden         DATETIME     NOT NULL,
    medico_ordena       VARCHAR(150) NOT NULL,
    numero_autorizacion VARCHAR(100) NULL,
    id_aliado           VARCHAR(100) NULL,
    fecha_envio         DATETIME     NULL,
    porc_ejecucion      DECIMAL(5,2) DEFAULT 0,
    estado_orden        VARCHAR(50)  NOT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lab_orders_estado (estado_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_order_details (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key     VARCHAR(100) NOT NULL,
    id_admision          VARCHAR(100) NOT NULL,
    cups                 VARCHAR(30)  NOT NULL,
    nombre_laboratorio   VARCHAR(150) NOT NULL,
    fecha_toma_muestra   DATETIME     NULL,
    metodo               VARCHAR(150) NULL,
    reactivo             VARCHAR(150) NULL,
    invima               VARCHAR(100) NULL,
    estado_resultado     VARCHAR(100) NULL,
    fecha_resultado      DATETIME     NULL,
    tipo_id_bacteriologo VARCHAR(10)  NULL,
    id_bacteriologo      VARCHAR(50)  NULL,
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_detail_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_results (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    cups             VARCHAR(30)  NOT NULL,
    values_json      JSON         NOT NULL,
    attachment_path  VARCHAR(255) NULL,
    bacteriologo_id  INT          NULL,
    received_at      DATETIME     NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1b. Auth: roles, aliados, users, user_aliado
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (name) VALUES
    ('admin'),
    ('lab_operator'),
    ('aliado_operator'),
    ('viewer');

CREATE TABLE IF NOT EXISTS aliados (
    id         VARCHAR(100) PRIMARY KEY,
    nombre     VARCHAR(200) NOT NULL,
    nit        VARCHAR(20)  NULL,
    direccion  VARCHAR(255) NULL,
    email      VARCHAR(150) NULL,
    logo_path  VARCHAR(255) NULL,
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT          NOT NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_aliado (
    user_id   INT          NOT NULL,
    aliado_id VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, aliado_id),
    CONSTRAINT fk_ua_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ua_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1c. Catálogo de exámenes y parámetros (EAV)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS exam_types (
    cups        VARCHAR(30)  NOT NULL PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_parameters (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    cups             VARCHAR(30)   NOT NULL,
    codigo           VARCHAR(50)   NOT NULL,
    nombre           VARCHAR(150)  NOT NULL,
    unidad           VARCHAR(50)   NULL,
    valor_min_ref    DECIMAL(10,4) NULL,
    valor_max_ref    DECIMAL(10,4) NULL,
    tipo_resultado   ENUM('numerico','texto','booleano') NOT NULL DEFAULT 'numerico',
    etiqueta_booleano ENUM('normal_alto','positivo_negativo','reactivo_no_reactivo') NULL,
    comentario       TEXT          NULL,
    sexo             CHAR(1)       NOT NULL DEFAULT '*',
    edad_min         TINYINT       NULL,
    edad_max         TINYINT       NULL,
    obligatorio      TINYINT(1)    NOT NULL DEFAULT 0,
    orden            SMALLINT      NOT NULL DEFAULT 0,
    activo           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ep_exam_type FOREIGN KEY (cups) REFERENCES exam_types(cups) ON DELETE CASCADE,
    INDEX idx_ep_cups (cups),
    UNIQUE KEY uq_ep_cups_codigo_sexo_edad (cups, codigo, sexo, edad_min, edad_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS lab_result_values (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    lab_result_id  INT           NOT NULL,
    parameter_id   INT           NOT NULL,
    valor_numerico DECIMAL(10,4) NULL,
    valor_texto    VARCHAR(255)  NULL,
    valor_booleano TINYINT(1)    NULL,
    reactivo       VARCHAR(150)  NULL,
    flag           ENUM(
        'normal','alto','bajo','critico','indeterminado',
        'positivo','negativo','reactivo','no_reactivo'
    ) NOT NULL DEFAULT 'indeterminado',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lrv_result    FOREIGN KEY (lab_result_id) REFERENCES lab_results(id) ON DELETE CASCADE,
    CONSTRAINT fk_lrv_parameter FOREIGN KEY (parameter_id)  REFERENCES exam_parameters(id),
    INDEX idx_lrv_result (lab_result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1d. Centros de salud, pacientes y relaciones
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS health_centers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(200) NOT NULL,
    ciudad     VARCHAR(100) NULL,
    direccion  VARCHAR(255) NULL,
    telefono   VARCHAR(30)  NULL,
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aliado_health_center (
    aliado_id        VARCHAR(100) NOT NULL,
    health_center_id INT          NOT NULL,
    PRIMARY KEY (aliado_id, health_center_id),
    CONSTRAINT fk_ahc_aliado FOREIGN KEY (aliado_id)        REFERENCES aliados(id)        ON DELETE CASCADE,
    CONSTRAINT fk_ahc_center FOREIGN KEY (health_center_id) REFERENCES health_centers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patients (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento   VARCHAR(20)  NOT NULL,
    identificacion   VARCHAR(50)  NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    sexo             CHAR(1)      NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    email            VARCHAR(150) NULL,
    telefono         VARCHAR(30)  NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1e. Bacteriólogos
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS bacteriologos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    aliado_id           VARCHAR(100)  NOT NULL,
    tipo_documento      VARCHAR(20)   NOT NULL,
    identificacion      VARCHAR(50)   NOT NULL,
    nombre              VARCHAR(200)  NOT NULL,
    tarjeta_profesional VARCHAR(50)   NULL,
    universidad         VARCHAR(200)  NULL,
    firma_path          VARCHAR(255)  NULL,
    activo              TINYINT(1)    NOT NULL DEFAULT 1,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bact_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_bact_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1f. Médicos
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS medicos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento  VARCHAR(20)  NOT NULL,
    identificacion  VARCHAR(50)  NOT NULL,
    nombre          VARCHAR(200) NOT NULL,
    especialidad    VARCHAR(150) NULL,
    registro_medico VARCHAR(50)  NULL,
    user_id         INT          NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medico_doc  (tipo_documento, identificacion),
    UNIQUE KEY uq_medico_user (user_id),
    CONSTRAINT fk_medico_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1g. Antibiogramas
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS antibiogramas (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    lab_result_id     INT          NOT NULL,
    bacteria_aislada  VARCHAR(255) NOT NULL,
    gram              ENUM('positivo','negativo','n/a') NULL,
    tiempo_incubacion VARCHAR(100) NULL,
    gram_orina        TEXT         NULL,
    observaciones     TEXT         NULL,
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ab_result FOREIGN KEY (lab_result_id) REFERENCES lab_results(id) ON DELETE CASCADE,
    INDEX idx_ab_result (lab_result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS antibiograma_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    antibiograma_id INT          NOT NULL,
    antibiotico     VARCHAR(150) NOT NULL,
    cim             VARCHAR(50)  NULL,
    sensibilidad    ENUM('S','I','R') NULL,
    metodo          VARCHAR(100) NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_abi_ab FOREIGN KEY (antibiograma_id) REFERENCES antibiogramas(id) ON DELETE CASCADE,
    INDEX idx_abi_ab (antibiograma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 1h. Log de email y portal de pacientes
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS result_email_log (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    email_destino    VARCHAR(150) NOT NULL,
    estado           ENUM('enviado','error') NOT NULL,
    error_mensaje    TEXT NULL,
    enviado_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rel_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ============================================================
-- 2. ROL MÉDICO Y TABLA user_health_center
-- ============================================================

INSERT IGNORE INTO roles (name) VALUES ('medico');

CREATE TABLE IF NOT EXISTS user_health_center (
    user_id          INT NOT NULL,
    health_center_id INT NOT NULL,
    PRIMARY KEY (user_id, health_center_id),
    CONSTRAINT fk_uhc_user FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE CASCADE,
    CONSTRAINT fk_uhc_hc   FOREIGN KEY (health_center_id) REFERENCES health_centers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. COLUMNAS ADICIONALES (idempotentes vía stored procedures)
-- ============================================================

-- ------------------------------------------------------------
-- 3a. Columnas de seguridad en users
-- ------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_add_security_columns;

DELIMITER //
CREATE PROCEDURE sp_add_security_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'failed_login_attempts'
    ) THEN
        ALTER TABLE users ADD COLUMN failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locked_until'
    ) THEN
        ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_reset_token'
    ) THEN
        ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_reset_expires'
    ) THEN
        ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_security_columns();
DROP PROCEDURE IF EXISTS sp_add_security_columns;

-- ------------------------------------------------------------
-- 3b. Columnas de perfil extendido en aliados
-- ------------------------------------------------------------

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

-- ------------------------------------------------------------
-- 3c. Columnas FK en lab_orders (patient_id, health_center_id, medico_id)
-- ------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_add_lab_orders_cols;

DELIMITER //
CREATE PROCEDURE sp_add_lab_orders_cols()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'patient_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN patient_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_patient FOREIGN KEY (patient_id) REFERENCES patients(id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'health_center_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN health_center_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_health_center FOREIGN KEY (health_center_id) REFERENCES health_centers(id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'medico_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN medico_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_medico FOREIGN KEY (medico_id) REFERENCES medicos(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;

CALL sp_add_lab_orders_cols();
DROP PROCEDURE IF EXISTS sp_add_lab_orders_cols;

-- ------------------------------------------------------------
-- 3d. FK bacteriologo_id en lab_results
-- ------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_add_bact_fk;

DELIMITER //
CREATE PROCEDURE sp_add_bact_fk()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_results' AND CONSTRAINT_NAME = 'fk_lr_bacteriologo'
    ) THEN
        ALTER TABLE lab_results ADD CONSTRAINT fk_lr_bacteriologo FOREIGN KEY (bacteriologo_id) REFERENCES bacteriologos(id);
    END IF;
END //
DELIMITER ;

CALL sp_add_bact_fk();
DROP PROCEDURE IF EXISTS sp_add_bact_fk;

-- ============================================================
-- 4. STORED PROCEDURE sp_crear_orden
-- ============================================================

DROP PROCEDURE IF EXISTS sp_crear_orden;

DELIMITER //

CREATE PROCEDURE sp_crear_orden(
    -- Datos del paciente
    IN p_tipo_documento        VARCHAR(20),
    IN p_identificacion        VARCHAR(50),
    IN p_nombre_paciente       VARCHAR(200),
    IN p_sexo                  CHAR(1),
    IN p_fecha_nacimiento      DATE,
    IN p_email_paciente        VARCHAR(150),
    IN p_telefono_paciente     VARCHAR(30),

    -- Datos de la orden
    IN p_id_solicitud_key      VARCHAR(100),
    IN p_id_admision           VARCHAR(100),
    IN p_id_atencion           VARCHAR(100),
    IN p_centro_salud          VARCHAR(150),
    IN p_fecha_orden           DATETIME,
    IN p_medico_ordena         VARCHAR(150),
    IN p_tipo_doc_medico       VARCHAR(20),
    IN p_identificacion_medico VARCHAR(50),
    IN p_numero_autorizacion   VARCHAR(100),
    IN p_codigo_aliado         VARCHAR(100),

    -- Salida
    OUT p_patient_id      INT,
    OUT p_paciente_creado TINYINT(1),
    OUT p_orden_creada    TINYINT(1),
    OUT p_mensaje         VARCHAR(500)
)
BEGIN
    DECLARE v_health_center_id INT          DEFAULT NULL;
    DECLARE v_aliado_id        VARCHAR(100) DEFAULT NULL;
    DECLARE v_medico_id        INT          DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_patient_id      = NULL;
        SET p_paciente_creado = 0;
        SET p_orden_creada    = 0;
        GET DIAGNOSTICS CONDITION 1 p_mensaje = MESSAGE_TEXT;
    END;

    START TRANSACTION;

    -- 1. Paciente: buscar o crear
    SELECT id INTO p_patient_id
    FROM patients
    WHERE tipo_documento = p_tipo_documento
      AND identificacion = p_identificacion
    LIMIT 1;

    IF p_patient_id IS NULL THEN
        INSERT INTO patients (tipo_documento, identificacion, nombre, sexo, fecha_nacimiento, email, telefono)
        VALUES (p_tipo_documento, p_identificacion, p_nombre_paciente, p_sexo, p_fecha_nacimiento,
                p_email_paciente, p_telefono_paciente);
        SET p_patient_id      = LAST_INSERT_ID();
        SET p_paciente_creado = 1;
    ELSE
        UPDATE patients
        SET email    = COALESCE(NULLIF(p_email_paciente,    ''), email),
            telefono = COALESCE(NULLIF(p_telefono_paciente, ''), telefono)
        WHERE id = p_patient_id;
        SET p_paciente_creado = 0;
    END IF;

    -- 2. Aliado: buscar por ID o nombre
    SELECT id INTO v_aliado_id
    FROM aliados
    WHERE id = p_codigo_aliado AND activo = 1
    LIMIT 1;

    IF v_aliado_id IS NULL THEN
        SELECT id INTO v_aliado_id
        FROM aliados
        WHERE LOWER(nombre) = LOWER(p_codigo_aliado) AND activo = 1
        LIMIT 1;
    END IF;

    -- 3. Centro de salud: buscar por nombre
    SELECT id INTO v_health_center_id
    FROM health_centers
    WHERE nombre = p_centro_salud AND activo = 1
    LIMIT 1;

    -- 4. Médico: buscar por documento
    IF p_identificacion_medico IS NOT NULL AND p_identificacion_medico != '' THEN
        SELECT id INTO v_medico_id
        FROM medicos
        WHERE tipo_documento = p_tipo_doc_medico
          AND identificacion = p_identificacion_medico
          AND activo         = 1
        LIMIT 1;
    END IF;

    -- 5. Orden: verificar duplicado e insertar
    IF EXISTS (SELECT 1 FROM lab_orders WHERE id_solicitud_key = p_id_solicitud_key) THEN
        SET p_orden_creada = 0;
        SET p_mensaje = CONCAT('La orden ', p_id_solicitud_key, ' ya existe. ',
                               IF(p_paciente_creado, 'Paciente creado (ID: ', 'Paciente existente (ID: '),
                               p_patient_id, ').');
        ROLLBACK;
    ELSE
        INSERT INTO lab_orders (
            id_solicitud_key, id_admision, id_atencion, tipo_documento, identificacion,
            nombre_paciente, sexo, fecha_nacimiento, centro_salud, fecha_orden,
            medico_ordena, numero_autorizacion, id_aliado, fecha_envio, porc_ejecucion,
            estado_orden, patient_id, health_center_id, medico_id
        ) VALUES (
            p_id_solicitud_key, p_id_admision, p_id_atencion, p_tipo_documento, p_identificacion,
            p_nombre_paciente, p_sexo, p_fecha_nacimiento, p_centro_salud, p_fecha_orden,
            p_medico_ordena, p_numero_autorizacion, v_aliado_id, NULL, 0.00,
            'pending', p_patient_id, v_health_center_id, v_medico_id
        );

        SET p_orden_creada = 1;
        SET p_mensaje = CONCAT(
            'Orden ', p_id_solicitud_key, ' creada. ',
            IF(p_paciente_creado,
               CONCAT('Paciente NUEVO (ID: ', p_patient_id, ', doc: ', p_identificacion, '). '),
               CONCAT('Paciente EXISTENTE (ID: ', p_patient_id, '). ')
            ),
            IF(v_aliado_id IS NOT NULL,
               CONCAT('Aliado vinculado (ID: ', v_aliado_id, '). '),
               CONCAT('Aliado "', p_codigo_aliado, '" no encontrado, campo queda NULL. ')
            ),
            IF(v_medico_id IS NOT NULL,
               CONCAT('Médico vinculado (ID: ', v_medico_id, '). '),
               'Médico no encontrado en catálogo (guardado como texto). '
            ),
            IF(v_health_center_id IS NOT NULL,
               CONCAT('Centro de salud vinculado (ID: ', v_health_center_id, ').'),
               'Centro de salud no encontrado en catálogo (guardado como texto).'
            )
        );

        COMMIT;
    END IF;

END //

DELIMITER ;

-- ============================================================
-- 5. MIGRACIÓN: crear pacientes desde órdenes existentes
-- (solo tiene efecto si hay filas con patient_id IS NULL)
-- ============================================================

INSERT IGNORE INTO patients (tipo_documento, identificacion, nombre, sexo, fecha_nacimiento)
SELECT DISTINCT
    tipo_documento,
    identificacion,
    nombre_paciente,
    sexo,
    fecha_nacimiento
FROM lab_orders
WHERE patient_id IS NULL;

UPDATE lab_orders lo
JOIN patients p ON p.tipo_documento = lo.tipo_documento
               AND p.identificacion  = lo.identificacion
SET lo.patient_id = p.id
WHERE lo.patient_id IS NULL;

SET foreign_key_checks = 1;

-- ============================================================
-- FIN — dbprod.sql
-- ============================================================
