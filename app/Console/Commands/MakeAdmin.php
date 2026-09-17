<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Da acceso al panel de administración. Es la única forma de nombrar al primer
 * administrador; los siguientes se nombran desde el propio panel.
 *
 *   php artisan silvestre:admin correo@ejemplo.com
 *   php artisan silvestre:admin correo@ejemplo.com --revoke
 */
#[Signature('silvestre:admin {email : Correo de la cuenta} {--revoke : Quitar el acceso en lugar de darlo}')]
#[Description('Da o quita acceso al panel de administración de Silvestre')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $grant])->save();

        $this->info($grant
            ? "{$user->name} ahora puede entrar al panel de administración."
            : "{$user->name} ya no tiene acceso al panel de administración.");

        return self::SUCCESS;
    }
}
