-- =====================================================================
--  007 · Galería por categoría y vídeos por vehículo
-- =====================================================================
--  Hasta ahora cada vehículo tenía UNA fotografía. Una ficha profesional
--  necesita varias tomas clasificadas (exterior, interior, motor y
--  detalles) y, si existe, material en vídeo.
--
--  · coches_imagenes.categoria permite agrupar la galería y filtrarla.
--  · coches_videos guarda el material audiovisual vinculado al coche.
--    Se admite YouTube (embed) o un archivo alojado en el propio sitio,
--    de modo que la ficha no dependa de terceros si se usa la 2.ª opción.
-- =====================================================================

ALTER TABLE coches_imagenes
    ADD COLUMN categoria ENUM('exterior', 'interior', 'motor', 'detalle')
        NOT NULL DEFAULT 'exterior'
        AFTER orden,
    ADD KEY idx_ci_categoria (coche_id, categoria);

CREATE TABLE IF NOT EXISTS coches_videos (
    id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    coche_id      INT UNSIGNED   NOT NULL,
    plataforma    ENUM('youtube', 'vimeo', 'archivo')
                  NOT NULL DEFAULT 'youtube',
    codigo        VARCHAR(64)    NOT NULL,
    titulo        VARCHAR(200)   NOT NULL,
    canal         VARCHAR(120)   NULL,
    duracion      VARCHAR(9)     NULL,
    -- Ruta relativa al documento raíz (p. ej. assets/img/cars/golf/video-01.jpg)
    miniatura     VARCHAR(255)   NULL,
    orden         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_video_coche (coche_id, plataforma, codigo),
    KEY idx_video_coche (coche_id, orden),
    CONSTRAINT fk_video_coche
        FOREIGN KEY (coche_id) REFERENCES coches (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

-- Vista de lectura: sólo vehículos visibles, con la ruta ya resuelta.
CREATE OR REPLACE VIEW v_coches_videos AS
SELECT v.id,
       v.coche_id,
       v.plataforma,
       v.codigo,
       v.titulo,
       v.canal,
       v.duracion,
       v.miniatura,
       v.orden,
       CASE
           WHEN v.plataforma = 'archivo' THEN CONCAT('/assets/img/', v.codigo)
           ELSE CONCAT('https://i.ytimg.com/vi/', v.codigo, '/hqdefault.jpg')
       END AS miniatura_url
  FROM coches_videos v
  JOIN coches c ON c.id = v.coche_id
 WHERE c.estado = 'publicado'
   AND c.borrado_en IS NULL;
