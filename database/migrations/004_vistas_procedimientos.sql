-- =====================================================================
--  MotorPress · Migración 004 — Vistas, funciones, procedimientos,
--  disparadores y seguridad a nivel de base de datos.
--
--  Todo lo que puede "olvidarse" desde PHP está aquí:
--   · los slugs y las fechas de publicación se calculan en la BD
--   · la auditoría se escribe desde un trigger (no desde la app)
--   · los contadores y la moderación pasan por procedimientos
-- =====================================================================

SET NAMES utf8mb4;

DELIMITER $$

-- =====================================================================
--  FUNCIONES
-- =====================================================================

-- ---------------------------------------------------------------------
--  fn_slug: normaliza un texto a identificador de URL en español.
--  Se usa desde los disparadores para que un título mal tecleado en la
--  aplicación nunca introduzca un slug inválido.
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_slug(p_texto VARCHAR(255))
RETURNS VARCHAR(220)
DETERMINISTIC
BEGIN
    DECLARE v VARCHAR(255);

    SET v = LOWER(TRIM(COALESCE(p_texto, '')));
    SET v = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                v, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'),
                'ú', 'u'), 'ü', 'u'), 'ñ', 'n'), 'ç', 'c');
    SET v = REGEXP_REPLACE(v, '[^a-z0-9]+', '-');
    SET v = TRIM(BOTH '-' FROM v);

    IF v = '' THEN
        SET v = 'sin-titulo';
    END IF;

    RETURN LEFT(v, 220);
END$$

-- ---------------------------------------------------------------------
--  fn_palabras: cuenta palabras aproximadas quitando etiquetas HTML.
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_palabras(p_texto MEDIUMTEXT)
RETURNS INT UNSIGNED
DETERMINISTIC
BEGIN
    DECLARE v MEDIUMTEXT;

    SET v = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                COALESCE(p_texto, ''),
                '<p>', ' '), '</p>', ' '),
                '<br>', ' '), '<br/>', ' '),
                '<h2>', ' '), '</h2>', ' ');
    SET v = REGEXP_REPLACE(v, '<[^>]*>', ' ');

    IF TRIM(v) = '' THEN
        RETURN 0;
    END IF;

    RETURN CHAR_LENGTH(v) - CHAR_LENGTH(REPLACE(TRIM(v), ' ', '')) + 1;
END$$

-- ---------------------------------------------------------------------
--  fn_lectura: minutos de lectura (200 palabras por minuto, mín. 1).
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_lectura(p_texto MEDIUMTEXT)
RETURNS SMALLINT UNSIGNED
DETERMINISTIC
BEGIN
    DECLARE w INT UNSIGNED DEFAULT fn_palabras(p_texto);
    DECLARE m SMALLINT UNSIGNED;

    SET m = CEILING(w / 200);
    IF m < 1 OR m IS NULL THEN
        SET m = 1;
    END IF;

    RETURN LEAST(m, 180);
END$$

-- ---------------------------------------------------------------------
--  fn_precio: formato español "45.900 €" (para informes y vistas).
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_precio(p_euros INT UNSIGNED)
RETURNS VARCHAR(30)
DETERMINISTIC
BEGIN
    IF p_euros IS NULL THEN
        RETURN '—';
    END IF;

    RETURN CONCAT(FORMAT(p_euros, 0, 'de_DE'), ' €');
END$$

-- Las VISTAS terminan en punto y coma: se cambia el delimitador
DELIMITER ;


-- =====================================================================
--  VISTAS  (consultas reutilizadas por la app, la API y los informes)
-- =====================================================================

