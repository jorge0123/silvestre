<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Crea la cuenta. `account_type` solo recuerda la intención con la que se
     * registró, para mandarlo al lugar correcto la primera vez: quien elige
     * "Tengo un negocio" va directo al onboarding. Después puede cambiar de
     * modo cuando quiera; no es una decisión para siempre.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'account_type' => ['required', Rule::enum(AccountType::class)],
        ], [
            'account_type.required' => 'Elige si vienes a comprar o a vender.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'account_type' => $input['account_type'],
        ]);
    }
}
