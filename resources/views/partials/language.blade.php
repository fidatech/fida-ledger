{{-- The language switch. The page comes back in the chosen language; Dari and Pashto read right to left. --}}
<div class="dropdown">
    <button class="topbar-icon" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ __('Language') }}" aria-label="{{ __('Language') }}"><x-icon name="globe" /></button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">{{ __('Language') }}</h6></li>
        @foreach (config('app.locales') as $code => $name)
            <li>
                <form method="POST" action="{{ route('locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit" class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" lang="{{ $code }}">{{ $name }}</button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
