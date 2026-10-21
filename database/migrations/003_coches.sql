-- =====================================================================
--  MotorPress · Migración 003 — Catálogo de vehículos
--  Ficha técnica en columnas tipadas (permiten filtrar por SQL sin
--  funciones en las columnas, lo que conserva los índices), histórico
--  de precios de mercado y biblioteca de fotografías por vehículo.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Marcas
-- ---------------------------------------------------------------------
CREATE TABLE marcas (
    id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(80)       NOT NULL,
    slug          VARCHAR(100)      NOT NULL,
    pais          VARCHAR(60)       NULL,
    fundacion_anio SMALLINT UNSIGNED NULL,
    logo          VARCHAR(255)      NULL,
    web           VARCHAR(255)      NULL,
    activa        TINYINT(1)        NOT NULL DEFAULT 1,
    orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marcas_slug (slug),
    UNIQUE KEY uq_marcas_nombre (nombre),
    KEY idx_marcas_activa (activa, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Modelos / vehículos
--  Unidades explícitas: euros, segundos, litros, gramos/km…
--  Los CHECK impiden que entre un dato imposible (precio negativo,
--  aceleración 0 o consumo negativo).
-- ---------------------------------------------------------------------
CREATE TABLE coches (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid              CHAR(36)     NOT NULL,
    marca_id          SMALLINT UNSIGNED NOT NULL,
    modelo            VARCHAR(120) NOT NULL,
    version           VARCHAR(140) NULL,
    slug              VARCHAR(220) NOT NULL,
    categoria         ENUM('turismo','berlina','suv','utilitario','deportivo','monovolumen','pickup','furgoneta','coupé','descapotable')
                                     NOT NULL DEFAULT 'turismo',
    anio_desde        SMALLINT UNSIGNED NOT NULL,
    anio_hasta        SMALLINT UNSIGNED NULL,
    estado            ENUM('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
    destacado         TINYINT(1)    NOT NULL DEFAULT 0,

    -- ---------------- Ficha técnica ----------------
    combustible       ENUM('gasolina','diesel','hibrido','hibrido_enchufable','electrico','gas_natural') NOT NULL DEFAULT 'gasolina',
    transmision       ENUM('manual','automatica','dct','cvt') NOT NULL DEFAULT 'manual',
    marchas           TINYINT UNSIGNED NULL,
    traccion          ENUM('delantera','trasera','cuatro_ruedas') NOT NULL DEFAULT 'delantera',
    plazas            TINYINT UNSIGNED NOT NULL DEFAULT 5,
    puertas           TINYINT UNSIGNED NOT NULL DEFAULT 5,

    potencia_cv       SMALLINT UNSIGNED NULL,
    potencia_kw       SMALLINT UNSIGNED NULL,
    par_nm            SMALLINT UNSIGNED NULL,
    cilindrada_cc     SMALLINT UNSIGNED NULL,
    cilindros         TINYINT UNSIGNED NULL,
    cilindraje_text   VARCHAR(40)  NULL,

    aceleracion_0_100 DECIMAL(4,1) NULL,
    vel_maxima_kmh    SMALLINT UNSIGNED NULL,
    consumo_l100      DECIMAL(3,1) NULL,
    consumo_kwh100    DECIMAL(4,1) NULL,
    autonomia_km      SMALLINT UNSIGNED NULL,
    emisiones_gkm     SMALLINT UNSIGNED NULL,
    bateria_kwh       DECIMAL(5,1) NULL,
    eficiencia        ENUM('A','B','C','D','E','F','G') NULL,

    maletero_litros   SMALLINT UNSIGNED NULL,
    largo_mm          SMALLINT UNSIGNED NULL,
    ancho_mm          SMALLINT UNSIGNED NULL,
    alto_mm           SMALLINT UNSIGNED NULL,
    distancia_ejes_mm SMALLINT UNSIGNED NULL,
    peso_kg           SMALLINT UNSIGNED NULL,
    carga_util_kg     SMALLINT UNSIGNED NULL,

    -- ---------------- Comercial ----------------
    precio_nuevo      INT UNSIGNED  NULL,
    precio_ocasion    INT UNSIGNED  NULL,
    precio_desde_txt  VARCHAR(60)   NULL,

    -- ---------------- Contenido ----------------
    resumen           VARCHAR(400)  NULL,
    descripcion       MEDIUMTEXT    NULL,
    puntos_fuertes    JSON          NULL,
    puntos_debiles    JSON          NULL,
    valoracion        DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    vistas            INT UNSIGNED  NOT NULL DEFAULT 0,
    destacado_por     DATETIME      NULL,

    creado_en         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    borrado_en        DATETIME      NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_coches_uuid (uuid),
    UNIQUE KEY uq_coches_slug (slug),

    -- Índices de filtrado: cubren el 95 % de las consultas del catálogo
    KEY idx_coches_portada (estado, destacado, borrado_en),
    KEY idx_coches_marca (marca_id, estado),
    KEY idx_coches_categoria (categoria, estado, precio_nuevo),
    KEY idx_coches_precio (estado, precio_nuevo),
    KEY idx_coches_ocasion (estado, precio_ocasion),
    KEY idx_coches_combustible (estado, combustible, potencia_cv),
    KEY idx_coches_potencia (estado, potencia_cv),
    KEY idx_coches_aceleracion (estado, aceleracion_0_100),
    KEY idx_coches_vistas (vistas DESC),
    FULLTEXT KEY ft_coches (modelo, version, resumen, descripcion),

    CONSTRAINT fk_coches_marca
        FOREIGN KEY (marca_id) REFERENCES marcas (id) ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_coches_precio       CHECK (precio_nuevo IS NULL OR precio_nuevo > 0),
    CONSTRAINT chk_coches_ocasion      CHECK (precio_ocasion IS NULL OR precio_ocasion > 0),
    CONSTRAINT chk_coches_potencia     CHECK (potencia_cv IS NULL OR potencia_cv BETWEEN 1 AND 2000),
    CONSTRAINT chk_coches_aceleracion  CHECK (aceleracion_0_100 IS NULL OR aceleracion_0_100 > 0),
    CONSTRAINT chk_coches_consumo      CHECK (consumo_l100 IS NULL OR consumo_l100 >= 0),
    CONSTRAINT chk_coches_emisiones    CHECK (emisiones_gkm IS NULL OR emisiones_gkm >= 0),
    CONSTRAINT chk_coches_anios        CHECK (anio_hasta IS NULL OR anio_hasta >= anio_desde),
    CONSTRAINT chk_coches_plazas       CHECK (plazas BETWEEN 1 AND 9),
    CONSTRAINT chk_coches_puertas      CHECK (puertas BETWEEN 2 AND 7),
    CONSTRAINT chk_coches_valoracion   CHECK (valoracion BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Fotografías por vehículo (varias por modelo, una principal)
-- ---------------------------------------------------------------------
CREATE TABLE coches_imagenes (
    coche_id   INT UNSIGNED NOT NULL,
    imagen_id  INT UNSIGNED NOT NULL,
    orden      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    pie        VARCHAR(255) NULL,
    PRIMARY KEY (coche_id, imagen_id),
    KEY idx_ci_imagen (imagen_id),
    KEY idx_ci_principal (coche_id, es_principal),
    CONSTRAINT fk_ci_coche
        FOREIGN KEY (coche_id) REFERENCES coches (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ci_imagen
        FOREIGN KEY (imagen_id) REFERENCES imagenes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Histórico de precios de mercado (base de las evoluciones y alertas)
-- ---------------------------------------------------------------------
CREATE TABLE precios_historicos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coche_id        INT UNSIGNED NOT NULL,
    periodo         DATE         NOT NULL,
    precio_nuevo    INT UNSIGNED NULL,
    precio_ocasion  INT UNSIGNED NULL,
    demanda         ENUM('muy_alta','alta','media','baja','muy_baja') NULL,
    unidades        SMALLINT UNSIGNED NULL,
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_precios (coche_id, periodo),
    KEY idx_precios_periodo (periodo),
    CONSTRAINT fk_precios_coche
        FOREIGN KEY (coche_id) REFERENCES coches (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Valoraciones de usuarios (autenticados) sobre un modelo
-- ---------------------------------------------------------------------
CREATE TABLE valoraciones (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coche_id   INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    puntuacion TINYINT UNSIGNED NOT NULL,
    comentario VARCHAR(600) NULL,
    creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_valoraciones (coche_id, usuario_id),
    KEY idx_valoraciones_coche (coche_id),
    CONSTRAINT fk_val_coche
        FOREIGN KEY (coche_id) REFERENCES coches (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_val_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_val_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
--  Noticias que citan un vehículo (m:n)
-- ---------------------------------------------------------------------
CREATE TABLE noticias_coches (
    noticia_id INT UNSIGNED NOT NULL,
    coche_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (noticia_id, coche_id),
    KEY idx_nc_coche (coche_id),
    CONSTRAINT fk_nc_noticia
        FOREIGN KEY (noticia_id) REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nc_coche
        FOREIGN KEY (coche_id) REFERENCES coches (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
