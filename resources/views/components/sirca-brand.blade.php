@props(['href', 'appearance' => 'dark'])

@php
    $crestPath = 'images/branding/escudo.png';
    $logoPath = $appearance === 'color'
        ? 'images/branding/logo_color.png'
        : 'images/branding/logo_fondooscuro.png';
    $hasCrest = file_exists(public_path($crestPath));
    $hasLogo = file_exists(public_path($logoPath));
@endphp

<a href="{{ $href }}" {{ $attributes->class(['sirca-brand']) }} aria-label="SIRCA, Sistema Inteligente de Reportes Ciudadanos de Champotón">
    @if ($hasLogo)
        <img class="sirca-brand__image sirca-brand__image--{{ $appearance }}" src="{{ asset($logoPath) }}" alt="{{ $appearance === 'color' ? 'Logotipo del Ayuntamiento de Champotón' : 'Identidad institucional del Ayuntamiento de Champotón para fondo oscuro' }}">
    @elseif ($hasCrest)
        <img class="sirca-brand__crest" src="{{ asset($crestPath) }}" alt="Escudo del Ayuntamiento de Champotón">
    @endif
    @if ($appearance !== 'color' || ! $hasLogo)
        <span class="sirca-brand__name">SIRCA<small>Ayuntamiento de Champotón</small></span>
    @endif
</a>