-- ============================================================
-- Auth: roles, aliados, users, user_aliado
-- Ejecutar después de schema.sql
-- ============================================================

-- Eliminar tabla users anterior (sin relaciones aún)
DROP TABLE IF EXISTS users;

-- Roles del sistema
CREATE TABLE roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE  -- admin | lab_operator | aliado_operator | viewer
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name) VALUES
    ('admin'),
    ('lab_operator'),
    ('aliado_operator'),
    ('viewer');

-- Aliados / laboratorios clínicos externos
CREATE TABLE aliados (
    id         VARCHAR(100) PRIMARY KEY,
    nombre     VARCHAR(200) NOT NULL,
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuarios del sistema
CREATE TABLE users (
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

-- Relación usuario ↔ aliado (un usuario puede pertenecer a varios aliados)
CREATE TABLE user_aliado (
    user_id   INT          NOT NULL,
    aliado_id VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, aliado_id),
    CONSTRAINT fk_ua_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ua_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
