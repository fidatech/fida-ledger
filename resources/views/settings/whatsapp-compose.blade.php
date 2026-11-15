@extends('layouts.app', ['title' => __('WhatsApp message')])

@section('content')
<x-page-header :title="__('WhatsApp message')" icon="comment-dots" :subtitle="$about ?: null" :crumbs="[[__('WhatsApp'), can_do('settings.manage') ? route('whatsapp.index') : null], [__('New message')]]" />

<div class="row g-3">
    <div class="col-12 col-lg-7">
        @if (! $setting->isReady())
            <div class="alert alert-warning">{{ __('WhatsApp is not connected yet. Enter the session key under Settings > WhatsApp.') }}</div>
        @endif
        <form method="POST" action="{{ route('whatsapp.send') }}" class="card">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <input type="hidden" name="reference" value="{{ $reference }}">
            <input type="hidden" name="back" value="{{ $back }}">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="to">{{ __('Phone number') }} <span class="text-danger">*</span></label>
                    <input type="text" inputmode="tel" class="form-control" id="to" name="to" value="{{ old('to', $to) }}" dir="ltr" placeholder="93700123456" required autocomplete="off">
                    <div class="form-text">{{ __('With the country code. Save the number on the account to have it filled in next time.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="body">{{ __('Message') }} <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="body" name="body" rows="9" maxlength="4096" required dir="auto">{{ old('body', $body) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary" @disabled(! $setting->isReady())><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button>
                <a class="btn btn-outline-soft" href="{{ $back }}">{{ __('app.actions.cancel') }}</a>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body small text-muted">
                <p>{{ __('The message goes out from the WhatsApp number your company linked in RelayWA.') }}</p>
                <p class="mb-0">{{ __('Only text is sent. To send the document itself, print it to PDF and attach it in WhatsApp.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
