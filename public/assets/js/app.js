/* ==========================================================================
   MotorPress — comportamiento de interfaz
   --------------------------------------------------------------------------
   Vanilla JS, sin dependencias. Todo se activa desde atributos data-* para
   que el HTML siga siendo funcional si el script no llega a cargarse.
   ========================================================================== */

(function () {
    'use strict';

    var $  = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ---------------------------------------------------------------------
       1. Navegación móvil y cabecera fija
       --------------------------------------------------------------------- */

    function initNav() {
        var toggle = $('[data-nav-toggle]');
        var nav = $('[data-nav]');

        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                var abierto = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                document.body.classList.toggle('is-locked', abierto);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                    nav.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('is-locked');
                    toggle.focus();
                }
            });
        }

        var header = $('[data-header]');
        if (header) {
            var marcar = function () {
                header.classList.toggle('is-stuck', window.scrollY > 8);
            };
            marcar();
            window.addEventListener('scroll', marcar, { passive: true });
        }
    }

    /* ---------------------------------------------------------------------
       2. Panel lateral (sólo pantallas pequeñas)
       --------------------------------------------------------------------- */

    function initAdminSide() {
        var toggle = $('[data-admin-toggle]');
        var side = $('[data-admin-side]');

        if (!toggle || !side) { return; }

        toggle.addEventListener('click', function () {
            var abierto = side.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            document.body.classList.toggle('is-locked', abierto);
        });

        document.addEventListener('click', function (e) {
            if (!side.classList.contains('is-open')) { return; }
            if (side.contains(e.target) || toggle.contains(e.target)) { return; }
            side.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('is-locked');
        });
    }

    /* ---------------------------------------------------------------------
       3. Avisos flash
       --------------------------------------------------------------------- */

    function initFlash() {
        $$('[data-dismiss]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var flash = btn.closest('.flash');
                if (!flash) { return; }
                flash.style.transition = 'opacity .2s, transform .2s';
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-6px)';
                setTimeout(function () { flash.remove(); }, 200);
            });
        });
    }

    /* ---------------------------------------------------------------------
       4. Confirmaciones antes de enviar
       --------------------------------------------------------------------- */

    function initConfirm() {
        $$('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') { return; }
                e.preventDefault();

                if (window.confirm(form.getAttribute('data-confirm'))) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       5. Copiar enlace
       --------------------------------------------------------------------- */

    function initCopy() {
        $$('[data-copy]').forEach(function (btn) {
            var texto = btn.getAttribute('data-copy');
            var original = btn.textContent;

            btn.addEventListener('click', function () {
                var listo = function () {
                    btn.textContent = 'Enlace copiado ✓';
                    btn.disabled = true;
                    setTimeout(function () {
                        btn.textContent = original;
                        btn.disabled = false;
                    }, 2200);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(texto).then(listo, fallback);
                } else {
                    fallback();
                }

                function fallback() {
                    var ta = document.createElement('textarea');
                    ta.value = texto;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); listo(); } catch (err) { /* nada */ }
                    document.body.removeChild(ta);
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       6. Filtros del catálogo
       --------------------------------------------------------------------- */

    /**
     * El backend espera listas como parámetros separados por comas
     * (?marcas=1,7,12) mientras que el formulario envía marcas[].
     * Antes de enviar, agrupamos y reescribimos los nombres.
     */
    function prepararFiltros(form) {
        var grupos = {};

        $$('input[type="checkbox"][data-filter-key]', form).forEach(function (cb) {
            var key = cb.getAttribute('data-filter-key');
            if (cb.checked) {
                grupos[key] = grupos[key] || [];
                grupos[key].push(cb.value);
            }
            cb.disabled = true;
        });

        Object.keys(grupos).forEach(function (key) {
            var oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = key;
            oculto.value = grupos[key].join(',');
            oculto.dataset.filtroGenerado = '1';
            form.appendChild(oculto);
        });
    }

    function restaurarFiltros(form) {
        $$('input[data-filtro-generado]', form).forEach(function (i) { i.remove(); });
        $$('input[type="checkbox"][data-filter-key]', form).forEach(function (cb) {
            cb.disabled = false;
        });
    }

    function initFilters() {
        var panel = $('[data-filters]');
        var form = panel ? $('form', panel) : null;

        if (form) {
            form.addEventListener('submit', function () { prepararFiltros(form); });

            window.addEventListener('pageshow', function () { restaurarFiltros(form); });
        }

        // Búsqueda dentro de la lista de marcas
        var buscador = $('[data-brand-filter]');
        if (buscador) {
            buscador.addEventListener('input', function () {
                var q = buscador.value.trim().toLowerCase();
                $$('[data-brand-name]').forEach(function (li) {
                    li.hidden = q !== '' && li.getAttribute('data-brand-name').indexOf(q) === -1;
                });
            });
        }

        // Botón «Filtros» en móvil
        var boton = $('[data-filters-toggle]');
        if (boton && panel) {
            boton.addEventListener('click', function () {
                var abierto = panel.classList.toggle('is-open');
                boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                if (abierto) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        }
    }

    /* ---------------------------------------------------------------------
       7. Envío automático
       --------------------------------------------------------------------- */

    function initAutoSubmit() {
        $$('[data-auto-submit]').forEach(function (campo) {
            campo.addEventListener('change', function () {
                var form = campo.closest('form');
                if (form) { form.submit(); }
            });
        });
    }

    /* ---------------------------------------------------------------------
       8. Validación de formularios
       --------------------------------------------------------------------- */

    function mensajeError(campo) {
        if (campo.validity.valueMissing) {
            return 'Este campo es obligatorio.';
        }
        if (campo.validity.typeMismatch && campo.type === 'email') {
            return 'Introduce un correo electrónico válido.';
        }
        if (campo.validity.typeMismatch && campo.type === 'url') {
            return 'Introduce una dirección válida (https://…).';
        }
        if (campo.validity.tooShort) {
            return 'Como mínimo ' + campo.minLength + ' caracteres (ahora ' + campo.value.length + ').';
        }
        if (campo.validity.rangeUnderflow || campo.validity.rangeOverflow) {
            return 'El valor debe estar entre ' + campo.min + ' y ' + campo.max + '.';
        }
        if (campo.validity.patternMismatch) {
            return 'El formato introducido no es válido.';
        }
        return 'Revisa este campo.';
    }

    function bloqueError(campo) {
        var campo2 = campo.closest('.field');
        var bloque = campo2 ? $('.field-error', campo2) : null;

        if (!bloque && campo.closest('label')) {
            bloque = campo.closest('label').parentNode.querySelector('.field-error');
        }

        return bloque;
    }

    function marcarError(campo, mensaje) {
        campo.setAttribute('aria-invalid', 'true');
        var bloque = bloqueError(campo);
        if (bloque) {
            bloque.textContent = mensaje;
            bloque.hidden = false;
        }
    }

    function limpiarError(campo) {
        campo.removeAttribute('aria-invalid');
        var bloque = bloqueError(campo);
        if (bloque) {
            bloque.hidden = true;
            bloque.textContent = '';
        }
    }

    function initValidation() {
        $$('form[data-validate]').forEach(function (form) {
            var campos = $$('input, select, textarea', form).filter(function (c) {
                return c.type !== 'hidden' && c.type !== 'submit' && c.type !== 'button';
            });

            campos.forEach(function (campo) {
                campo.addEventListener('blur', function () {
                    if (campo.value === '' && !campo.required) { return; }
                    if (campo.checkValidity()) { limpiarError(campo); }
                });

                campo.addEventListener('input', function () {
                    if (campo.getAttribute('aria-invalid') === 'true' && campo.checkValidity()) {
                        limpiarError(campo);
                    }
                });
            });

            form.addEventListener('submit', function (e) {
                var primero = null;

                campos.forEach(function (campo) {
                    if (campo.checkValidity()) {
                        limpiarError(campo);
                        return;
                    }

                    var mensaje = campo.getAttribute('data-error');
                    if (!mensaje) { mensaje = mensajeError(campo); }
                    marcarError(campo, mensaje);
                    if (!primero) { primero = campo; }
                });

                // Casilla de aceptación: el error se muestra fuera del label
                var acepta = $('input[name="acepta"]', form);
                if (acepta && !acepta.checked) {
                    var bloque = acepta.closest('label');
                    var error = bloque && bloque.nextElementSibling;
                    if (error && error.classList.contains('field-error')) {
                        error.textContent = acepta.getAttribute('data-error') || 'Debes aceptar los términos.';
                        error.hidden = false;
                    }
                    if (!primero) { primero = acepta; }
                }

                if (primero) {
                    e.preventDefault();
                    e.stopPropagation();
                    primero.focus({ preventScroll: false });
                    primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       9. Contadores de caracteres
       --------------------------------------------------------------------- */

    function initCounters() {
        $$('[data-counter]').forEach(function (campo) {
            var destino = campo.getAttribute('data-count-target');
            var marcador = destino ? $('[data-count="' + destino + '"]') : null;
            if (!marcador) { return; }

            var actualizar = function () {
                marcador.textContent = String(campo.value.length);
            };

            campo.addEventListener('input', actualizar);
            actualizar();
        });
    }

    /* ---------------------------------------------------------------------
       10. Barra de herramientas del editor
       --------------------------------------------------------------------- */

    function initEditor() {
        $$('[data-editor-toolbar]').forEach(function (barra) {
            var area = barra.nextElementSibling;
            if (!area || area.tagName !== 'TEXTAREA') { return; }

            barra.addEventListener('click', function (e) {
                var btn = e.target.closest('button');
                if (!btn) { return; }

                var inicio = area.selectionStart;
                var fin = area.selectionEnd;
                var seleccion = area.value.slice(inicio, fin);
                var pieza;

                if (btn.hasAttribute('data-wrap')) {
                    var partes = btn.getAttribute('data-wrap').split('|');
                    pieza = partes[0] + (seleccion || 'texto') + partes[1];
                } else {
                    pieza = btn.getAttribute('data-ins');
                }

                area.setRangeText(pieza, inicio, fin, 'end');
                area.focus();
                area.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }

    /* ---------------------------------------------------------------------
       11. Vista previa de imagen
       --------------------------------------------------------------------- */

    function initPreview() {
        $$('[data-preview-select]').forEach(function (select) {
            var objetivo = document.getElementById(select.getAttribute('data-preview-target'));
            if (!objetivo) { return; }

            var img = $('img', objetivo);
            var pie = $('.img-preview-credit', objetivo);

            var actualizar = function () {
                var opcion = select.options[select.selectedIndex];
                var src = opcion ? opcion.getAttribute('data-src') : '';

                if (!src) {
                    objetivo.hidden = true;
                    return;
                }

                objetivo.hidden = false;
                if (img) { img.src = src; }
                if (pie) { pie.textContent = opcion.textContent.trim(); }
            };

            select.addEventListener('change', actualizar);
            actualizar();
        });
    }

    /* ---------------------------------------------------------------------
       12. Medidor de contraseña
       --------------------------------------------------------------------- */

    var COMUNES = ['password', '12345678', '123456789', 'qwertyuiop', 'admin123',
                   'motorpress', 'contrasena', 'iloveyou', '11111111', 'abc12345',
                   'qwerty123', '000000', '1q2w3e4r', 'letmein'];

    function evaluarClave(valor) {
        var v = (valor || '').toLowerCase();

        return {
            length:  valor.length >= 10,
            case:    /[A-ZÁÉÍÓÚÜÑ]/.test(valor) && /[a-záéíóúüñ]/.test(valor),
            digit:   /\d/.test(valor),
            symbol:  /[^\p{L}\s\d]/u.test(valor),
            common:  valor.length > 0 && COMUNES.indexOf(v) === -1 && !/^(.)\1+$/.test(v)
        };
    }

    function initPassword() {
        var campos = $$('[data-mirror="password-meter"]');

        campos.forEach(function (campo) {
            var contenedor = campo.parentNode;
            var meter = $('[data-meter]', contenedor);
            if (!meter) { return; }

            var barra = $('.password-meter-bar', meter);
            var texto = $('.password-meter-text', meter);
            var reglas = $$('.password-rules li', contenedor);

            meter.hidden = false;

            var actualizar = function () {
                var res = evaluarClave(campo.value);
                var puntos = 0;
                var total = 0;

                Object.keys(res).forEach(function (k) {
                    total += 1;
                    if (res[k]) { puntos += 1; }
                });

                var pct = campo.value.length === 0 ? 0 : Math.round((puntos / total) * 100);

                if (barra) { barra.style.width = pct + '%'; }

                var etiqueta = pct >= 80 ? 'Muy segura'
                    : pct >= 60 ? 'Segura'
                    : pct >= 40 ? 'Aceptable'
                    : pct > 0 ? 'Débil' : '—';

                if (texto) { texto.textContent = etiqueta + ' · ' + pct + '%'; }

                reglas.forEach(function (li) {
                    var clave = li.getAttribute('data-rule');
                    li.classList.toggle('is-ok', !!res[clave]);
                });
            };

            campo.addEventListener('input', actualizar);
            actualizar();
        });
    }

    /* ---------------------------------------------------------------------
       13. Galería con visor ampliado
       --------------------------------------------------------------------- */

    function initLightbox() {
        var raiz = $('[data-lightbox-root]');
        if (!raiz) { return; }

        var principal = $('img', raiz);
        var abridor = $('[data-lightbox-open]', raiz);
        var miniaturas = $$('.gallery-thumb[data-lightbox-src]');

        var abrir = function (src, alt) {
            var caja = document.createElement('div');
            caja.className = 'lightbox';
            caja.setAttribute('role', 'dialog');
            caja.setAttribute('aria-modal', 'true');
            caja.setAttribute('aria-label', 'Imagen ampliada');

            caja.innerHTML =
                '<button class="lightbox-close" type="button" aria-label="Cerrar">×</button>' +
                '<figure style="margin:0;text-align:center">' +
                '<img alt="">' +
                '<figcaption></figcaption>' +
                '</figure>';

            var img = $('img', caja);
            img.src = src;
            img.alt = alt || '';
            $('figcaption', caja).textContent = alt || '';

            document.body.appendChild(caja);
            document.body.classList.add('is-locked');
            $('.lightbox-close', caja).focus();

            var cerrar = function () {
                caja.remove();
                document.body.classList.remove('is-locked');
                document.removeEventListener('keydown', alPulsar);
            };

            var alPulsar = function (e) {
                if (e.key === 'Escape') { cerrar(); }
            };

            caja.addEventListener('click', function (e) {
                if (e.target === caja || e.target.closest('.lightbox-close')) { cerrar(); }
            });

            document.addEventListener('keydown', alPulsar);
        };

        if (abridor && principal) {
            abridor.addEventListener('click', function () {
                abrir(principal.currentSrc || principal.src, principal.alt);
            });
        }

        miniaturas.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-lightbox-src');
                var alt = thumb.getAttribute('data-lightbox-alt') || '';

                miniaturas.forEach(function (t) { t.classList.remove('is-active'); });
                thumb.classList.add('is-active');

                if (principal) {
                    principal.src = src;
                    principal.alt = alt;
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       14. Comparador persistente entre páginas
       --------------------------------------------------------------------- */

    var CLAVE = 'mp_comparar';
    var MAXIMO = 4;

    function leerComparador() {
        try {
            var raw = window.localStorage.getItem(CLAVE);
            var lista = raw ? JSON.parse(raw) : [];
            return Array.isArray(lista) ? lista.map(Number).filter(function (n) { return n > 0; }) : [];
        } catch (e) {
            return [];
        }
    }

    function guardarComparador(ids) {
        try {
            window.localStorage.setItem(CLAVE, JSON.stringify(ids.slice(0, MAXIMO)));
        } catch (e) { /* almacenamiento no disponible */ }
    }

    function initCompare() {
        var botones = $$('[data-compare-add]');
        var ids = leerComparador();

        // Bandeja flotante
        var bandeja = document.createElement('div');
        bandeja.className = 'compare-tray';
        bandeja.hidden = true;
        bandeja.innerHTML =
            '<span class="compare-tray-text"></span>' +
            '<a class="btn btn-primary btn-sm" href="#">Comparar</a>' +
            '<button class="compare-tray-clear" type="button" aria-label="Vaciar la comparación">' +
            '<span aria-hidden="true">×</span></button>';
        document.body.appendChild(bandeja);

        function pintar() {
            ids = leerComparador();
            var n = ids.length;

            bandeja.hidden = n === 0;
            if (n === 0) { return; }

            $('.compare-tray-text', bandeja).textContent =
                n + (n === 1 ? ' coche seleccionado' : ' coches seleccionados');
            $('a', bandeja).href = (document.body.getAttribute('data-base') || '/') +
                'comparador?ids=' + ids.join(',');

            botones.forEach(function (b) {
                var id = Number(b.getAttribute('data-compare-add'));
                var dentro = ids.indexOf(id) !== -1;
                b.textContent = dentro ? 'Añadido ✓' : 'Comparar este coche';
                b.classList.toggle('is-added', dentro);
            });
        }

        botones.forEach(function (b) {
            b.addEventListener('click', function () {
                var id = Number(b.getAttribute('data-compare-add'));
                var pos = ids.indexOf(id);

                if (pos !== -1) {
                    ids.splice(pos, 1);
                } else {
                    if (ids.length >= MAXIMO) {
                        ids.shift();
                    }
                    ids.push(id);
                }

                guardarComparador(ids);
                pintar();
            });
        });

        $('button', bandeja).addEventListener('click', function () {
            ids = [];
            guardarComparador(ids);
            pintar();
        });

        pintar();

        // El formulario «añadir otro vehículo» ya lo resuelve el servidor;
        // aquí sólo limpiamos la selección tras enviarla.
        var enviar = $('[data-compare-submit]');
        if (enviar) {
            enviar.closest('form').addEventListener('submit', function () {
                var select = $('#add-car');
                if (select && select.value) {
                    ids.push(Number(select.value));
                    guardarComparador(ids);
                }
            });
        }
    }

    /* ---------------------------------------------------------------------
       15. Boletín: aviso inmediato al usuario
       --------------------------------------------------------------------- */

    function initNewsletter() {
        $$('[data-newsletter]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var campo = $('input[type="email"]', form);

                if (campo && !campo.checkValidity()) {
                    e.preventDefault();
                    campo.focus();
                    campo.setAttribute('aria-invalid', 'true');
                    return;
                }

                var boton = $('button[type="submit"]', form);
                if (boton) {
                    boton.disabled = true;
                    boton.textContent = 'Enviando…';
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       16. Sugerencias rápidas del buscador
       --------------------------------------------------------------------- */

    function initSearch() {
        var campo = $('[data-search-suggest]');
        if (!campo) { return; }

        var caja = document.createElement('ul');
        caja.className = 'suggest-list';
        caja.setAttribute('role', 'listbox');
        caja.hidden = true;
        campo.parentNode.appendChild(caja);

        var TERMINOS = ['SUV familiar', 'eléctrico', 'híbrido enchufable', 'berlina',
                        'deportivo', 'pickup', 'utilitario', 'coupé'];

        var pintar = function () {
            var q = campo.value.trim().toLowerCase();
            caja.innerHTML = '';
            caja.hidden = q.length < 2;

            if (!caja.hidden) {
                TERMINOS.filter(function (t) { return t.indexOf(q) !== -1; })
                    .slice(0, 6)
                    .forEach(function (t) {
                        var li = document.createElement('li');
                        var a = document.createElement('a');
                        a.href = campo.form.action + '?q=' + encodeURIComponent(t);
                        a.textContent = t;
                        li.appendChild(a);
                        caja.appendChild(li);
                    });

                caja.hidden = caja.children.length === 0;
            }
        };

        campo.addEventListener('input', pintar);
        campo.addEventListener('blur', function () {
            setTimeout(function () { caja.hidden = true; }, 180);
        });
        campo.addEventListener('focus', pintar);
    }

    /* ---------------------------------------------------------------------
       15. Aparición progresiva al desplazar
       --------------------------------------------------------------------- */

    function initReveal() {
        if (!('IntersectionObserver' in window)) { return; }
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

        var SEL = [
            '.grid > .card',
            '.panel-tiles > .tile',
            '.action-row > .action-card',
            '.section-head',
            '.stats',
            '.novedades',
            '.table-scroll',
            '.panel-box'
        ].join(', ');

        var objetivos = $$(SEL);
        if (!objetivos.length) { return; }

        objetivos.forEach(function (el, i) {
            el.classList.add('reveal');
            el.style.transitionDelay = (Math.min(i % 6, 5) * 55) + 'ms';
        });

        var mostrar = function (el) {
            el.classList.add('is-visible');
            el.style.transitionDelay = '';
        };

        var observadorActivo = false;

        var io = new IntersectionObserver(function (entradas) {
            observadorActivo = true;
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting) { return; }
                mostrar(entrada.target);
                io.unobserve(entrada.target);
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0 });

        objetivos.forEach(function (el) { io.observe(el); });

        /* Red de seguridad: si el observador no llega a actuar, el contenido
           nunca debe quedar oculto. Se pospone mientras la pestaña esté en
           segundo plano, porque ahí el observador aún no ha despertado. */
        var redDeSeguridad = function (intento) {
            setTimeout(function () {
                if (observadorActivo) { return; }
                if (document.hidden && intento < 40) { redDeSeguridad(intento + 1); return; }
                objetivos.forEach(function (el) {
                    if (!el.classList.contains('is-visible')) { mostrar(el); io.unobserve(el); }
                });
            }, 2500);
        };
        redDeSeguridad(0);
    }

    /* ---------------------------------------------------------------------
       16. Barra de progreso y volver arriba
       --------------------------------------------------------------------- */

    function initScrollUI() {
        var barra = document.createElement('div');
        barra.className = 'scroll-progress';
        barra.setAttribute('aria-hidden', 'true');
        document.body.appendChild(barra);

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'to-top';
        boton.setAttribute('aria-label', 'Volver arriba');
        boton.innerHTML = '<span aria-hidden="true">↑</span>';
        document.body.appendChild(boton);

        var refrescar = function () {
            var alto = document.documentElement.scrollHeight - window.innerHeight;
            var p = alto > 0 ? (window.scrollY / alto) * 100 : 0;
            barra.style.width = Math.min(100, Math.max(0, p)) + '%';
            boton.classList.toggle('is-visible', window.scrollY > 640);
        };

        refrescar();
        window.addEventListener('scroll', refrescar, { passive: true });
        window.addEventListener('resize', refrescar);

        boton.addEventListener('click', function () {
            var suave = !window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: suave ? 'smooth' : 'auto' });
        });
    }

    /* ---------------------------------------------------------------------
       17. Galería de la ficha: filtro por tipo de toma
       --------------------------------------------------------------------- */

    function initGalleryFilters() {
        var barra = $('[data-gallery-filters]');
        var lista = $('[data-gallery-thumbs]');

        if (!barra || !lista) { return; }

        var botones = $$('.gfilter', barra);
        var items = $$('li[data-cat]', lista);

        if (!botones.length || !items.length) { return; }

        // Al filtrar, la imagen grande pasa a la primera toma visible
        var enfocarPrimera = function () {
            for (var i = 0; i < items.length; i++) {
                if (items[i].hidden) { continue; }
                var thumb = $('.gallery-thumb', items[i]);
                if (thumb) { thumb.click(); }
                return;
            }
        };

        botones.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filtro = btn.getAttribute('data-gfilter') || 'all';

                botones.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');

                items.forEach(function (item) {
                    item.hidden = filtro !== 'all' && item.getAttribute('data-cat') !== filtro;
                    item.classList.toggle('is-hidden', item.hidden);
                });

                enfocarPrimera();
            });
        });

        botones.forEach(function (b) {
            b.setAttribute('aria-pressed', b.classList.contains('is-active') ? 'true' : 'false');
        });
    }

    /* ---------------------------------------------------------------------
       18. Vídeos con reproductor en diferido
       --------------------------------------------------------------------- */

    function initVideos() {
        var fachadas = $$('[data-video]');

        if (!fachadas.length) { return; }

        fachadas.forEach(function (fachada) {
            fachada.addEventListener('click', function () {
                var codigo = fachada.getAttribute('data-video');
                var esYoutube = fachada.getAttribute('data-video-youtube') === '1';
                var titulo = fachada.getAttribute('data-video-titulo') || 'Vídeo';

                if (!codigo) { return; }

                var caja = document.createElement('div');
                caja.className = 'video-frame';

                if (esYoutube) {
                    var iframe = document.createElement('iframe');
                    iframe.src = 'https://www.youtube-nocookie.com/embed/' +
                        encodeURIComponent(codigo) + '?autoplay=1&rel=0';
                    iframe.title = titulo;
                    iframe.setAttribute('allow',
                        'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                    iframe.setAttribute('allowfullscreen', 'true');
                    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                    caja.appendChild(iframe);
                } else {
                    var reproductor = document.createElement('video');
                    reproductor.className = 'video-el';
                    reproductor.src = codigo;
                    reproductor.controls = true;
                    reproductor.autoplay = true;
                    reproductor.setAttribute('playsinline', 'true');
                    reproductor.setAttribute('preload', 'metadata');
                    caja.appendChild(reproductor);
                }

                if (fachada.parentNode) {
                    fachada.parentNode.replaceChild(caja, fachada);
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       19. Regiones con desplazamiento horizontal
       --------------------------------------------------------------------- */

    function initScrollRegions() {
        // Una tabla que se desborda sólo se puede recorrer con el teclado si
        // puede recibir el foco (WCAG 2.1.1 «Accesible con teclado»).
        $$('.table-scroll').forEach(function (caja) {
            if (!caja.hasAttribute('tabindex')) {
                caja.setAttribute('tabindex', '0');
            }
        });
    }

    /* ---------------------------------------------------------------------
       Arranque
       --------------------------------------------------------------------- */

    function iniciar() {
        initNav();
        initAdminSide();
        initFlash();
        initConfirm();
        initCopy();
        initFilters();
        initAutoSubmit();
        initValidation();
        initCounters();
        initEditor();
        initPreview();
        initPassword();
        initLightbox();
        initCompare();
        initNewsletter();
        initSearch();
        initReveal();
        initScrollUI();
        initGalleryFilters();
        initVideos();
        initScrollRegions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
