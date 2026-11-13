<form wire:submit="login" class="card w-full max-w-sm space-y-4">
    <div>
        <h1 class="text-xl font-semibold">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-500">Connecte-toi pour accéder à tes projets.</p>
    </div>
    <div>
        <label class="label" for="email">E-mail</label>
        <input id="email" type="email" wire:model="email" class="input" autocomplete="username" autofocus>
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password">Mot de passe</label>
        <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password">
        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="remember"> Rester connecté</label>
    <button class="btn w-full justify-center" wire:loading.attr="disabled">Se connecter</button>
</form>