-- ---------------------------------------------------------------------
--  Catálogo: modelo + marca + fotografía principal + número de fotos.
--  Sólo vehículos publicados y no borrados lógicamente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_coches_catalogo AS
SELECT
    c.id,
    c.uuid,
    c.slug,
    c.marca_id,
    m.nombre            AS marca,
    m.slug              AS marca_slug,
    m.logo              AS marca_logo,
    c.modelo,
    c.version,
    c.categoria,
    c.combustible,
    c.transmision,
    c.traccion,
    c.plazas,
    c.puertas,
    c.potencia_cv,
    c.potencia_kw,
    c.par_nm,
    c.aceleracion_0_100,
    c.vel_maxima_kmh,
    c.consumo_l100,
    c.consumo_kwh100,
    c.autonomia_km,
    c.emisiones_gkm,
    c.maletero_litros,
    c.peso_kg,
    c.precio_nuevo,
    c.precio_ocasion,
    fn_precio(c.precio_nuevo)        AS precio_nuevo_txt,
    fn_precio(c.precio_ocasion)      AS precio_ocasion_txt,
    c.anio_desde,
    c.anio_hasta,
    c.resumen,
    c.valoracion,
    c.destacado,
    c.vistas,
    c.creado_en,
    ci.imagen_id                     AS imagen_principal_id,
    im.ruta                          AS imagen_principal,
    (SELECT COUNT(*) FROM coches_imagenes x WHERE x.coche_id = c.id) AS num_imagenes
FROM coches c
JOIN marcas m  ON m.id = c.marca_id
LEFT JOIN coches_imagenes ci ON ci.coche_id = c.id AND ci.es_principal = 1
LEFT JOIN imagenes im         ON im.id = ci.imagen_id
WHERE c.estado = 'publicado'
  AND c.borrado_en IS NULL
  AND m.activa = 1;

-- ---------------------------------------------------------------------
--  Ficha técnica completa de un vehículo (para la página de detalle)
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_coches_ficha AS
SELECT
    v.*,
    (SELECT ROUND(AVG(puntuacion), 2) FROM valoraciones w WHERE w.coche_id = v.id) AS valoracion_media,
    (SELECT COUNT(*)                 FROM valoraciones w WHERE w.coche_id = v.id) AS num_valoraciones,
    (SELECT COUNT(*)                 FROM noticias_coches nc
        JOIN noticias n ON n.id = nc.noticia_id
       WHERE nc.coche_id = v.id AND n.estado = 'publicado' AND n.borrado_en IS NULL) AS num_noticias,
    (SELECT MIN(periodo) FROM precios_historicos p WHERE p.coche_id = v.id) AS primer_precio_at,
    (SELECT precio_ocasion FROM precios_historicos p WHERE p.coche_id = v.id
      ORDER BY periodo DESC LIMIT 1) AS ultimo_precio_ocasion
FROM v_coches_catalogo v;

-- ---------------------------------------------------------------------
--  Noticias publicadas con sus relaciones resueltas
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_noticias_publicadas AS
SELECT
    n.id,
    n.uuid,
    n.slug,
    n.titulo,
    n.resumen,
    n.categoria_id,
    cat.nombre          AS categoria,
    cat.slug            AS categoria_slug,
    cat.color           AS categoria_color,
    n.autor_id,
    u.nombre            AS autor,
    u.avatar            AS autor_avatar,
    im.ruta             AS imagen,
    im.alt              AS imagen_alt,
    n.pie_foto,
    n.destacada,
    n.fijada,
    n.permitir_comentarios,
    n.vistas,
    n.tiempo_lectura,
    n.valoracion,
    n.publicado_at,
    n.actualizado_en,
    (SELECT COUNT(*) FROM comentarios co
      WHERE co.noticia_id = n.id AND co.estado = 'aprobado') AS num_comentarios
FROM noticias n
LEFT JOIN categorias cat ON cat.id = n.categoria_id
LEFT JOIN usuarios u     ON u.id = n.autor_id AND u.borrado_en IS NULL
LEFT JOIN imagenes im    ON im.id = n.imagen_id
WHERE n.estado = 'publicado'
  AND n.borrado_en IS NULL
  AND (n.publicado_at IS NULL OR n.publicado_at <= NOW());

