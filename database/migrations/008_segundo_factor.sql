-- =====================================================================
--  008 · Segundo factor de autenticación (TOTP)
-- =====================================================================
--  El panel exige tres cosas para entrar:
--     1. la contraseña (algo que sabes),
--     2. la clave maestra guardada en .env (algo que sabes y nadie más),
--     3. el código de 6 dígitos de la app del móvil (algo que tienes).
--
--  El secreto TOTP se guarda CIFRADO con AES-256-GCM (ver
--  App\Core\Cifrado), de modo que quien filtre la base de datos sin
--  tener también la APP_KEY no puede generar los códigos.
--
--  · totp_secreto      → criptograma binario (IV + etiqueta + texto).
--  · totp_activado_en  → cuándo se completó el emparejamiento; NULL
--                        significa «segundo factor aún no configurado».
-- =====================================================================

ALTER TABLE usuarios
    ADD COLUMN totp_secreto VARBINARY(255) NULL
        AFTER password_hash,
    ADD COLUMN totp_activado_en DATETIME NULL
        AFTER totp_secreto;
