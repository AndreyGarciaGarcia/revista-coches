/**
 * MotorPress · Panel · Emparejamiento TOTP
 *
 * Dibuja el código QR de la clave pendiente y limita el campo del código
 * a 6 dígitos. No hay lógica de seguridad aquí: el servidor valida el
 * código contra el secreto guardado en sesión.
 *
 * La librería qrcode-generator (MIT) se sirve desde el propio dominio,
 * así que la CSP de 'self' no hace ninguna excepción.
 */
(function () {
    'use strict';

    var contenedor = document.querySelector('[data-qr]');

    if (contenedor && typeof qrcode === 'function') {
        try {
            var qr = qrcode(0, 'M');          // 0 = tamaño automático
            qr.addData(contenedor.getAttribute('data-qr') || '');
            qr.make();

            contenedor.innerHTML = qr.createSvgTag({
                cellSize: 4,
                margin: 2,
                scalable: true,
                alt: 'Código QR con la clave del segundo factor'
            });
        } catch (error) {
            // Sin QR sigue siendo posible emparejar con la clave manual
            contenedor.hidden = true;
        }
    }

    // El código sólo puede tener 6 cifras
    Array.prototype.forEach.call(document.querySelectorAll('[data-codigo]'), function (campo) {
        campo.addEventListener('input', function () {
            campo.value = campo.value.replace(/\D/g, '').slice(0, 6);
        });
    });
})();