-- ---------------------------------------------------------------------
--  Portada: primero fijadas, luego destacadas, resto por fecha
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_noticias_portada AS
SELECT * FROM v_noticias_publicadas
ORDER BY fijada DESC, destacada DESC, publicado_at DESC;

-- ---------------------------------------------------------------------
--  Marcas con número de modelos publicados (rejilla de marcas)
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_marcas_conteo AS
SELECT
    m.id,
    m.nombre,
    m.slug,
    m.logo,
    m.pais,
    m.web,
    m.orden,
    COUNT(c.id) AS num_modelos,
    MIN(c.precio_nuevo) AS precio_desde
FROM marcas m
LEFT JOIN coches c
       ON c.marca_id = m.id
      AND c.estado = 'publicado'
      AND c.borrado_en IS NULL
WHERE m.activa = 1
GROUP BY m.id, m.nombre, m.slug, m.logo, m.pais, m.web, m.orden
HAVING num_modelos > 0
ORDER BY num_modelos DESC, m.nombre ASC;

-- ---------------------------------------------------------------------
--  Texto de búsqueda pre-normalizado (evita funciones en el WHERE)
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_busqueda_coches AS
SELECT
    c.id,
    c.slug,
    CONCAT_WS(' ',
        m.nombre, c.modelo, c.version, c.categoria, c.combustible,
        LOWER(CONCAT_WS(' ', m.nombre, c.modelo, c.version))
    ) AS texto,
    c.precio_nuevo,
    c.precio_ocasion,
    c.potencia_cv,
    c.categoria,
    c.combustible,
    c.marca_id
FROM coches c
JOIN marcas m ON m.id = c.marca_id
WHERE c.estado = 'publicado' AND c.borrado_en IS NULL;

-- ---------------------------------------------------------------------
--  Panel de administración: indicadores del día
-- ---------------------------------------------------------------------
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_panel_resumen AS
SELECT
    (SELECT COUNT(*) FROM noticias  WHERE estado = 'publicado' AND borrado_en IS NULL) AS noticias_publicadas,
    (SELECT COUNT(*) FROM noticias  WHERE estado = 'borrador'  AND borrado_en IS NULL) AS noticias_borrador,
    (SELECT COUNT(*) FROM coches    WHERE estado = 'publicado' AND borrado_en IS NULL) AS coches_publicados,
    (SELECT COUNT(*) FROM comentarios WHERE estado = 'pendiente')                      AS comentarios_pendientes,
    (SELECT COUNT(*) FROM suscriptores WHERE estado = 'activo')                        AS suscriptores,
    (SELECT COALESCE(SUM(vistas),0) FROM noticias WHERE borrado_en IS NULL)            AS vistas_totales,
    (SELECT COUNT(*) FROM usuarios   WHERE borrado_en IS NULL)                         AS usuarios,
    (SELECT COUNT(*) FROM auditoria  WHERE created_at >= CURDATE())                    AS acciones_hoy;


-- Los PROCEDIMIENTOS y los DISPARADORES contienen punto y coma
-- internos, así que vuelven a exigir el delimitador \$\$.
DELIMITER $$

-- =====================================================================
--  PROCEDIMIENTOS ALMACENADOS
-- =====================================================================

-- ---------------------------------------------------------------------
--  sp_publicar_noticia: publica con transacción y marca la fecha.
--  Devuelve 1 si todo fue bien, 0 si la noticia no existe.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_publicar_noticia(IN p_id INT UNSIGNED, OUT pResultado INT)
MODIFIES SQL DATA
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pResultado = 0;
    END;

    START TRANSACTION;

    UPDATE noticias
       SET estado = 'publicado',
           publicado_at = COALESCE(publicado_at, NOW())
     WHERE id = p_id
       AND borrado_en IS NULL
       AND cuerpo IS NOT NULL
       AND CHAR_LENGTH(cuerpo) > 50;

    SET pResultado = ROW_COUNT();
    COMMIT;
