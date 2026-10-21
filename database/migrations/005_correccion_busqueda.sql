-- =====================================================================
--  005 · Corrección de sp_buscar_coches
-- =====================================================================
--  MySQL no admite variables de usuario en la cláusula LIMIT/OFFSET de
--  una consulta dinámica ("LIMIT @q_limit" produce el error 1064).
--  La solución conserva el principio de seguridad del procedimiento:
--  el SQL dinámico sigue construyéndose con identificadores fijos y los
--  límites viajan como parámetros vinculados de la consulta preparada
--  (EXECUTE ... USING), de modo que ningún dato del usuario se
--  interpola en la cadena.
-- =====================================================================

DROP PROCEDURE IF EXISTS sp_buscar_coches;

DELIMITER $$

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
    DECLARE v_where LONGTEXT DEFAULT 'c.estado = ''publicado'' AND c.borrado_en IS NULL';
    DECLARE v_order LONGTEXT;
    DECLARE v_count LONGTEXT;

    -- Los valores viajan en variables de sesión: la consulta preparada
    -- nunca interpola datos en la cadena del SQL dinámico.
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
          WHERE ', v_where
    );

    SET @q_count = v_count;
    PREPARE cnt FROM @q_count;
    EXECUTE cnt;
    DEALLOCATE PREPARE cnt;

    SET p_total = COALESCE(@q_total, 0);

    -- Límites como parámetros vinculados (nunca dentro de la cadena)
    SET @q_limite  = CAST(COALESCE(p_limite, 24) AS UNSIGNED);
    SET @q_offset  = CAST(COALESCE(p_offset, 0) AS UNSIGNED);

    SET @q_sql = CONCAT(
        'SELECT c.*, m.nombre AS marca, m.slug AS marca_slug, m.logo AS marca_logo
           FROM coches c
           JOIN marcas m ON m.id = c.marca_id
          WHERE ', v_where,
        ' ORDER BY ', v_order,
        ' LIMIT ? OFFSET ?'
    );

    PREPARE stmt FROM @q_sql;
    EXECUTE stmt USING @q_limite, @q_offset;
    DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;
