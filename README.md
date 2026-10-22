# MotorPress — Revista de coches

**Revista digital + catálogo completo de vehículos**, con estética de **prensa de motor**: blanco
y negro con un único acento rojo de señal (`#d1261b`), titulares condensados en Oswald y rejilla
editorial. Incluye portada, noticias con comentarios, ficha técnica de coches, filtros avanzados,
comparador, buscador global, API REST pública y panel de administración **blindado con clave
maestra y segundo factor TOTP**.

Construido **sin frameworks ni dependencias externas**: PHP 8, MySQL 8, JavaScript vanilla y CSS propio.

---

## Índice

1. [Características](#1-características)
2. [Requisitos](#2-requisitos)
3. [Instalación en cinco minutos](#3-instalación-en-cinco-minutos)
4. [Configuración (.env)](#4-configuración-env)
5. [Estructura del proyecto](#5-estructura-del-proyecto)
6. [Base de datos a nivel empresarial](#6-base-de-datos-a-nivel-empresarial)
7. [Seguridad](#7-seguridad)
8. [Scripts de consola](#8-scripts-de-consola)
9. [Rutas de la aplicación](#9-rutas-de-la-aplicación)
10. [API REST v1](#10-api-rest-v1)
11. [Pruebas y verificación](#11-pruebas-y-verificación)
12. [Copias de seguridad y restauración](#12-copias-de-seguridad-y-restauración)
13. [Mantenimiento programado](#13-mantenimiento-programado)
14. [Solución de problemas](#14-solución-de-problemas)
15. [Despliegue en producción](#15-despliegue-en-producción)
16. [Créditos](#16-créditos)

---

## 1. Características

### Público
- **Portada** con hero, últimas noticias, coches destacados, más vistos y novedades del catálogo.
- **Revista**: categorías, etiquetas, artículos con galería, esquema JSON‑LD, vídeo incrustado y **comentarios anidados con moderación**.
- **Catálogo de 30 vehículos** con filtros combinados (marca, categoría, combustible, precio, potencia), ordenación, paginación y **búsqueda FULLTEXT** mediante procedimiento almacenado.
- **Ficha de coche**: galería con *lightbox* y filtro por tipo de toma (exterior, interior, motor, detalle), vídeos con reproductor en diferido, tabla de especificaciones, puntos fuertes/débiles, histórico de precios, valoraciones, coches relacionados y noticias del modelo.
- **Comparador** de hasta 4 vehículos con tabla lado a lado y bandeja persistente.
- **Buscador global** (coches, noticias, marcas) con sugerencias.
- **Newsletter**, formulario de contacto con *honeypot* y aviso de cookies.
- Páginas legales, créditos fotográficos generados desde el manifiesto de imágenes, `robots.txt` y `sitemap.xml`.
- **Registro de cuentas cerrado por defecto** (`REGISTRO_ABIERTO=false`): nadie puede crear identidades
  en la plataforma; si algún día se habilita, las cuentas nuevas nacen como simples lectores.
- **Sin redes sociales**: el sitio no tiene perfiles ni botones de Instagram, X/Twitter, Facebook ni
  ninguna otra red. La única acción de enlace es «copiar enlace».
- **Dirección visual «prensa de motor»**: blanco y negro con un único acento rojo de señal `#d1261b`, titulares en **Oswald** condensada, texto en **IBM Plex Sans** y datos en **IBM Plex Mono**,
  filetes gruesos de 3 px, secciones numeradas (`01 / ACTUALIDAD`) y fotografía a sangre. Las tres familias
  se sirven **alojadas en el propio dominio** (`public/assets/fonts/`, subconjuntos *latin* y *latin-ext*,
  pesos variables) sin peticiones a terceros.
- Diseño **responsive** (escritorio, tablet, móvil), `prefers-reduced-motion` y estilos de impresión.

### Privado (`/admin`)
- Panel con métricas, actividad reciente y gráficos.
- CRUD de noticias (editor enriquecido, borrador/publicar/archivar, imagen destacada).
- CRUD de coches con decenas de campos técnicos, galería asignable y precios históricos.
- **Galería y vídeo por ficha**: subida de fotografías JPG (hasta 10 por envío), clasificación por tipo de toma, orden, foto principal y borrado; alta de vídeos de YouTube con validación, duración y miniatura local. Todas las operaciones quedan en la auditoría.
- Moderación de comentarios y bandeja de mensajes de contacto.
- Gestión de usuarios y roles, registro de auditoría y configuración del sitio.
- **Página «Seguridad»**: emparejamiento del segundo factor con QR, estado de las defensas y listado
  de todas las cuentas con su rol, estado y último acceso.
- **Acceso en tres pasos**: contraseña → clave maestra del `.env` → código TOTP de la app del móvil.
- **5 niveles de rol**: `lector` (0), `autor` (30), `editor` (50), `admin` (90), `root` (100).

---

## 2. Requisitos

| Componente | Versión mínima | Usado en desarrollo |
|---|---|---|
| PHP | 8.1 | 8.2.12 |
| Extensiones | `pdo_mysql`, `mbstring`, `json`, `dom`, `openssl`, `fileinfo` | — |
| MySQL | 8.0 | 8.0.46 |
| Servidor web | Apache 2.4 con `mod_rewrite`, `mod_headers` | 2.4.58 |

> No hace falta Composer, Node ni build step. No hay dependencias de terceros.

---

## 3. Instalación en cinco minutos

```bash
# 1. Configurar el entorno
copy .env.example .env        # y editar credenciales

# 2. Crear esquema, usuario de aplicación restringido, datos e imágenes
php scripts/install.php

# 3. (Opcional) descargar sólo las fotografías
php scripts/descargar-imagenes.php

# 4. (Opcional) ampliar la galería de cada coche y buscar vídeos
php scripts/descargar-galeria.php
php scripts/descargar-videos.php

# 5. Verificar que todo responde
php scripts/smoke.php
```

`scripts/install.php` hace, en este orden:

1. Crea la base de datos si no existe.
2. Crea el **usuario de aplicación con mínimos privilegios** (`SELECT, INSERT, UPDATE, DELETE, EXECUTE, SHOW VIEW`) en `localhost` y `127.0.0.1`.
3. Comprueba que ese usuario **no** tiene `GRANT OPTION` ni privilegios de DDL.
4. Aplica las 7 migraciones (esquema, vistas, procedimientos, disparadores).
5. Siembra contenidos: 19 marcas, 30 coches, 16 artículos, 166 fotografías, 30 vídeos, comentarios, usuarios y configuración.
6. Verifica la conexión con el usuario restringido.

Útiles adicionales:

```bash
php scripts/install.php --force      # reinstala desde cero (borra el esquema)
php scripts/install.php --no-seed    # sólo estructura
php scripts/install.php --no-images  # no descarga fotografías
php scripts/migrate.php --status     # estado de las migraciones
php scripts/seed.php --force         # vuelve a sembrar el contenido
```

Después, abrir en el navegador:

```
http://localhost/ruta-del-proyecto/
http://localhost/ruta-del-proyecto/admin
```

Si el `DocumentRoot` de Apache es la raíz de XAMPP (`C:/xampp/htdocs`), el `.htaccess` de la raíz del proyecto redirige todo el tráfico hacia `public/`, de modo que **sólo ese directorio queda expuesto**.

**Acceso de administrador**: el correo de `ADMIN_EMAIL` y la contraseña de `ADMIN_PASSWORD`,
ambas definidas en tu `.env` (que nunca se sube al repositorio). El instalador imprime la
contraseña una sola vez por consola: apúntala en tu gestor de contraseñas. Si la pierdes:

```bash
php scripts/make-admin.php --listar
php scripts/make-admin.php --nuevo=correo@ejemplo.com --pass='Frase larga y segura 2026!'
```

---

## 4. Configuración (.env)

`.env` **nunca se sube al repositorio** (está en `.gitignore`). Plantilla en `.env.example`.

| Variable | Descripción |
|---|---|
| `APP_URL` | URL pública; de ella se deriva la base de las rutas y del enlace canónico |
| `APP_ENV` / `APP_DEBUG` | Entorno y detalle de errores (nunca `true` en producción) |
| `APP_TIMEZONE` | Zona horaria (por defecto `Europe/Madrid`) |
| `DB_HOST`, `DB_PORT`, `DB_NAME` | Servidor y esquema |
| `DB_USER`, `DB_PASS` | Usuario de aplicación (mínimos privilegios) |
| `DB_ROOT_USER`, `DB_ROOT_PASS` | Credenciales de mantenimiento: **sólo** las usan los scripts de consola |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Cuenta inicial creada por el instalador. `ADMIN_EMAIL` es además la **lista blanca**: sólo esa cuenta puede entrar en el panel |
| `PANEL_NIVEL` | Nivel jerárquico mínimo exigido en `/admin` (90 = administrador) |
| `PANEL_INACTIVIDAD` | Segundos sin actividad antes de cerrar la sesión del panel (900 = 15 min) |
| `PANEL_CLAVE_HASH` | Hash Argon2id de la **clave maestra**; se crea o rota con `scripts/panel_clave.php` |
| `REGISTRO_ABIERTO` | `false` por defecto: registro público de cuentas desactivado |
| `SESSION_NAME`, `SESSION_LIFETIME` | Cookie de sesión y duración en segundos |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Remitente del correo saliente |
| `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW` | Intentos permitidos por ventana (login y API) |

---

## 5. Estructura del proyecto

```
revista-coches/
├── app/                    núcleo de la aplicación (PSR-4 App\)
│   ├── Console/            Migrator, Seeder
│   ├── Controllers/        público, admin, API v1
│   ├── Core/               App, Router, Database, Auth, Csrf, Security…
│   ├── Http/Middleware/    RequireAuth, RequireAdmin, RequireSuperAdmin,
│   │                       FormRateLimit, ApiRateLimit
│   ├── Repositories/       Coche, Noticia, Sitio, Admin
│   └── helpers.php         funciones globales de plantilla
├── config/                 app.php, security.php, routes.php
├── database/
│   ├── migrations/         001…006 (esquema, vistas, procedimientos, triggers)
│   └── seeds/              contenido + imagenes-manifest.json (créditos)
├── public/                 ÚNICO árbol servido por Apache
│   ├── index.php           front controller
│   └── assets/             css (app.css, fuentes.css), js, img, fonts
├── scripts/                instalación, migraciones, backup, humo…
├── storage/                cache, logs, sesiones (fuera del alcance web)
├── templates/              layouts, partials, pages (vistas PHP)
└── .htaccess               bloquea app/, config/, database/, storage/…
```

Convenciones:

- Enrutador con rutas limpias y middleware por grupo (`config/routes.php`).
- Controladores delgados; el acceso a datos vive en **repositorios**.
- Las vistas son plantillas PHP con escape automático mediante `e()`.
- Todo el HTML procedente del editor **pasa por una lista blanca** (`Security::htmlSeguro()`), al guardar y al mostrar.

---

## 6. Base de datos a nivel empresarial

**24 tablas · 8 vistas · 6 procedimientos · 4 funciones · 8 disparadores**

- Collation `utf8mb4_0900_ai_ci` en todo el esquema (soporte completo para emojis y acentos).
- **Integridad referencial** con `FOREIGN KEY` y `ON DELETE CASCADE/RESTRICT` en todas las relaciones.
- **Borrado lógico** (`borrado_en`) en usuarios, noticias y coches: el historial nunca se pierde.
- **RBAC**: `roles`, `permisos`, `rol_permiso`; el nivel decide qué ve el panel.
- **Auditoría** en tabla `auditoria` escrita **dos veces**: por disparadores MySQL (con las variables de sesión `@mp_actor_id`, `@mp_actor_email`, `@mp_ip`, `@mp_ua` que fija `Auditoria::contexto()` en cada petición) y por la capa de aplicación en cada operación del panel.
- **Índices** de cobertura, **FULLTEXT** en noticias y coches, y **vistas materializables** (`v_coches_catalogo`, `v_noticias_publicadas`, `v_panel_resumen`, `v_busqueda_coches`…).
- **Procedimientos almacenados** para las operaciones de negocio: `sp_buscar_coches` (búsqueda + paginación + contador), `sp_publicar_noticia`, `sp_archivar_noticia`, `sp_moderar_comentario`, `sp_incrementar_vista`, `sp_mantenimiento`.
- **Funciones** `fn_slug`, `fn_precio`, `fn_palabras`, `fn_lectura` para normalizar slugs, formatos y tiempos de lectura.
- **Limitación de peticiones** persistente (`rate_limits`) en base de datos, no en ficheros.
- **Disparadores de seguridad**: normalizan `email` a minúsculas, generan slugs y registran cambios de rol/estado.

Esquema generado por el instalador; el usuario de aplicación **no puede** crear ni alterar nada (`SHOW GRANTS` se verifica durante la instalación).

---

## 7. Seguridad

### Acceso al panel: tres factores

Para entrar en `/admin` hay que superar, en este orden, tres barreras independientes:

1. **Contraseña** — Argon2id en `/acceso`, con límite de intentos por IP y por cuenta.
2. **Clave maestra** — frase larga que sólo tú tienes; en el `.env` sólo vive su hash
   (`PANEL_CLAVE_HASH`), así que ni perder el `.env` la revela ni filtrarla deja el panel abierto.
   Se genera y rota con `php scripts/panel_clave.php`.
3. **Código TOTP** — los 6 dígitos de la app del móvil (Google Authenticator, Aegis, 1Password…),
   con tolerancia de ±1 periodo de reloj. El secreto se guarda **cifrado con AES-256-GCM**: quien
   vuelque la base de datos sin tener también la `APP_KEY` no puede generar tus códigos.

Además: la verificación caduca a los 5 minutos y a los 5 intentos, sólo la cuenta de
`ADMIN_EMAIL` pasa de la contraseña al panel, la sesión se cierra sola a los 15 minutos de
inactividad, el registro público está cerrado (`REGISTRO_ABIERTO=false`) y **sólo existe una
cuenta activa en la plataforma**.

**Si pierdes el móvil**, no te quedas fuera: `php scripts/panel_clave.php --desactiva-totp`
borra el secreto desde la consola, vuelves a entrar con contraseña + clave maestra y vuelves a
emparejar el teléfono desde `/admin/seguridad` con el QR nuevo.

### Capas

| Capa | Medida |
|---|---|
| Contraseñas | **Argon2id** (64 MB, coste 4) con *rehash* automático y política de fortaleza |
| Segundo factor | **TOTP RFC 6238** con secreto cifrado (AES-256-GCM) + clave maestra en `.env` |
| Lista blanca | El panel sólo lo abre el correo de `ADMIN_EMAIL`, además de exigir nivel ≥ `PANEL_NIVEL` |
| Registro | Cerrado por defecto; sólo el propietario da de alta cuentas desde el panel |
| Sesión | Cookie `HttpOnly` + `SameSite=Lax`, id regenerado en cada acceso, cierre por inactividad |
| CSRF | Token obligatorio en todo POST/PUT/DELETE, validado en el front controller |
| SQL | Consultas preparadas con PDO nativas (sin emulación) en toda la aplicación |
| XSS | Escape de salida `e()` + **lista blanca de HTML** con `DOMDocument` (sin `script`, `onclick`, `javascript:`) |
| Cabeceras | CSP con **nonce por petición**, `X-Frame-Options: DENY` y `Cache-Control: no-store` en el panel y en la puerta de acceso |
| Fuerza bruta | `RateLimiter` por IP y por cuenta en el login, la verificación y el panel; bloqueo progresivo (`intentos_fallidos`, `bloqueado_hasta`) |
| IDS básico | `Security::apply()` detecta SQLi, XSS, *path traversal* y RCE y lo deja en el log de seguridad |
| Privilegio mínimo | Usuario MySQL propio sin DDL ni `GRANT`; credenciales raíz sólo en los scripts |
| Archivos | `.htaccess` en la raíz y en `public/` que devuelve 404 ante `.env`, código fuente y ficheros ocultos |
| Límites | Tamaños máximos validados en cada campo; *honeypot* en formularios públicos |
| Auditoría | Escritura doble (triggers de MySQL + aplicación) en toda operación sensible |
| Log | `storage/logs/app.log` y `errors.log` con rotación y retención configurable |

> Ningún sistema es invulnerable: lo que se garantiza es que entrar exiga tres factores
> independientes, que cualquier intento fallido quede registrado con su IP y que el panel
> se cierre solo si lo dejas abierto.

---

## 8. Scripts de consola

Todos se ejecutan desde la raíz del proyecto y comparten `scripts/bootstrap.php`
(las opciones se admiten **antes o después** de los argumentos posicionales).

| Script | Uso |
|---|---|
| `php scripts/install.php` | Instalación completa (BD + usuario restringido + siembra) |
| `php scripts/migrate.php --status` | Lista las migraciones y su checksum |
| `php scripts/migrate.php` | Aplica las pendientes (`--dry-run` para no escribir) |
| `php scripts/seed.php [--force]` | Carga o repone los datos de partida |
| `php scripts/descargar-imagenes.php [--only=cars] [--key=…]` | Fotografías desde Wikimedia Commons con atribución |
| `php scripts/descargar-galeria.php [--solo=…] [--por-coche=4] [--limpiar]` | Amplía la galería de cada coche (exterior, interior, motor, detalle) y la registra en la BD |
| `php scripts/descargar-videos.php [--solo=…] [--por-coche=1] [--limpiar]` | Busca vídeo del modelo, valida el título y guarda miniatura local |
| `php scripts/make-admin.php --listar` | Cuentas y roles existentes |
| `php scripts/make-admin.php correo@x.com --rol=root` | Eleva privilegios (queda en auditoría) |
| `php scripts/make-admin.php correo@x.com --pass='…'` | Cambia la contraseña de una cuenta existente (rotación / recuperación) |
| `php scripts/panel_clave.php` | Genera (o rota) la clave maestra del panel y guarda su hash en `.env` |
| `php scripts/panel_clave.php --verifica TEXTO` | Comprueba si una clave coincide con `PANEL_CLAVE_HASH` |
| `php scripts/panel_clave.php --desactiva-totp` | Borra el 2FA de `ADMIN_EMAIL` si pierdes el móvil (vuelta a emparejar) |
| `php scripts/backup.php [--keep=10] [--no-data] [--tabla=a,b]` | Volcado `.sql.gz` con tablas, vistas, triggers y rutinas |
| `php scripts/restaurar.php <fichero> [--db=otro] [--yes]` | Restauración con verificación posterior |
| `php scripts/limpieza.php [--dry-run] [--dias=30]` | Sesiones, caché, logs, ventanas vencidas, imágenes huérfanas |
| `php scripts/desplegar.php [--produccion]` | Comprobación previa al despliegue: PHP, `.env`, BD, esquema, permisos y rutas críticas |
| `php scripts/desplegar.php --url=https://…` | Comprobación del sitio ya publicado (API, cabeceras, barreras del panel, HTTPS) |
| `php scripts/smoke.php [--verbose]` | Recorre las rutas GET y detecta 5xx, avisos de PHP y SQL rota |

> **`--limpiar` respeta el material del panel**: borra sólo lo que el propio
> script descargó. Las fotografías subidas desde el panel
> (`cars/<modelo>/subida-*.jpg`) y los vídeos dados de alta allí (los que no
> aparecen en `videos-manifest.json`) se conservan.

---

## 9. Rutas de la aplicación

**78 rutas** (46 GET, 32 POST) registradas en `config/routes.php`.

| Grupo | Rutas |
|---|---|
| Portada | `/` |
| Revista | `/noticias`, `/noticias/{slug}`, `/categoria/{slug}`, `/etiqueta/{slug}` |
| Catálogo | `/coches`, `/marcas`, `/marcas/{slug}`, `/coches/{slug}` |
| Herramientas | `/comparador`, `/buscar` |
| Institucional | `/sobre-nosotros`, `/privacidad`, `/aviso-legal`, `/cookies`, `/contacto`, `/creditos` |
| Cuenta | `/acceso`, `/registro`, `/cuenta`, `/salir` |
| SEO | `/robots.txt`, `/sitemap.xml` |
| Panel | `/admin`, `/admin/noticias`, `/admin/coches`, `/admin/coches/{id}/galeria`, `/admin/comentarios`, `/admin/mensajes`, `/admin/usuarios`, `/admin/auditoria`, `/admin/configuracion` |
| API | `/api/v1/…` |

---

## 10. API REST v1

Todas las respuestas usan el sobre `{ "ok": true, "data": …, "meta": … }`.
Las rutas de API llevan además su propia cuota de peticiones.

| Endpoint | Descripción |
|---|---|
| `GET /api/v1/salud` | Estado del sistema (BD, migraciones, latencia) |
| `GET /api/v1/coches` | Listado con filtros: `q`, `marca_id`, `categoria`, `combustible`, `precio_min`, `precio_max`, `potencia_cv_min`, `orden`, `pagina`, `limite` |
| `GET /api/v1/coches/{id}` | Ficha completa + galería + vídeos + precios + relacionados |
| `GET /api/v1/comparar?ids=1,2,3` | Comparativa (máx. 4) |
| `GET /api/v1/marcas` | Marcas con número de modelos |
| `GET /api/v1/noticias` | Artículos (`pagina`, `limite`, `categoria`) |
| `GET /api/v1/noticias/{id}` | Artículo completo |
| `GET /api/v1/categorias` | Categorías con conteo |

```bash
curl "http://localhost/ruta/api/v1/coches?categoria=suv&precio_max=30000&limite=5"
```

---

## 11. Pruebas y verificación

```bash
php scripts/smoke.php             # recorre las 46 rutas GET
php scripts/smoke.php --verbose   # detalle de cada incidencia
php scripts/smoke.php --sin-admin # sólo la parte pública
php scripts/desplegar.php         # entorno, BD, esquema (vistas/rutinas/disparadores) y rutas críticas
```

El *smoke* comprueba, para cada ruta: código de estado (2xx/3xx), que el cuerpo no esté vacío, ausencia de `Warning/Notice/Deprecated`, ausencia de `SQLSTATE[` en la salida y, además, **las líneas nuevas escritas en `storage/logs/errors.log` durante la ejecución**. Sale con código `1` si hay incidencias: apto para integración continua.

`desplegar.php` añade lo que el *smoke* no ve: configuración del `.env`, migraciones aplicadas y **sin alterar**, cuentas activas, permisos de escritura y —con credenciales de mantenimiento— que el esquema conserva sus vistas, rutinas y disparadores (el usuario de aplicación no tiene privilegio `TRIGGER`, así que no puede verlos; son ellos quienes rellenan `slug` y `uuid` al crear contenido).

Comprobaciones manuales recomendadas tras un cambio:

```bash
# todas las plantillas y controladores sin errores de sintaxis
for f in $(find app config scripts templates -name "*.php"); do php -l "$f"; done

# el JavaScript también
node --check public/assets/js/app.js
```

---

## 12. Copias de seguridad y restauración

```bash
# Copia completa (tablas + datos + vistas + triggers + procedimientos + funciones)
php scripts/backup.php --keep=10

# Sólo estructura / sólo algunas tablas / listar copias
php scripts/backup.php --no-data
php scripts/backup.php --tabla=noticias,coches
php scripts/backup.php --list

# Restaurar (pide confirmación si es el esquema de producción)
php scripts/restaurar.php backups/revista_coches_20261007_192459.sql.gz --yes

# Restaurar en otro esquema, para probar
php scripts/restaurar.php backups/….sql.gz --db=revista_coches_pruebas --yes
```

El volcado se genera **en PHP puro y comprimido con gzip** (no depende de `mysqldump`), emitiendo los objetos **en orden topológico** para que la restauración funcione desde cero: las funciones antes que las vistas que las usan, las vistas antes que las que las referencian, y los triggers al final.

Tras restaurar, el script verifica una a una que las tablas y las vistas son consultables.

> Las copias se guardan en `backups/`, fuera del repositorio y del alcance web.

---

## 13. Mantenimiento programado

```bash
php scripts/limpieza.php --info          # estado sin tocar nada
php scripts/limpieza.php --dry-run       # simula
php scripts/limpieza.php                 # aplica
```

Elimina sesiones y caché caducadas, rota los logs y archiva los comprimidos con más de 90 días, limpia las ventanas de limitación vencidas, desbloquea cuentas cuya ventana terminó y detecta registros de imagen sin fichero.

Ejemplo de tarea programada (Linux/macOS), a las 04:00:

```cron
0 4 * * *  php /ruta/al/proyecto/scripts/limpieza.php >> /ruta/al/proyecto/storage/logs/limpieza.log 2>&1
0 5 * * 0  php /ruta/al/proyecto/scripts/backup.php --keep=8 >> /ruta/al/proyecto/storage/logs/backup.log 2>&1
```

---

## 14. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `404` en la portada | `mod_rewrite` desactivado | `a2enmod rewrite` y reiniciar Apache |
| `404` en `/`, pero sí funciona `/index.php` | Falta el `.htaccess` de la raíz | Restaurarlo; apunta todo hacia `public/` |
| Página en blanco / `500` | `APP_DEBUG=false` y un error oculto | Mirar `storage/logs/errors.log` |
| `Access denied for user 'revista_app'` | Credenciales de `.env` | Revisar `DB_USER`/`DB_PASS` o relanzar `php scripts/install.php` |
| `Incorrect string value` | Cliente enviando otra codificación | El `Request` ya normaliza a UTF‑8; revisar `DB_CHARSET=utf8mb4` |
| Aviso `CSRF` / 419 | Sesión caducada | Recargar el formulario y volver a enviarlo |
| Login correcto pero sin acceso | Rol por debajo del nivel mínimo | `php scripts/make-admin.php correo@x.com --rol=admin` |
| Las fotos no aparecen | Falta descargarlas | `php scripts/descargar-imagenes.php` |
| Errores 429 en la API | Cuota agotada | Subir `RATE_LIMIT_MAX` o esperar a la ventana |

---

## 15. Despliegue en producción

El sitio corre en **cualquier alojamiento PHP 8.1+ con MySQL 8, Apache, `mod_rewrite` y
`mod_headers`**: sin Composer, sin Node, sin paso de build y sin SSH. Dos recetas:

| | **A · Hosting PHP gratuito** | **B · Oracle Cloud Always Free** |
|---|---|---|
| Coste | 0 € | 0 € (VM siempre encendida) |
| Administración | ninguna (panel gráfico) | hay que montar y mantener el servidor |
| HTTPS | certificado del propio hosting | Let's Encrypt (`certbot`) |
| Ideal para | lanzar cuanto antes | control total y crecimiento |

Comprueba el entorno antes y después de subir nada:

```bash
php scripts/desplegar.php --produccion                       # entorno local listo para producción
php scripts/desplegar.php --url=https://tudominio.com        # el sitio ya subido, medido por HTTP
```

### Ruta A · Hosting PHP gratuito

1. **Esquema y usuario**: créalos desde el panel del hosting (o usa los que te asignen).
2. **Sube el código** por FTP o gestor de ficheros: todo **excepto** `.env`, `storage/cache/*`,
   `storage/logs/*`, `storage/sessions/*` y `backups/`.
3. **Importa los datos**: genera la copia aquí (`php scripts/backup.php`) y súbela; en
   phpMyAdmin, *Importar* sobre el esquema nuevo. El volcado incluye esquema, datos, vistas,
   triggers y rutinas, así que **no hace falta ejecutar scripts en el servidor**.
4. **`.env`** en la raíz (copia `.env.example` y rellena):
   - `APP_URL=https://tudominio.com`, `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_KEY` = 32 bytes aleatorios → `php -r "echo bin2hex(random_bytes(16));"`
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
   - `PANEL_CLAVE_HASH` (cópialo de tu `.env` local: es la misma clave maestra que ya usas)
   - `REGISTRO_ABIERTO=false`, `PANEL_NIVEL=90`, `PANEL_INACTIVIDAD=900`
5. **DocumentRoot** hacia `public/`. Si el hosting obliga a que la raíz sea la del proyecto, ya
   está previsto: el `.htaccess` de la raíz redirige todo hacia `public/`.
6. **Permisos de escritura**: `storage/` y `public/assets/img/subidas/`.
7. **Tareas programadas** (si tu panel ofrece cron): las dos líneas de §13 (limpieza diaria y
   copia semanal).
8. **Verifica**: `php scripts/desplegar.php --url=https://tudominio.com` y entra en `/acceso`.

### Ruta B · Oracle Cloud Always Free

```bash
# 1. VM Ubuntu 24.04 (ARM Ampere A1 o AMD E2.1.Micro), puertos 80 y 443 abiertos en la Security List
sudo apt update && sudo apt install -y apache2 libapache2-mod-php8.2 php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-curl mysql-server certbot python3-certbot-apache
sudo a2enmod rewrite headers

# 2. Base de datos: esquema y usuario de aplicación con privilegios SÓLO de DML (sin CREATE/GRANT)
sudo mysql -e "CREATE DATABASE revista_coches CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
               CREATE USER 'revista_app'@'localhost' IDENTIFIED BY 'Contraseña-Larga-2026';
               GRANT SELECT, INSERT, UPDATE, DELETE ON revista_coches.* TO 'revista_app'@'localhost';"

# 3. Código y configuración
git clone https://github.com/AndreyGarciaGarcia/revista-coches.git /var/www/motorpress
cp /var/www/motorpress/.env.example /var/www/motorpress/.env    # y rellénalo como en la ruta A

# 4. DocumentRoot → /var/www/motorpress/public (VirtualHost) y permisos
sudo chown -R www-data:www-data /var/www/motorpress/storage /var/www/motorpress/public/assets/img/subidas

# 5. Datos, verificación y HTTPS
php /var/www/motorpress/scripts/install.php        # o importa la copia de backup.php
php /var/www/motorpress/scripts/migrate.php
php /var/www/motorpress/scripts/desplegar.php --produccion
php /var/www/motorpress/scripts/smoke.php
sudo certbot --apache -d tudominio.com

# 6. Firewall y tareas programadas (§13)
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw enable
```

### Tras el despliegue

- Entra en `/acceso` con `ADMIN_EMAIL` + contraseña + **clave maestra**, y activa el segundo
  factor desde `/admin/seguridad` escaneando el QR.
- Comprueba desde fuera: `/registro` → `404`, `/admin` sin sesión → `401`, y que las respuestas
  del panel llevan `X-Frame-Options: DENY`.
- `.env` jamás se sube al repositorio; si la copia de seguridad sale fuera de tus servidores,
  cifra el `.sql.gz` antes de moverlo.
- Guarda la clave maestra y la contraseña en tu gestor de contraseñas: son las dos piezas que
  sólo tienes tú.

---

## 16. Créditos

- **Fotografías**: Wikimedia Commons, licencias libres (CC BY‑SA / CC0 / dominio público). Autoría, licencia y enlace de origen en `database/seeds/imagenes-manifest.json`, en `public/assets/img/CREDITOS.md` y en la página `/creditos`.
- **Galería y vídeo de cada ficha**: 4 tomas adicionales por vehículo clasificadas como exterior, interior, motor o detalle (filtro en la propia página) y un vídeo por modelo. Las miniaturas de vídeo son locales: YouTube **no** se contacta hasta que el visitante pulsa reproducir (CSP `frame-src` limitado a `youtube-nocookie.com`).
- **Fotografías subidas desde el panel**: se guardan como «Redacción MotorPress · Todos los derechos reservados» y aparecen en `/creditos` con su propia sección, aparte del material de Commons.
- **Logotipos de marca**: no se usan imágenes de marcas; cada fabricante se representa con un **lettermark** generado en CSS (`letra_marca()`), evitando problemas de propiedad intelectual.
- **Sin dependencias de terceros**: todo el código (framework mínimo, CSS y JS) es original de este proyecto.

---

### Licencia

Proyecto privado. Los contenidos editoriales y las fotografías conservan la licencia de sus autores originales (véase `/creditos`).
