-- =====================================================================
--  MotorPress · Migración 001 — Seguridad, identidad y auditoría
--  MySQL 8.0 · InnoDB · utf8mb4_0900_ai_ci (ordenación sin distinción
--  de mayúsculas/acentos: los UNIQUE de correo y slug no duplican).
--
--  Modelo: RBAC con roles jerárquicos (nivel 0-100), permisos
--  granulares, contraseñas con hash Argon2id, bloqueo progresivo de
--  cuentas, límite de peticiones y trazabilidad de auditoría.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Registro de migraciones aplicadas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    archivo     VARCHAR(120)      NOT NULL,
    checksum    CHAR(32)          NOT NULL,
    aplicada_en DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    duracion_ms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migrations_archivo (archivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Roles: jerarquía por "nivel" (mayor = más privilegios)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave       VARCHAR(30)      NOT NULL,
    nombre      VARCHAR(60)      NOT NULL,
    descripcion VARCHAR(255)     NULL,
    nivel       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    es_sistema  TINYINT(1)       NOT NULL DEFAULT 1,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_clave (clave),
    KEY idx_roles_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Permisos granulares asignables a roles
-- ---------------------------------------------------------------------
CREATE TABLE permisos (
    id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave   VARCHAR(60)       NOT NULL,
    nombre  VARCHAR(80)       NOT NULL,
    grupo   VARCHAR(40)       NOT NULL DEFAULT 'general',
    PRIMARY KEY (id),
    UNIQUE KEY uq_permisos_clave (clave),
    KEY idx_permisos_grupo (grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Matriz role x permiso
-- ---------------------------------------------------------------------
CREATE TABLE rol_permiso (
    rol_id     TINYINT UNSIGNED  NOT NULL,
    permiso_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rolpermiso_rol
        FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rolpermiso_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Usuarios (autores del blog y administradores)
--  · email con collation insensible a mayúsculas -> UNIQUE efectivo
--  · password_hash guarda Argon2id (coste+salt+verificación en uno)
--  · intentos_fallidos / bloqueado_hasta = bloqueo progresivo
--  · deleted_at = borrado lógico: nunca se pierde historial
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    uuid               CHAR(36)         NOT NULL,
    email              VARCHAR(190)     NOT NULL,
    password_hash      VARCHAR(255)     NOT NULL,
    nombre             VARCHAR(100)     NOT NULL,
    apellidos          VARCHAR(120)     NULL,
    bio                VARCHAR(400)     NULL,
    avatar             VARCHAR(255)     NULL,
    web                VARCHAR(255)     NULL,
    rol_id             TINYINT UNSIGNED NOT NULL,
    estado             ENUM('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
    email_verificado_at DATETIME        NULL,
    password_cambiado_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    intentos_fallidos  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta    DATETIME         NULL,
    ultimo_acceso      DATETIME         NULL,
    ultima_ip          VARCHAR(45)      NULL,
    creado_en          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    borrado_en         DATETIME         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_uuid (uuid),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_rol (rol_id),
    KEY idx_usuarios_estado (estado, borrado_en),
    KEY idx_usuarios_ultimo_acceso (ultimo_acceso),
    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES roles (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_usuarios_email CHECK (email LIKE '%_@_%.__%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Auditoría inmutable: quién hizo qué, cuándo y desde dónde.
--  Se alimenta desde un trigger (no desde PHP) para que ningún
--  proceso pueda "olvidarse" de registrar una acción.
-- ---------------------------------------------------------------------
CREATE TABLE auditoria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    INT UNSIGNED    NULL,
    actor_email VARCHAR(190)    NULL,
    accion      VARCHAR(60)     NOT NULL,
    entidad     VARCHAR(60)     NULL,
    entidad_id  BIGINT UNSIGNED NULL,
    datos       JSON            NULL,
    ip          VARCHAR(45)     NULL,
    user_agent  VARCHAR(512)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_actor (actor_id, created_at),
    KEY idx_auditoria_accion (accion, created_at),
    KEY idx_auditoria_entidad (entidad, entidad_id),
    CONSTRAINT fk_auditoria_actor
        FOREIGN KEY (actor_id) REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
--  Límite de peticiones persistente (no se pierde al reiniciar PHP)
-- ---------------------------------------------------------------------
CREATE TABLE rate_limits (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave_hash    CHAR(64)        NOT NULL,
    intentos      INT UNSIGNED    NOT NULL DEFAULT 0,
    ventana_hasta DATETIME        NOT NULL,
    actualizado   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_limits_clave (clave_hash),
    KEY idx_rate_limits_purga (actualizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Configuración del sitio (pares clave/valor versionadas)
-- ---------------------------------------------------------------------
CREATE TABLE configuracion (
    clave        VARCHAR(60)      NOT NULL,
    valor        TEXT             NULL,
    tipo         ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
    descripcion  VARCHAR(255)     NULL,
    editable     TINYINT(1)       NOT NULL DEFAULT 1,
    actualizado_en DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Claves API (integraciones externas que sólo leen el catálogo)
-- ---------------------------------------------------------------------
CREATE TABLE api_keys (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(80)  NOT NULL,
    prefijo      VARCHAR(16)  NOT NULL,
    hash         CHAR(64)     NOT NULL,
    permiso      ENUM('lectura','lectura_escritura') NOT NULL DEFAULT 'lectura',
    ultimo_uso   DATETIME     NULL,
    ip_permitida VARCHAR(45)  NULL,
    activa       TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_keys_prefijo (prefijo),
    UNIQUE KEY uq_api_keys_hash (hash),
    KEY idx_api_keys_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
