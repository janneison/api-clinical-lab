-- ============================================================
-- Antibiogramas (resultados microbiológicos de cultivos)
-- Un lab_result puede tener uno o más antibiogramas (uno por bacteria aislada)
-- ============================================================

CREATE TABLE IF NOT EXISTS antibiogramas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    lab_result_id       INT           NOT NULL,
    bacteria_aislada    VARCHAR(255)  NOT NULL,
    gram                VARCHAR(20)   NULL COMMENT 'positivo | negativo | n/a',
    tiempo_incubacion   VARCHAR(100)  NULL,
    gram_orina          TEXT          NULL COMMENT 'Texto del Gram directo de orina',
    observaciones       TEXT          NULL,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_antibiograma_lab_result
        FOREIGN KEY (lab_result_id) REFERENCES lab_results(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Items del antibiograma (un antibiótico por fila)
-- ============================================================

CREATE TABLE IF NOT EXISTS antibiograma_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    antibiograma_id     INT           NOT NULL,
    antibiotico         VARCHAR(150)  NOT NULL,
    cim                 VARCHAR(50)   NULL COMMENT 'Concentración Inhibitoria Mínima',
    sensibilidad        VARCHAR(50)   NULL COMMENT 'S | I | R',
    metodo              VARCHAR(100)  NULL COMMENT 'Kirby-Bauer, MIC, etc.',
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ab_item_antibiograma
        FOREIGN KEY (antibiograma_id) REFERENCES antibiogramas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
