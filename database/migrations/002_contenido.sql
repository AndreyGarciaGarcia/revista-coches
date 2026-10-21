-- =====================================================================
--  MotorPress · Migración 002 — Contenido editorial
--  Categorías jerárquicas, etiquetas, noticias con estados y borrado
--  lógico, comentarios moderados y suscriptores con consentimiento
--  expreso (RGPD).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Biblioteca de medios: control de duplicados por hash y de licencias
-- ---------------------------------------------------------------------
CREATE TABLE imagenes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    hash        CHAR(40)     NOT NULL,
    ruta        VARCHAR(255) NOT NULL,
    titulo      VARCHAR(180) NULL,
    alt         VARCHAR(200) NOT NULL,
    autor       VARCHAR(120) NULL,
    licencia    VARCHAR(40)  NULL,
    fuente_url  VARCHAR(300) NULL,
    ancho       SMALLINT UNSIGNED NULL,
    alto        SMALLINT UNSIGNED NULL,
    peso_kb     SMALLINT UNSIGNED NULL,
    tipo        ENUM('jpg','png','webp','avif','gif','svg') NOT NULL DEFAULT 'jpg',
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_imagenes_hash (hash),
    UNIQUE KEY uq_imagenes_ruta (ruta),
    KEY idx_imagenes_licencia (licencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Categorías (jerárquicas, admiten subcategorías)
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(80)       NOT NULL,
    slug         VARCHAR(100)      NOT NULL,
    descripcion  VARCHAR(400)      NULL,
    color        CHAR(7)           NULL,
    icono        VARCHAR(40)       NULL,
    parent_id    SMALLINT UNSIGNED NULL,
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa       TINYINT(1)        NOT NULL DEFAULT 1,
    creado_en    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_slug (slug),
    KEY idx_categorias_padre (parent_id, orden),
    CONSTRAINT fk_categorias_padre
        FOREIGN KEY (parent_id) REFERENCES categorias (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Etiquetas (m:n con noticias)
-- ---------------------------------------------------------------------
CREATE TABLE etiquetas (
    id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre    VARCHAR(60)       NOT NULL,
    slug      VARCHAR(80)       NOT NULL,
    creado_en DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_etiquetas_slug (slug),
    UNIQUE KEY uq_etiquetas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Noticias
--  · estado: borrador -> revisado -> publicado -> archivado
--  · borrado_en = soft delete (los listados filtran con IS NULL)
--  · FULLTEXT alimenta el buscador sin LIKE '%…%'
-- ---------------------------------------------------------------------
CREATE TABLE noticias (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36)     NOT NULL,
    titulo          VARCHAR(200) NOT NULL,
    slug            VARCHAR(220) NOT NULL,
    resumen         VARCHAR(400) NULL,
    cuerpo          MEDIUMTEXT   NOT NULL,
    categoria_id    SMALLINT UNSIGNED NULL,
    autor_id        INT UNSIGNED NULL,
    imagen_id       INT UNSIGNED NULL,
    pie_foto        VARCHAR(255) NULL,
    estado          ENUM('borrador','revisado','publicado','archivado') NOT NULL DEFAULT 'borrador',
    destacada       TINYINT(1)   NOT NULL DEFAULT 0,
    fijada          TINYINT(1)   NOT NULL DEFAULT 0,
    permitir_comentarios TINYINT(1) NOT NULL DEFAULT 1,
    vistas          INT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_lectura  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    valoracion      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    publicado_at    DATETIME     NULL,
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    borrado_en      DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_noticias_uuid (uuid),
    UNIQUE KEY uq_noticias_slug (slug),
    KEY idx_noticias_portada (estado, fijada, destacada, publicado_at DESC),
    KEY idx_noticias_categoria (categoria_id, estado, publicado_at DESC),
    KEY idx_noticias_autor (autor_id, publicado_at DESC),
    KEY idx_noticias_publicado (publicado_at DESC),
    KEY idx_noticias_vistas (vistas DESC),
    FULLTEXT KEY ft_noticias (titulo, resumen, cuerpo),
    CONSTRAINT fk_noticias_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_noticias_autor
        FOREIGN KEY (autor_id) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_noticias_imagen
        FOREIGN KEY (imagen_id) REFERENCES imagenes (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Relación noticias <-> etiquetas
-- ---------------------------------------------------------------------
CREATE TABLE noticias_etiquetas (
    noticia_id INT UNSIGNED      NOT NULL,
    etiqueta_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (noticia_id, etiqueta_id),
    KEY idx_ne_etiqueta (etiqueta_id),
    CONSTRAINT fk_ne_noticia
        FOREIGN KEY (noticia_id) REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ne_etiqueta
        FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Relación noticias <-> coches (se rellena en la migración 003)
--  Se crea aquí para que el trigger de noticias no falle al insertar
--  una noticia que cite un modelo concreto.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
--  Comentarios con moderación y control anti-spam
-- ---------------------------------------------------------------------
CREATE TABLE comentarios (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    noticia_id   INT UNSIGNED NOT NULL,
    usuario_id   INT UNSIGNED NULL,
    parent_id    INT UNSIGNED NULL,
    autor        VARCHAR(80)  NOT NULL,
    email        VARCHAR(190) NULL,
    contenido    TEXT         NOT NULL,
    ip           VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    estado       ENUM('pendiente','aprobado','spam','rechazado') NOT NULL DEFAULT 'pendiente',
    motivo       VARCHAR(200) NULL,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    moderado_en  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_comentarios_noticia (noticia_id, estado, creado_en),
    KEY idx_comentarios_estado (estado, creado_en),
    KEY idx_comentarios_ip (ip, creado_en),
    CONSTRAINT fk_comentarios_noticia
        FOREIGN KEY (noticia_id) REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_comentarios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_comentarios_padre
        FOREIGN KEY (parent_id) REFERENCES comentarios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Suscriptores de la newsletter
--  · consentimiento RGPD con marca temporal e IP
--  · token de baja de un solo uso
-- ---------------------------------------------------------------------
CREATE TABLE suscriptores (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(190) NOT NULL,
    nombre         VARCHAR(80)  NULL,
    token_baja     CHAR(40)     NOT NULL,
    consentimiento_at DATETIME  NOT NULL,
    consentimiento_ip  VARCHAR(45) NULL,
    estado         ENUM('activo','pendiente','baja') NOT NULL DEFAULT 'activo',
    confirmado_at  DATETIME     NULL,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_suscriptores_email (email),
    UNIQUE KEY uq_suscriptores_token (token_baja),
    KEY idx_suscriptores_estado (estado, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Registro de envíos (traceabilidad de comunicaciones)
-- ---------------------------------------------------------------------
CREATE TABLE envios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo           ENUM('newsletter','baja','bienvenida','contacto','password_reset') NOT NULL,
    asunto         VARCHAR(200) NOT NULL,
    destinatarios  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    estado         ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
    detalle        VARCHAR(400) NULL,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_envios_tipo (tipo, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
