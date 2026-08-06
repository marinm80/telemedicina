<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PatientSlotCollisionException extends RuntimeException
{
    public function __construct(string $message = 'Ya tienes una cita agendada en ese horario.')
    {
        parent::__construct($message);
    }
}
