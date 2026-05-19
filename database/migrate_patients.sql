-- Migración: crear pacientes desde órdenes existentes y vincularlos
-- Ejecutar una sola vez después de aplicar schema_pdf_email.sql

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

SELECT CONCAT('Pacientes migrados: ', COUNT(*)) AS resultado FROM patients;
