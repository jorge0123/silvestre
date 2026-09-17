<?php

namespace App\Enums;

/**
 * Máquina de estados de una CITA de servicio.
 *
 * Es deliberadamente distinta a OrderState: un servicio no se empaca ni se
 * envía, se cotiza, se agenda y se cumple. NoShow existe porque en servicios
 * el plantón es real y hay que poder registrarlo sin castigar la calificación.
 */
enum BookingState: string
{
    case Requested = 'requested';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitada',
            self::Quoted => 'Cotizada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'En curso',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested, self::Quoted => 'wait',
            self::Confirmed, self::InProgress => 'move',
            self::Completed => 'done',
            self::Cancelled, self::NoShow => 'stop',
        };
    }

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Quoted, self::Confirmed, self::Cancelled],
            self::Quoted => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Cancelled, self::NoShow],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Ocupa un lugar en la agenda del negocio. */
    public function holdsCapacity(): bool
    {
        return in_array($this, [self::Requested, self::Quoted, self::Confirmed, self::InProgress], true);
    }

    public function unlocksReview(): bool
    {
        return $this === self::Completed;
    }
}