END$$

-- ---------------------------------------------------------------------
--  sp_archivar_noticia: retira una noticia de la portada sin borrarla.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_archivar_noticia(IN p_id INT UNSIGNED, OUT pResultado INT)
MODIFIES SQL DATA
BEGIN
    START TRANSACTION;

    UPDATE noticias
       SET estado = 'archivado',
           destacada = 0,
           fijada = 0
     WHERE id = p_id AND borrado_en IS NULL;

    SET pResultado = ROW_COUNT();
    COMMIT;
END$$

-- ---------------------------------------------------------------------
--  sp_moderar_comentario: aprueba/rechaza y deja constancia.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_moderar_comentario(
    IN p_id      INT UNSIGNED,
    IN p_estado  ENUM('pendiente','aprobado','spam','rechazado'),
    IN p_motivo  VARCHAR(200)
)
MODIFIES SQL DATA
BEGIN
    UPDATE comentarios
       SET estado = p_estado,
           motivo = p_motivo,
           moderado_en = NOW()
     WHERE id = p_id;
END$$

-- ---------------------------------------------------------------------
--  sp_incrementar_vista: evita contar varias veces la misma visita en
--  menos de 30 minutos desde la misma IP para un mismo registro.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_incrementar_vista(IN p_tabla VARCHAR(12), IN p_id INT UNSIGNED, IN p_ip VARCHAR(45))
MODIFIES SQL DATA
BEGIN
    DECLARE v_clave CHAR(64);
    DECLARE v_existe INT DEFAULT 0;

    IF p_tabla NOT IN ('noticias', 'coches') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabla de vistas no permitida';
    END IF;

    SET v_clave = SHA2(CONCAT(p_tabla, ':', p_id, ':', COALESCE(p_ip, '')), 256);

    SELECT COUNT(*) INTO v_existe
      FROM rate_limits
     WHERE clave_hash = v_clave
       AND actualizado >= (NOW() - INTERVAL 30 MINUTE);

    IF v_existe = 0 THEN
        IF p_tabla = 'noticias' THEN
            UPDATE noticias SET vistas = vistas + 1 WHERE id = p_id;
        ELSE
            UPDATE coches SET vistas = vistas + 1 WHERE id = p_id;
        END IF;

        INSERT INTO rate_limits (clave_hash, intentos, ventana_hasta)
        VALUES (v_clave, 1, NOW() + INTERVAL 30 MINUTE)
        ON DUPLICATE KEY UPDATE intentos = intentos + 1, actualizado = NOW();
    END IF;
END$$

