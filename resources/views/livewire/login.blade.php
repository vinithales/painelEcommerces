<div class="panel" style="max-width:440px;margin:10vh auto">
<div class="login-brand"><x-brand-logo/></div>
<h2>Acesse o BeaconHub</h2><p>Operação e suporte às suas lojas.</p>
<form wire:submit="login">
<label>E-mail<input type="email" wire:model="email" autocomplete="username" required></label>
<label>Senha<input type="password" wire:model="password" autocomplete="current-password" required></label>
@error('email')<p role="alert">{{ $message }}</p>@enderror
<button class="btn btn-primary" wire:loading.attr="disabled">Entrar</button>
</form></div>
