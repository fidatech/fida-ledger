{{-- A page without the sidebar: opening a company. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-primary="blue" data-icons="{{ asset_v('icons.svg') }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="container py-4" style="max-width:980px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="{{ url('/') }}" class="brand-link text-decoration-none d-flex align-items-center gap-2"><x-brand-logo :size="34" /><span class="fw-bold fs-5 text-body">{{ config('app.name') }}</span></a>
        <div class="d-flex align-items-center gap-2">
            @include('partials.language')
            <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-sm btn-light-secondary"><x-icon name="right-from-bracket" class="me-1" /> {{ __('app.nav.sign_out') }}</button></form>
        </div>
    </div>
    @yield('content')
    <footer class="mt-4 small text-muted text-center">© {{ date('Y') }} {{ config('app.name') }} · <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></footer>
</div>
@include('partials.flash')
@include('partials.scripts')
</body>
</html>
