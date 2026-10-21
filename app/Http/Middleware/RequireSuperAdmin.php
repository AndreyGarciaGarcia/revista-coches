<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * Operaciones reservadas al propietario del sitio: gestión de usuarios,
 * auditoría y configuración.
 *
 * Hereda todas las barreras de RequireAdmin (lista blanca ADMIN_EMAIL,
 * nivel 90, expiración por inactividad, límite de peticiones y
 * cabeceras endurecidas). Aquí sólo se deja constancia de la intención:
 * es el mismo filtro con el mismo nivel mínimo, porque el nivel 90 es
 * el más alto que existe en el panel (100 queda para la raíz de la
 * base de datos, que sólo se alcanza por consola).
 */
final class RequireSuperAdmin extends RequireAdmin
{
}
