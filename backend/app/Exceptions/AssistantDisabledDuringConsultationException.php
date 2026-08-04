<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class AssistantDisabledDuringConsultationException extends Exception
{
    public function __construct(string $message = 'El asistente clínico se encuentra deshabilitado durante una consulta en vivo.')
    {
        parent::__construct($message);
    }
}
