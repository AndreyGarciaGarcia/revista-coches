-- =====================================================================
--  006 · Bandeja de mensajes de contacto
-- =====================================================================
--  La web permite escribir a la redacción. Hasta ahora esa petición se
--  registraba sólo en el log; una operación real necesita una tabla
--  propia con ciclo de vida (nuevo → en curso → resuelto) para que el
--  panel de administración pueda gestionarla.
-- =====================================================================

CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    uuid           CHAR(36)       NOT NULL,
    nombre         VARCHAR(120)   NOT NULL,
    email          VARCHAR(190)   NOT NULL,
    asunto         VARCHAR(160)   NOT NULL,
    mensaje        TEXT           NOT NULL,
    ip             VARCHAR(45)    NULL,
    user_agent     VARCHAR(255)   NULL,
    estado         ENUM('nuevo', 'en_curso', 'resuelto', 'spam')
                   NOT NULL DEFAULT 'nuevo',
    usuario_id     INT UNSIGNED   NULL,
    gestionado_por INT UNSIGNED   NULL,
    respondido_en  DATETIME       NULL,
    creado_en      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mensajes_uuid (uuid),
    KEY idx_mensajes_estado (estado, creado_en),
    KEY idx_mensajes_usuario (usuario_id),
    KEY idx_mensajes_email (email),
    CONSTRAINT fk_mensajes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_mensajes_gestor
        FOREIGN KEY (gestionado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

-- Identificador público generado por la propia base de datos
DELIMITER $$
CREATE TRIGGER tr_mensajes_bi BEFORE INSERT ON mensajes_contacto
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
END$$
DELIMITER ;

-- Vista para el panel: lo pendiente primero
CREATE OR REPLACE VIEW v_mensajes_pendientes AS
SELECT m.id,
       m.uuid,
       m.nombre,
       m.email,
       m.asunto,
       m.mensaje,
       m.estado,
       m.creado_en,
       u.nombre AS usuario
  FROM mensajes_contacto m
  LEFT JOIN usuarios u ON u.id = m.usuario_id
 WHERE m.estado <> 'spam'
 ORDER BY FIELD(m.estado, 'nuevo', 'en_curso', 'resuelto'), m.creado_en DESC;
