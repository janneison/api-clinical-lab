-- ============================================================
-- Rol médico + asociación usuario ↔ centros de salud
-- Idempotente — se puede ejecutar varias veces sin error
-- ============================================================

-- 1. Insertar rol 'medico' si no existe
INSERT IGNORE INTO roles (name) VALUES ('medico');

-- 2. Tabla user_health_center (usuario puede estar en varios centros)
CREATE TABLE IF NOT EXISTS user_health_center (
    user_id          INT NOT NULL,
    health_center_id INT NOT NULL,
    PRIMARY KEY (user_id, health_center_id),
    CONSTRAINT fk_uhc_user   FOREIGN KEY (user_id)          REFERENCES users(id)           ON DELETE CASCADE,
    CONSTRAINT fk_uhc_hc     FOREIGN KEY (health_center_id) REFERENCES health_centers(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
