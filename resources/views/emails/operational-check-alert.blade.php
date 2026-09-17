<h2>{{ $alert->check->name }}: {{ $alert->to_status }}</h2>
<p>Loja: {{ $alert->check->store->name }}</p><p>{{ $alert->from_status }} → {{ $alert->to_status }}</p>
<p>Abra o BringPainel para consultar detalhes e histórico da verificação.</p>