-- ============================================================
-- Catálogo de exámenes y parámetros estructurados
-- Ejecutar después de schema_auth.sql
-- ============================================================

-- Tipos de examen (indexados por CUPS)
CREATE TABLE IF NOT EXISTS exam_types (
    cups        VARCHAR(30)  NOT NULL PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parámetros por tipo de examen
-- sexo: 'M', 'F' o '*' (aplica a ambos)
-- edad_min / edad_max: en años, NULL = sin restricción
-- valor_min_ref / valor_max_ref: NULL cuando el parámetro es cualitativo
CREATE TABLE IF NOT EXISTS exam_parameters (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cups          VARCHAR(30)  NOT NULL,
    codigo        VARCHAR(50)  NOT NULL,   -- clave en values_json (ej: 'wbc', 'hemoglobina')
    nombre        VARCHAR(150) NOT NULL,   -- nombre legible (ej: 'Leucocitos')
    unidad        VARCHAR(50)  NULL,       -- '10³/µL', 'g/dL', '%', etc.
    valor_min_ref DECIMAL(10,4) NULL,
    valor_max_ref DECIMAL(10,4) NULL,
    sexo          CHAR(1)      NOT NULL DEFAULT '*',  -- 'M', 'F', '*'
    edad_min      TINYINT      NULL,
    edad_max      TINYINT      NULL,
    obligatorio   TINYINT(1)   NOT NULL DEFAULT 0,
    orden         SMALLINT     NOT NULL DEFAULT 0,    -- orden de presentación
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ep_exam_type FOREIGN KEY (cups) REFERENCES exam_types(cups) ON DELETE CASCADE,
    INDEX idx_ep_cups (cups),
    UNIQUE KEY uq_ep_cups_codigo_sexo_edad (cups, codigo, sexo, edad_min, edad_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores estructurados por resultado (EAV)
-- Complementa lab_results.values_json con datos tipados y validados
CREATE TABLE IF NOT EXISTS lab_result_values (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    lab_result_id    INT          NOT NULL,
    parameter_id     INT          NOT NULL,
    valor_numerico   DECIMAL(10,4) NULL,
    valor_texto      VARCHAR(255) NULL,
    flag             ENUM('normal','alto','bajo','critico','indeterminado') NOT NULL DEFAULT 'indeterminado',
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lrv_result    FOREIGN KEY (lab_result_id) REFERENCES lab_results(id) ON DELETE CASCADE,
    CONSTRAINT fk_lrv_parameter FOREIGN KEY (parameter_id)  REFERENCES exam_parameters(id),
    INDEX idx_lrv_result (lab_result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
