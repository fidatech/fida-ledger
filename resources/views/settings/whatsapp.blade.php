@extends('layouts.app', ['title' => __('WhatsApp')])

@section('content')
@php
    $hasKey = filled($setting->session_key);
    $sessions = session('wa_sessions');
@endphp
<x-page-header :title="__('WhatsApp')" icon="comment-dots" :subtitle="__('Send invoices, receipts and account balances to customers through RelayWA.')" :crumbs="[[__('Settings')], [__('WhatsApp')]]">
    @if ($setting->isReady())
        <a class="btn btn-primary" href="{{ route('whatsapp.compose') }}"><x-icon name="paper-plane" class="me-1" /> {{ __('New message') }}</a>
    @endif
</x-page-header>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ route('whatsapp.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">{{ __('Connection') }}</h5>
                @if ($setting->isReady())
                    <span class="badge bg-success-subtle text-success-emphasis">{{ __('Switched on') }}</span>
                @elseif ($hasKey)
                    <span class="badge bg-warning-subtle text-warning-emphasis">{{ __('Switched off') }}</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Not connected') }}</span>
                @endif
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('RelayWA links your WhatsApp number by QR code, like WhatsApp Web. Create a session at relaywa.com, link your number there, and paste the session key here.') }}</p>
                <div class="mb-3">
                    <label class="form-label" for="session_key">{{ __('Session key') }}</label>
                    <input type="password" class="form-control" id="session_key" name="session_key" autocomplete="off" dir="ltr" placeholder="{{ $hasKey ? __('A key is saved. Leave empty to keep it.') : '' }}">
                    <div class="form-text">{{ __('The key is stored encrypted and is never shown again.') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="country_code">{{ __('Country code') }}</label>
                        <input type="text" inputmode="numeric" class="form-control" id="country_code" name="country_code" value="{{ old('country_code', $setting->country_code) }}" dir="ltr" placeholder="93" autocomplete="off">
                        <div class="form-text">{{ __('Used for numbers saved as 07…') }}</div>
                    </div>
                    <div class="col-12 col-sm-6 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1" @checked(old('is_enabled', $setting->is_enabled))>
                            <label class="form-check-label" for="is_enabled">{{ __('Sending is switched on') }}</label>
                        </div>
                    </div>
                </div>

                <hr>
                <h6>{{ __('Message texts') }}</h6>
                <p class="small text-muted">{{ __('Words in {braces} are filled in for each message. You always read the message before it is sent.') }}</p>
                @foreach (config('whatsapp.templates') as $key => $template)
                    <div class="mb-3">
                        <label class="form-label" for="template-{{ $key }}">{{ __($template['label']) }}</label>
                        <textarea class="form-control" id="template-{{ $key }}" name="templates[{{ $key }}]" rows="5" maxlength="2000" dir="auto">{{ old('templates.'.$key, $setting->template($key)) }}</textarea>
                        <div class="form-text" dir="ltr">@foreach ($template['fields'] as $field)<code>&#123;{{ $field }}&#125;</code> @endforeach</div>
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Check and test') }}</h5></div>
            <div class="card-body">
                @if (! $hasKey)
                    <p class="text-muted mb-0">{{ __('Enter and save the session key first.') }}</p>
                @else
                    <form method="POST" action="{{ route('whatsapp.check') }}" class="mb-3">@csrf
                        <button type="submit" class="btn btn-outline-soft"><x-icon name="plug" class="me-1" /> {{ __('Check connection') }}</button>
                    </form>

                    @if (is_array($sessions))
                        @if (! $sessions)
                            <p class="small text-muted">{{ __('RelayWA did not list any session for this key.') }}</p>
                        @else
                            <table class="table table-sm align-middle w-100">
                                <thead><tr><th>{{ __('Session') }}</th><th>{{ __('State') }}</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($sessions as $row)
                                        <tr>
                                            <td><span class="fw-semibold">{{ $row['name'] ?: $row['id'] }}</span>@if ($row['phone'])<span class="d-block small text-muted" dir="ltr">{{ $row['phone'] }}</span>@endif</td>
                                            <td dir="ltr">{{ $row['status'] ?: '—' }}</td>
                                            <td class="text-end text-nowrap">
                                                @if ($row['id'])
                                                    <form method="POST" action="{{ route('whatsapp.session') }}" class="d-inline">@csrf
                                                        <input type="hidden" name="session_id" value="{{ $row['id'] }}">
                                                        <button type="submit" name="action" value="qr" class="btn btn-sm btn-light-secondary">{{ __('QR code') }}</button>
                                                        <button type="submit" name="action" value="connect" class="btn btn-sm btn-light-secondary">{{ __('Connect') }}</button>
                                                        <button type="submit" name="action" value="disconnect" class="btn btn-sm btn-light-secondary">{{ __('Disconnect') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="small text-muted">{{ __('The state is shown exactly as RelayWA reports it.') }}</p>
                        @endif
                    @endif

                    @if (session('wa_qr'))
                        <div class="text-center mb-3">
                            <img src="{{ session('wa_qr') }}" alt="{{ __('QR code') }}" width="240" height="240" class="img-fluid border rounded p-2 bg-white">
                            <p class="small text-muted mt-2 mb-0">{{ __('On your phone: WhatsApp > Linked devices > Link a device, then scan this code.') }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('whatsapp.test') }}">@csrf
                        <label class="form-label" for="test-to">{{ __('Send a test message to') }}</label>
                        <div class="input-group">
                            <input type="text" inputmode="tel" class="form-control" id="test-to" name="to" dir="ltr" placeholder="93700123456" required autocomplete="off">
                            <button type="submit" class="btn btn-primary" @disabled(! $setting->isReady())><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button>
                        </div>
                        @unless ($setting->isReady())
                            <div class="form-text">{{ __('Switch sending on and save first.') }}</div>
                        @endunless
                    </form>

                    <hr>
                    <form method="POST" action="{{ route('whatsapp.forget') }}" data-confirm="{{ __('Remove the session key?') }}" data-confirm-text="{{ __('Nothing can be sent until a new key is entered. The message log stays.') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><x-icon name="trash" class="me-1" /> {{ __('Remove the session key') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Message log') }}</h5></div>
            <div class="card-body">
                @if ($messages->isEmpty())
                    <p class="text-muted mb-0">{{ __('No message was sent yet.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle w-100">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('To') }}</th><th>{{ __('About') }}</th><th>{{ __('Message') }}</th><th>{{ __('Sent by') }}</th><th>{{ __('Result') }}</th></tr></thead>
                            <tbody>
                                @foreach ($messages as $message)
                                    <tr>
                                        <td class="text-nowrap">{{ fmt_date($message->created_at) }} <span class="small text-muted" dir="ltr">{{ $message->created_at->timezone(company()->timezone ?: config('app.timezone'))->format('H:i') }}</span></td>
                                        <td dir="ltr">{{ $message->to }}</td>
                                        <td>{{ $message->reference ?: '—' }}</td>
                                        <td><span class="d-inline-block text-truncate" style="max-width:320px" title="{{ $message->body }}" dir="auto">{{ $message->body }}</span></td>
                                        <td>{{ $message->user?->name ?: '—' }}</td>
                                        <td>
                                            @if ($message->status === 'accepted')
                                                <span class="badge bg-success-subtle text-success-emphasis">{{ __('Accepted by RelayWA') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger-emphasis" title="{{ $message->error }}">{{ __('Not sent') }}</span>
                                                <span class="d-block small text-muted">{{ $message->error }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted">{{ __('"Accepted by RelayWA" means RelayWA took the message. Whether it was delivered or read is not known to this system.') }}</p>
                    {{ $messages->links('partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
