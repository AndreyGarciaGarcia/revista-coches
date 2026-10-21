-- =====================================================================
--  009 · Sin redes sociales
-- =====================================================================
--  El proyecto no mantiene perfiles en ninguna red social, así que no
--  se publican enlaces ni campos para ellos: ni en el pie de página, ni
--  en las fichas, ni en la configuración del panel.
--
--  Se eliminan las claves de configuración que servían para guardar
--  perfiles de Facebook, X/Twitter, Instagram y YouTube. Las
--  miniaturas y los vídeos incrustados de las fichas NO se tocan: son
--  contenido del catálogo, no una cuenta de la revista.
-- =====================================================================

DELETE FROM configuracion
 WHERE clave IN ('site.facebook', 'site.twitter', 'site.instagram', 'site.youtube');
