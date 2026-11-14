@extends('layouts.admin', ['title' => __('Audit Logs')])

@section('content')
<x-page-header :title="__('Audit Logs')" icon="clock-rotate-left" :subtitle="__('What administrators did, and the sensitive actions inside companies (the action only, never the books).')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search the action or the subject…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-8 col-md-auto"><select class="form-select" name="only" data-plain onchange="this.form.submit()" aria-label="{{ __('Who') }}"><option value="">{{ __('Everyone') }}</option><option value="platform" @selected($only === 'platform')>{{ __('Administrators only') }}</option></select></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>
        @if ($logs->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="clock-rotate-left" /></div><h3>{{ __('Nothing found') }}</h3></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Company') }}</th><th>{{ __('Action') }}</th><th>{{ __('Subject') }}</th><th>IP</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-nowrap small">{{ $log->created_at?->format('j M Y H:i') }}</td>
                                <td>@if ($log->admin_id)<span class="badge bg-primary-subtle text-primary-emphasis">{{ __('Admin') }}</span> {{ $admins[$log->admin_id] ?? '' }}@else<span class="small text-muted">{{ __('Company user') }}</span>@endif</td>
                                <td>{{ $log->company_id ? ($companies[$log->company_id] ?? '') : '' }}</td>
                                <td>{{ $log->action }}</td>
                                <td class="small">{{ $log->subject }}@if ($log->admin_id && $log->details) <span class="text-muted" dir="ltr">{{ \Illuminate\Support\Str::limit(json_encode($log->details, JSON_UNESCAPED_UNICODE), 120) }}</span>@endif</td>
                                <td class="small text-muted" dir="ltr">{{ $log->ip }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
