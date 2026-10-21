<?php

declare(strict_types=1);

namespace App\Core\Exception;

/** El token CSRF no coincide o ha caducado (posible ataque). */
final class CsrfException extends \RuntimeException
{
}