-- ---------------------------------------------------------------------
--  sp_buscar_coches: búsqueda con filtros dinámicos.
--  · La construcción dinámica sólo admite identificadores fijos
--    escritos en el propio procedimiento: no hay concatenación de
--    datos del usuario en los nombres de columna.
--  · Los valores viajan en variables de sesión (no en la cadena), de
--    modo que la consulta preparada nunca interpola datos.
--  · El orden sale de una lista blanca.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_buscar_coches(
    IN p_texto        VARCHAR(120),
    IN p_marcas       VARCHAR(400),
    IN p_categorias   VARCHAR(200),
    IN p_combustibles VARCHAR(200),
    IN p_precio_min   INT UNSIGNED,
    IN p_precio_max   INT UNSIGNED,
    IN p_potencia_min SMALLINT UNSIGNED,
    IN p_orden        VARCHAR(30),
    IN p_limite       INT UNSIGNED,
    IN p_offset       INT UNSIGNED,
    OUT p_total       INT UNSIGNED
)
READS SQL DATA
BEGIN
    DECLARE v_where LONGTEXT DEFAULT '1=1';
    DECLARE v_order LONGTEXT;
    DECLARE v_count LONGTEXT;

    -- Las variables de sesión reciben los parámetros
    SET @q_texto   = p_texto;
    SET @q_marcas  = p_marcas;
    SET @q_cats    = p_categorias;
    SET @q_comb    = p_combustibles;
    SET @q_pmin    = p_precio_min;
    SET @q_pmax    = p_precio_max;
    SET @q_potmin  = p_potencia_min;

    IF p_texto IS NOT NULL AND TRIM(p_texto) <> '' THEN
        SET v_where = CONCAT(v_where,
            " AND (CONCAT_WS(' ', m.nombre, c.modelo, COALESCE(c.version,''))",
            " LIKE CONCAT('%', @q_texto, '%'))");
    END IF;

    IF p_marcas IS NOT NULL AND p_marcas <> '' THEN
        SET v_where = CONCAT(v_where, " AND FIND_IN_SET(c.marca_id, @q_marcas) > 0");
    END IF;

    IF p_categorias IS NOT NULL AND p_categorias <> '' THEN
        SET v_where = CONCAT(v_where, " AND FIND_IN_SET(c.categoria, @q_cats) > 0");
    END IF;

    IF p_combustibles IS NOT NULL AND p_combustibles <> '' THEN
        SET v_where = CONCAT(v_where, " AND FIND_IN_SET(c.combustible, @q_comb) > 0");
    END IF;

    IF p_precio_min IS NOT NULL THEN
        SET v_where = CONCAT(v_where, " AND COALESCE(c.precio_nuevo, c.precio_ocasion) >= @q_pmin");
    END IF;

    IF p_precio_max IS NOT NULL THEN
        SET v_where = CONCAT(v_where, " AND COALESCE(c.precio_nuevo, c.precio_ocasion) <= @q_pmax");
    END IF;

    IF p_potencia_min IS NOT NULL THEN
        SET v_where = CONCAT(v_where, " AND COALESCE(c.potencia_cv, 0) >= @q_potmin");
    END IF;

    -- Lista blanca de ordenaciones: nunca se concatenan datos externos
    SET v_order = CASE COALESCE(p_orden, 'relevancia')
        WHEN 'precio_asc'  THEN 'c.precio_nuevo IS NULL, c.precio_nuevo ASC'
        WHEN 'precio_desc' THEN 'c.precio_nuevo DESC'
        WHEN 'potencia'    THEN 'c.potencia_cv DESC'
        WHEN 'novedades'   THEN 'c.anio_desde DESC'
        WHEN 'vistas'      THEN 'c.vistas DESC'
        ELSE 'c.destacado DESC, c.valoracion DESC, c.vistas DESC'
    END;

    SET v_count = CONCAT(
        'SELECT COUNT(*) INTO @q_total
           FROM coches c
           JOIN marcas m ON m.id = c.marca_id
          WHERE c.estado = ''publicado''
            AND c.borrado_en IS NULL
            AND ', v_where
    );

    SET @q_count = v_count;
    PREPARE cnt FROM @q_count;
    EXECUTE cnt;
    DEALLOCATE PREPARE cnt;

    SET p_total = COALESCE(@q_total, 0);

    SET @q_limit  = CAST(COALESCE(p_limite, 24) AS UNSIGNED);
    SET @q_offset = CAST(COALESCE(p_offset, 0) AS UNSIGNED);

    SET @q_sql = CONCAT(
        'SELECT c.*, m.nombre AS marca, m.slug AS marca_slug, m.logo AS marca_logo
           FROM coches c
           JOIN marcas m ON m.id = c.marca_id
          WHERE c.estado = ''publicado'' AND c.borrado_en IS NULL AND ', v_where,
        ' ORDER BY ', v_order,
        ' LIMIT @q_limit OFFSET @q_offset'
    );

    PREPARE stmt FROM @q_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

-- ---------------------------------------------------------------------
--  sp_mantenimiento: purga de datos caducados.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_mantenimiento(OUT p_purgados INT UNSIGNED)
MODIFIES SQL DATA
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_purgados = -1;
    END;

    START TRANSACTION;

    DELETE FROM rate_limits WHERE actualizado < (NOW() - INTERVAL 1 DAY);
    SET p_purgados = ROW_COUNT();

    DELETE FROM auditoria WHERE created_at < (NOW() - INTERVAL 1 YEAR);

    COMMIT;
