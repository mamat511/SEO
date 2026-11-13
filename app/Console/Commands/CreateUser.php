<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'app:create-user {email?} {--name=}';

    protected $description = 'Crée (ou met à jour le mot de passe d\') un utilisateur';

    public function handle(): int
    {
        $email = $this->argument('email') ?? text('E-mail', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'E-mail invalide.');
        $password = password('Mot de passe (8 caractères min.)', required: true, validate: fn ($v) => strlen($v) >= 8 ? null : 'Trop court.');

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $this->option('name') ?? strstr($email, '@', true),
            'password' => $password,
        ]);

        $this->info(($user->wasRecentlyCreated ? 'Utilisateur créé : ' : 'Mot de passe mis à jour : ').$email);

        return self::SUCCESS;
    }
}
