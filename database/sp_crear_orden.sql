DELIMITER //

CREATE PROCEDURE sp_crear_orden(
    -- ── Datos del paciente ────────────────────────────────────────────────────
    IN p_tipo_documento        VARCHAR(20),
    IN p_identificacion        VARCHAR(50),
    IN p_nombre_paciente       VARCHAR(200),
    IN p_sexo                  CHAR(1),
    IN p_fecha_nacimiento      DATE,

    -- ── Datos de la orden ─────────────────────────────────────────────────────
    IN p_id_solicitud_key      VARCHAR(100),
    IN p_id_admision           VARCHAR(100),
    IN p_id_atencion           VARCHAR(100),
    IN p_centro_salud          VARCHAR(150),
    IN p_fecha_orden           DATETIME,
    IN p_medico_ordena         VARCHAR(150),
    IN p_numero_autorizacion   VARCHAR(100),
    IN p_id_aliado             VARCHAR(100),

    -- ── Salida ────────────────────────────────────────────────────────────────
    OUT p_patient_id           INT,
    OUT p_paciente_creado      TINYINT(1),   -- 1 = nuevo, 0 = existente
    OUT p_orden_creada         TINYINT(1),   -- 1 = creada, 0 = ya existía
    OUT p_mensaje              VARCHAR(500)
)
BEGIN
    DECLARE v_health_center_id INT DEFAULT NULL;

    -- ── Manejo de errores ─────────────────────────────────────────────────────
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_patient_id    = NULL;
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
        INSERT INTO patients (tipo_documento, identificacion, nombre, sexo, fecha_nacimiento)
        VALUES (p_tipo_documento, p_identificacion, p_nombre_paciente, p_sexo, p_fecha_nacimiento);

        SET p_patient_id    = LAST_INSERT_ID();
        SET p_paciente_creado = 1;
    ELSE
        SET p_paciente_creado = 0;
    END IF;

    -- ── 2. Centro de salud: buscar por nombre ─────────────────────────────────
    SELECT id INTO v_health_center_id
    FROM health_centers
    WHERE nombre = p_centro_salud AND activo = 1
    LIMIT 1;

    -- ── 3. Orden: verificar duplicado ─────────────────────────────────────────
    IF EXISTS (SELECT 1 FROM lab_orders WHERE id_solicitud_key = p_id_solicitud_key) THEN
        SET p_orden_creada = 0;
        SET p_mensaje = CONCAT('La orden ', p_id_solicitud_key, ' ya existe. ',
                               IF(p_paciente_creado, 'Paciente creado (ID: ', 'Paciente existente (ID: '),
                               p_patient_id, ').');
        ROLLBACK;
    ELSE
        -- ── 4. Insertar orden ─────────────────────────────────────────────────
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
            health_center_id
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
            p_id_aliado,
            NULL,
            0.00,
            'pending',
            p_patient_id,
            v_health_center_id
        );

        SET p_orden_creada = 1;
        SET p_mensaje = CONCAT(
            'Orden ', p_id_solicitud_key, ' creada. ',
            IF(p_paciente_creado,
               CONCAT('Paciente NUEVO creado (ID: ', p_patient_id, ', doc: ', p_identificacion, ').'),
               CONCAT('Paciente EXISTENTE reutilizado (ID: ', p_patient_id, ').')
            ),
            IF(v_health_center_id IS NOT NULL,
               CONCAT(' Centro de salud vinculado (ID: ', v_health_center_id, ').'),
               ' Centro de salud no encontrado en catálogo (guardado como texto).'
            )
        );

        COMMIT;
    END IF;

END //

DELIMITER ;