END$$


-- =====================================================================
--  DISPARADORES
-- =====================================================================

-- ---------------------------------------------------------------------
--  Antes de insertar una noticia:
--   · genera uuid y slug (deduplicando) si no se proporcionan
--   · completa resumen a partir del cuerpo
--   · calcula el tiempo de lectura
--   · fija la fecha de publicación cuando procede
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_noticias_bi BEFORE INSERT ON noticias
FOR EACH ROW
BEGIN
    DECLARE v_slug VARCHAR(220);
    DECLARE v_final VARCHAR(220);
    DECLARE v_cnt INT UNSIGNED DEFAULT 0;
    DECLARE v_i INT UNSIGNED DEFAULT 2;

    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;

    IF NEW.tiempo_lectura IS NULL OR NEW.tiempo_lectura = 0 THEN
        SET NEW.tiempo_lectura = fn_lectura(NEW.cuerpo);
    END IF;

    IF NEW.resumen IS NULL OR NEW.resumen = '' THEN
        SET NEW.resumen = LEFT(REGEXP_REPLACE(REPLACE(REPLACE(NEW.cuerpo, '<p>', ' '), '</p>', ' '),
                             '<[^>]*>', ' '), 400);
    END IF;

    IF NEW.slug IS NULL OR NEW.slug = '' THEN
        SET v_slug = fn_slug(NEW.titulo);
    ELSE
        SET v_slug = fn_slug(NEW.slug);
    END IF;

    SET v_final = v_slug;
    SELECT COUNT(*) INTO v_cnt FROM noticias WHERE slug = v_final;
    WHILE v_cnt > 0 AND v_i < 1000 DO
        SET v_final = CONCAT(v_slug, '-', v_i);
        SELECT COUNT(*) INTO v_cnt FROM noticias WHERE slug = v_final;
        SET v_i = v_i + 1;
    END WHILE;
    SET NEW.slug = v_final;

    IF NEW.estado = 'publicado' AND NEW.publicado_at IS NULL THEN
        SET NEW.publicado_at = NOW();
    END IF;
END$$

-- ---------------------------------------------------------------------
--  Antes de actualizar: si cambia el título y no se tocó el slug a mano,
--  se regenera manteniendo la deduplicación. Audita cambios de estado.
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_noticias_bu BEFORE UPDATE ON noticias
FOR EACH ROW
BEGIN
    DECLARE v_slug VARCHAR(220);
    DECLARE v_final VARCHAR(220);
    DECLARE v_cnt INT UNSIGNED DEFAULT 0;
    DECLARE v_i INT UNSIGNED DEFAULT 2;

    IF NEW.titulo <> OLD.titulo AND (NEW.slug = OLD.slug OR NEW.slug = '') THEN
        SET v_slug = fn_slug(NEW.titulo);
        SET v_final = v_slug;
        SELECT COUNT(*) INTO v_cnt FROM noticias WHERE slug = v_final AND id <> NEW.id;
        WHILE v_cnt > 0 AND v_i < 1000 DO
            SET v_final = CONCAT(v_slug, '-', v_i);
            SELECT COUNT(*) INTO v_cnt FROM noticias WHERE slug = v_final AND id <> NEW.id;
            SET v_i = v_i + 1;
        END WHILE;
        SET NEW.slug = v_final;
    END IF;

    IF NEW.tiempo_lectura IS NULL OR NEW.tiempo_lectura = 0 THEN
        SET NEW.tiempo_lectura = fn_lectura(NEW.cuerpo);
    END IF;

    IF NEW.estado = 'publicado' AND NEW.publicado_at IS NULL THEN
        SET NEW.publicado_at = NOW();
    END IF;

    IF NEW.estado <> OLD.estado THEN
        INSERT INTO auditoria (actor_id, actor_email, accion, entidad, entidad_id, datos, ip, user_agent)
        VALUES (
            @mp_actor_id,
            @mp_actor_email,
            CONCAT('noticia.', NEW.estado),
            'noticias',
            NEW.id,
            JSON_OBJECT('de', OLD.estado, 'a', NEW.estado, 'titulo', NEW.titulo),
            @mp_ip,
            LEFT(@mp_ua, 512)
        );
    END IF;
