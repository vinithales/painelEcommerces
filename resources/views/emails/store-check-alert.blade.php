<x-mail::message>
# Alerta de SEO — {{ $store->name }}

Os checks abaixo identificaram problemas na loja **{{ $store->name }}** ({{ $store->store_url }}):

<x-mail::table>
| Check | Status | Detalhe |
|:------|:-------|:--------|
@foreach ($failingResults as $result)
| {{ $result['check_key'] }} | {{ $result['status'] }} | {{ $result['error'] ?? '—' }} |
@endforeach
</x-mail::table>

Acesse o painel para mais detalhes.

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
