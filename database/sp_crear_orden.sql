DROP PROCEDURE IF EXISTS sp_crear_orden;

DELIMITER //

CREATE PROCEDURE sp_crear_orden(
    -- ── Datos del paciente ────────────────────────────────────────────────────
    IN p_tipo_documento        VARCHAR(20),
    IN p_identificacion        VARCHAR(50),
    IN p_nombre_paciente       VARCHAR(200),
    IN p_sexo                  CHAR(1),
    IN p_fecha_nacimiento      DATE,
    IN p_email_paciente        VARCHAR(150),   -- nuevo: correo del paciente
    IN p_telefono_paciente     VARCHAR(30),    -- nuevo: teléfono del paciente

    -- ── Datos de la orden ─────────────────────────────────────────────────────
    IN p_id_solicitud_key      VARCHAR(100),
    IN p_id_admision           VARCHAR(100),
    IN p_id_atencion           VARCHAR(100),
    IN p_centro_salud          VARCHAR(150),
    IN p_fecha_orden           DATETIME,
    IN p_medico_ordena         VARCHAR(150),
    IN p_tipo_doc_medico       VARCHAR(20),    -- nuevo: tipo de documento del médico
    IN p_identificacion_medico VARCHAR(50),    -- nuevo: número de documento del médico
    IN p_numero_autorizacion   VARCHAR(100),
    IN p_codigo_aliado         VARCHAR(100),   -- reemplaza p_id_aliado: código legible del aliado

    -- ── Salida ────────────────────────────────────────────────────────────────
    OUT p_patient_id           INT,
    OUT p_paciente_creado      TINYINT(1),   -- 1 = nuevo, 0 = existente
    OUT p_orden_creada         TINYINT(1),   -- 1 = creada, 0 = ya existía
    OUT p_mensaje              VARCHAR(500)
)
BEGIN
    DECLARE v_health_center_id INT     DEFAULT NULL;
    DECLARE v_aliado_id        VARCHAR(100) DEFAULT NULL;
    DECLARE v_medico_id        INT     DEFAULT NULL;

    -- ── Manejo de errores ─────────────────────────────────────────────────────
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_patient_id      = NULL;
        SET p_paciente_creado = 0;
        SET p_orden_creada    = 0;
        GET DIAGNOSTICS CONDITION 1 p_mensaje = MESSAGE_TEXT;
    END;

    START TRANSACTION;

    -- ── 1. Paciente: buscar o crear ───────────────────────────────────────────
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
        -- Actualizar contacto si se reciben datos nuevos
        UPDATE patients
        SET email    = COALESCE(NULLIF(p_email_paciente,    ''), email),
            telefono = COALESCE(NULLIF(p_telefono_paciente, ''), telefono)
        WHERE id = p_patient_id;

        SET p_paciente_creado = 0;
    END IF;

    -- ── 2. Aliado: buscar ID a partir del código ──────────────────────────────
    SELECT id INTO v_aliado_id
    FROM aliados
    WHERE id = p_codigo_aliado AND activo = 1
    LIMIT 1;

    -- Si no coincide exacto, intentar búsqueda por nombre (insensible a mayúsculas)
    IF v_aliado_id IS NULL THEN
        SELECT id INTO v_aliado_id
        FROM aliados
        WHERE LOWER(nombre) = LOWER(p_codigo_aliado) AND activo = 1
        LIMIT 1;
    END IF;

    -- ── 3. Centro de salud: buscar por nombre ─────────────────────────────────
    SELECT id INTO v_health_center_id
    FROM health_centers
    WHERE nombre = p_centro_salud AND activo = 1
    LIMIT 1;

    -- ── 4. Médico: buscar por documento ───────────────────────────────────────
    IF p_identificacion_medico IS NOT NULL AND p_identificacion_medico != '' THEN
        SELECT id INTO v_medico_id
        FROM medicos
        WHERE tipo_documento  = p_tipo_doc_medico
          AND identificacion  = p_identificacion_medico
          AND activo          = 1
        LIMIT 1;
    END IF;

    -- ── 5. Orden: verificar duplicado ─────────────────────────────────────────
    IF EXISTS (SELECT 1 FROM lab_orders WHERE id_solicitud_key = p_id_solicitud_key) THEN
        SET p_orden_creada = 0;
        SET p_mensaje = CONCAT('La orden ', p_id_solicitud_key, ' ya existe. ',
                               IF(p_paciente_creado, 'Paciente creado (ID: ', 'Paciente existente (ID: '),
                               p_patient_id, ').');
        ROLLBACK;
    ELSE
        -- ── 6. Insertar orden ─────────────────────────────────────────────────
        INSERT INTO lab_orders (
            id_solicitud_key,
            id_admision,
            id_atencion,
            tipo_documento,
            identificacion,
            nombre_paciente,
            sexo,
            fecha_nacimiento,
            centro_salud,
            fecha_orden,
            medico_ordena,
            numero_autorizacion,
            id_aliado,
            fecha_envio,
            porc_ejecucion,
            estado_orden,
            patient_id,
            health_center_id,
            medico_id
        ) VALUES (
            p_id_solicitud_key,
            p_id_admision,
            p_id_atencion,
            p_tipo_documento,
            p_identificacion,
            p_nombre_paciente,
            p_sexo,
            p_fecha_nacimiento,
            p_centro_salud,
            p_fecha_orden,
            p_medico_ordena,
            p_numero_autorizacion,
            v_aliado_id,          -- ID resuelto desde el código del aliado
            NULL,
            0.00,
            'pending',
            p_patient_id,
            v_health_center_id,
            v_medico_id           -- ID resuelto desde el documento del médico
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
-- EJEMPLO DE USO
-- ============================================================
--
-- CALL sp_crear_orden(
--     -- Paciente
--     'CC',                          -- p_tipo_documento
--     '1098765432',                  -- p_identificacion
--     'Laura Martínez Gómez',        -- p_nombre_paciente
--     'F',                           -- p_sexo
--     '1990-04-15',                  -- p_fecha_nacimiento
--     'laura.martinez@email.com',    -- p_email_paciente       (nuevo)
--     '3001234567',                  -- p_telefono_paciente    (nuevo)
--
--     -- Orden
--     'ORD-2026-00123',              -- p_id_solicitud_key
--     'ADM-2026-00456',              -- p_id_admision
--     'ATE-2026-00789',              -- p_id_atencion
--     'Clínica Central Norte',       -- p_centro_salud
--     '2026-05-22 08:30:00',         -- p_fecha_orden
--     'Dr. Carlos Pérez',            -- p_medico_ordena
--     'CC',                          -- p_tipo_doc_medico      (nuevo)
--     '80123456',                    -- p_identificacion_medico (nuevo)
--     'AUTH-9900',                   -- p_numero_autorizacion
--     'HMI',                         -- p_codigo_aliado  (antes p_id_aliado)
--
--     -- Variables de salida
--     @patient_id,
--     @paciente_creado,
--     @orden_creada,
--     @mensaje
-- );
--
-- SELECT @patient_id, @paciente_creado, @orden_creada, @mensaje;