END$$

-- ---------------------------------------------------------------------
--  Al insertar un vehículo: slug, uuid y potencia derivada.
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_coches_bi BEFORE INSERT ON coches
FOR EACH ROW
BEGIN
    DECLARE v_slug VARCHAR(220);
    DECLARE v_final VARCHAR(220);
    DECLARE v_cnt INT UNSIGNED DEFAULT 0;
    DECLARE v_i INT UNSIGNED DEFAULT 2;

    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;

    IF NEW.potencia_kw IS NULL AND NEW.potencia_cv IS NOT NULL THEN
        SET NEW.potencia_kw = ROUND(NEW.potencia_cv / 1.36);
    END IF;

    IF NEW.slug IS NULL OR NEW.slug = '' THEN
        SET v_slug = fn_slug(CONCAT_WS(' ', NEW.modelo, NEW.version));
    ELSE
        SET v_slug = fn_slug(NEW.slug);
    END IF;

    SET v_final = v_slug;
    SELECT COUNT(*) INTO v_cnt FROM coches WHERE slug = v_final;
    WHILE v_cnt > 0 AND v_i < 1000 DO
        SET v_final = CONCAT(v_slug, '-', v_i);
        SELECT COUNT(*) INTO v_cnt FROM coches WHERE slug = v_final;
        SET v_i = v_i + 1;
    END WHILE;
    SET NEW.slug = v_final;
END$$

-- ---------------------------------------------------------------------
--  Al actualizar un vehículo: mantener la potencia en kW y auditar
--  cambios de precio (clave para el histórico de mercado).
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_coches_bu BEFORE UPDATE ON coches
FOR EACH ROW
BEGIN
    IF NEW.potencia_cv IS NOT NULL
       AND (NEW.potencia_kw IS NULL OR NEW.potencia_cv <> OLD.potencia_cv) THEN
        SET NEW.potencia_kw = ROUND(NEW.potencia_cv / 1.36);
    END IF;

    IF NEW.precio_nuevo <> OLD.precio_nuevo OR NEW.precio_ocasion <> OLD.precio_ocasion THEN
        INSERT INTO auditoria (actor_id, actor_email, accion, entidad, entidad_id, datos, ip, user_agent)
        VALUES (
            @mp_actor_id,
            @mp_actor_email,
            'coche.precio',
            'coches',
            NEW.id,
            JSON_OBJECT(
                'precio_nuevo_de', OLD.precio_nuevo, 'precio_nuevo_a', NEW.precio_nuevo,
                'precio_ocasion_de', OLD.precio_ocasion, 'precio_ocasion_a', NEW.precio_ocasion
            ),
            @mp_ip,
            LEFT(@mp_ua, 512)
        );
    END IF;
END$$

-- ---------------------------------------------------------------------
--  Al insertar un usuario: generar el identificador público (uuid)
--  nunca queda vacío, venga de donde venga el registro.
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_usuarios_bi BEFORE INSERT ON usuarios
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;

    IF NEW.email IS NOT NULL THEN
        SET NEW.email = LOWER(TRIM(NEW.email));
    END IF;
END$$

