<?php
/**
 * Ficha de artículo.
 *
 * Recibe: $noticia, $comentarios, $relacionadas, $etiquetas
 */

$n = $noticia;
$relacionadas = $relacionadas ?? [];
$etiquetas = $etiquetas ?? [];
$comentarios = $comentarios ?? [];
?>
<article class="article" itemscope itemtype="https://schema.org/NewsArticle">
    <header class="article-head">
        <div class="shell shell-narrow">
            <nav class="breadcrumb" aria-label="Ruta de navegación">
                <a href="<?= url('') ?>">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="<?= url('noticias') ?>">Noticias</a>
                <?php if (!empty($n['categoria_slug'])): ?>
                    <span aria-hidden="true">/</span>
                    <a href="<?= url('categoria/' . $n['categoria_slug']) ?>"><?= e($n['categoria']) ?></a>
                <?php endif; ?>
            </nav>

            <p class="hero-kicker">
                <?= categoria_chip($n['categoria'] ?? null, $n['categoria_color'] ?? null,
                    $n['categoria_slug'] ?? null) ?>
            </p>

            <h1 class="article-title" itemprop="headline"><?= e($n['titulo']) ?></h1>

            <?php if (!empty($n['resumen'])): ?>
                <p class="article-lede" itemprop="description"><?= e($n['resumen']) ?></p>
            <?php endif; ?>

            <div class="article-byline">
                <?php if (!empty($n['autor'])): ?>
                    <span class="byline">
                        <?= letra_marca((string) $n['autor'], 'is-sm') ?>
                        <span>
                            <strong itemprop="name"><?= e($n['autor']) ?></strong>
                            <?php if (!empty($n['autor_bio'])): ?>
                                <em><?= e(excerpt((string) $n['autor_bio'], 90)) ?></em>
                            <?php endif; ?>
                        </span>
                    </span>
                <?php endif; ?>

                <ul class="byline-stats">
                    <li>
                        <time itemprop="datePublished" datetime="<?= e((string) $n['publicado_at']) ?>">
                            <?= e(readable_time($n['publicado_at'], true)) ?>
                        </time>
                    </li>
                    <li><?= (int) $n['tiempo_lectura'] ?> min de lectura</li>
                    <li><?= number_format((int) $n['vistas'], 0, ',', '.') ?> vistas</li>
                    <li><?= count($comentarios) ?> comentarios</li>
                </ul>
            </div>
        </div>
    </header>

    <div class="shell shell-narrow article-media">
        <?= imagen($n['imagen'] ?? null, (string) ($n['imagen_alt'] ?? $n['titulo']),
            ['width' => 1600, 'height' => 900, 'loading' => 'eager', 'class' => 'article-figure']) ?>

        <?php if (!empty($n['pie_foto'])): ?>
            <figcaption class="figure-credit">
                <?= e($n['pie_foto']) ?>
                <?php if (!empty($n['imagen_autor'])): ?>
                    · <strong><?= e($n['imagen_autor']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($n['imagen_licencia'])): ?>
                    · <?= e($n['imagen_licencia']) ?>
                <?php endif; ?>
            </figcaption>
        <?php endif; ?>
    </div>

    <div class="shell shell-narrow article-body prose" itemprop="articleBody">
        <?php
        // Depuración en cada render (defensa en profundidad): lista blanca
        // de etiquetas, sin scripts ni manejadores de evento.
        echo \App\Core\Security::htmlSeguro((string) $n['cuerpo']);
        ?>
    </div>

    <footer class="shell shell-narrow article-foot">
        <?php if ($etiquetas !== []): ?>
            <div class="tag-cloud">
                <?php foreach ($etiquetas as $t): ?>
                    <a class="tag" href="<?= url('etiqueta/' . $t['slug']) ?>"><?= e($t['nombre']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="share">
            <span class="share-label">Enlace del artículo</span>
            <button class="btn btn-ghost btn-sm" type="button"
                    data-copy="<?= e(url('noticias/' . $n['slug'])) ?>">Copiar enlace</button>
        </div>
    </footer>
</article>

<?php if ($relacionadas !== []): ?>
    <section class="section section-alt" aria-labelledby="tit-relacionadas">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">Seguir leyendo</p>
                    <h2 class="section-title" id="tit-relacionadas">También te puede interesar</h2>
                </div>
            </header>
            <div class="grid grid-news">
                <?php foreach ($relacionadas as $rel): ?>
                    <?= \App\Core\View::partial('partials/card-noticia', ['n' => $rel]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section" id="comentarios" aria-labelledby="tit-comentarios">
    <div class="shell shell-narrow">
        <header class="section-head is-plain">
            <div>
                <p class="section-kicker">Comunidad</p>
                <h2 class="section-title" id="tit-comentarios">
                    Comentarios <span class="count-badge"><?= count($comentarios) ?></span>
                </h2>
            </div>
        </header>

        <?php if (empty($n['permitir_comentarios'])): ?>
            <p class="notice notice-info">Los comentarios están cerrados en este artículo.</p>
        <?php else: ?>
            <?php if (($authUser ?? null) === null): ?>
                <div class="notice notice-info">
                    <?php if (registro_abierto()): ?>
                        <p><a href="<?= url('acceso') ?>">Inicia sesión</a> o
                           <a href="<?= url('registro') ?>">crea una cuenta</a> para comentar.</p>
                    <?php else: ?>
                        <p><a href="<?= url('acceso') ?>">Inicia sesión</a> para comentar.
                           El registro de cuentas está cerrado: si quieres comentar, pide tu
                           cuenta desde <a href="<?= url('contacto') ?>">contacto</a>.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form class="form form-card" action="<?= url('noticias/' . $n['slug'] . '/comentarios') ?>"
                      method="post" data-validate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="contenido">Tu comentario</label>
                        <textarea id="contenido" name="contenido" rows="4" required minlength="8" maxlength="2000"
                                  placeholder="Aporta algo: una duda, una experiencia, un dato…"><?= e(old('contenido')) ?></textarea>
                        <p class="field-help">Entre 8 y 2.000 caracteres. Se publica tras moderación.</p>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Enviar comentario</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($comentarios === []): ?>
                <p class="empty-note">Todavía no hay comentarios. ¡Sé el primero!</p>
            <?php else: ?>
                <ol class="comments">
                    <?php foreach ($comentarios as $c): ?>
                        <li class="comment">
                            <?= letra_marca((string) $c['autor'], 'is-sm') ?>
                            <div class="comment-body">
                                <p class="comment-head">
                                    <strong><?= e($c['autor']) ?></strong>
                                    <?php if (!empty($c['usuario_id'])): ?>
                                        <span class="badge badge-soft">miembro</span>
                                    <?php endif; ?>
                                    <time datetime="<?= e((string) $c['creado_en']) ?>">
                                        <?= e(time_ago($c['creado_en'])) ?>
                                    </time>
                                </p>
                                <p class="comment-text"><?= nl2br(e($c['contenido'])) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script type="application/ld+json" nonce="<?= e(\App\Core\Security::nonce()) ?>">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $n['titulo'],
    'description' => $n['resumen'] ?? '',
    'datePublished' => $n['publicado_at'] ?? '',
    'dateModified' => $n['actualizado_en'] ?? '',
    'wordCount' => str_word_count(strip_tags((string) $n['cuerpo'])),
    'author' => ['@type' => 'Person', 'name' => $n['autor'] ?? 'MotorPress'],
    'publisher' => ['@type' => 'Organization', 'name' => 'MotorPress'],
    'mainEntityOfPage' => url('noticias/' . $n['slug']),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>
</script>
