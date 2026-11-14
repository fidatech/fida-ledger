@extends('layouts.plain', ['title' => __('Confirm your email')])

@section('content')
<div class="card mx-auto" style="max-width:560px">
    <div class="card-body text-center py-5">
        <div class="fs-1 text-primary mb-2"><x-icon name="envelope-circle-check" /></div>
        <h1 class="h4">{{ __('Confirm your email address') }}</h1>
        <p class="text-muted">{{ __('We sent a link to :email. Open it to continue setting up your company.', ['email' => auth()->user()->email]) }}</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('Send the link again') }}</button>
        </form>
    </div>
</div>
@endsection