-- ---------------------------------------------------------------------
--  Auditoría de cambios sensibles de usuarios (contraseña, rol, estado).
--  Nunca se guarda el hash, sólo que cambió.
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_usuarios_bu AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    IF NEW.password_hash <> OLD.password_hash THEN
        INSERT INTO auditoria (actor_id, actor_email, accion, entidad, entidad_id, datos, ip, user_agent)
        VALUES (@mp_actor_id, @mp_actor_email, 'usuario.password', 'usuarios', NEW.id,
                JSON_OBJECT('email', NEW.email), @mp_ip, LEFT(@mp_ua, 512));
    END IF;

    IF NEW.rol_id <> OLD.rol_id THEN
        INSERT INTO auditoria (actor_id, actor_email, accion, entidad, entidad_id, datos, ip, user_agent)
        VALUES (@mp_actor_id, @mp_actor_email, 'usuario.rol', 'usuarios', NEW.id,
                JSON_OBJECT('de', OLD.rol_id, 'a', NEW.rol_id, 'email', NEW.email),
                @mp_ip, LEFT(@mp_ua, 512));
    END IF;

    IF NEW.estado <> OLD.estado THEN
        INSERT INTO auditoria (actor_id, actor_email, accion, entidad, entidad_id, datos, ip, user_agent)
        VALUES (@mp_actor_id, @mp_actor_email, CONCAT('usuario.', NEW.estado), 'usuarios', NEW.id,
                JSON_OBJECT('email', NEW.email), @mp_ip, LEFT(@mp_ua, 512));
    END IF;
END$$

-- ---------------------------------------------------------------------
--  Al crear un suscriptores sin token de baja, se genera aquí (no
--  depende de que la aplicación se acuerde de hacerlo).
-- ---------------------------------------------------------------------
CREATE TRIGGER tr_suscriptores_bi BEFORE INSERT ON suscriptores
FOR EACH ROW
BEGIN
    IF NEW.token_baja IS NULL OR NEW.token_baja = '' THEN
        SET NEW.token_baja = SHA1(CONCAT(UUID(), RAND(), NOW(6)));
    END IF;

    IF NEW.consentimiento_at IS NULL THEN
        SET NEW.consentimiento_at = NOW();
    END IF;
END$$

DELIMITER ;

-- =====================================================================
--  DATOS DE CONFIGURACIÓN BASE
-- =====================================================================
INSERT IGNORE INTO configuracion (clave, valor, tipo, descripcion, editable) VALUES
    ('site.nombre',        'MotorPress',              'texto',    'Nombre del sitio', 1),
    ('site.descripcion',   'Revista de automoción y catálogo de vehículos', 'texto', 'Meta descripción', 1),
    ('site.palabras_clave','coches, noticias, motor, catálogo, comparador, precios', 'texto', 'Meta keywords', 1),
    ('site.email_contacto','contacto@motorpress.dev', 'texto',    'Correo de contacto', 1),
    ('site.telefono',      '+34 900 123 456',         'texto',    'Teléfono público', 1),
    ('site.direccion',     'Madrid, España',          'texto',    'Dirección fiscal', 1),
    ('site.facebook',      '',                        'texto',    'Perfil de Facebook', 1),
    ('site.twitter',       '',                        'texto',    'Perfil de X/Twitter', 1),
    ('site.instagram',     'https://instagram.com',   'texto',    'Perfil de Instagram', 1),
    ('site.youtube',       '',                        'texto',    'Canal de YouTube', 1),
    ('seo.canonical',      '',                        'texto',    'Dominio canónico (vacío = APP_URL)', 1),
    ('comentarios.moderacion', '1',                   'booleano', 'Los comentarios nuevos requieren aprobación', 1),
    ('comentarios.max_dia',    '5',                   'numero',   'Comentarios máximos por IP y día', 1),
    ('catalogo.por_pagina',    '12',                  'numero',   'Vehículos por página en el catálogo', 1),
    ('noticias.por_pagina',    '9',                   'numero',   'Noticias por página', 1),
    ('newsletter.alta',        '0',                   'booleano', 'Newsletter abierta a nuevos suscriptores', 1),
    ('mantenimiento',          '0',                   'booleano', 'Modo mantenimiento (1 = activo)', 1);
