@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" :icon="$icon" :subtitle="$subtitle" :crumbs="[[__('Reports'), route('reports.index')], [$title]]">
    @if (can_do('reports.export'))
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Excel (CSV)') }}</a>
    @endif
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print / PDF') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        @if ($dates)
            <form method="GET" class="row g-2 mb-3 no-print">
                @if ($dates === 'range')
                    <div class="col-6 col-md-auto"><x-date name="from" :value="$from" title="{{ __('From') }}" aria-label="{{ __('From') }}" /></div>
                @endif
                <div class="col-6 col-md-auto"><x-date name="until" :value="$until" title="{{ __('Until') }}" aria-label="{{ __('Until') }}" /></div>
                <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
            </form>
            <p class="small text-muted d-none d-print-block">{{ $dates === 'range' ? fmt_date($from).' – '.fmt_date($until) : fmt_date($until) }} · {{ company()->name }}</p>
        @endif

        @if (! $rows)
            <div class="empty-state"><div class="es-icon"><x-icon :name="$icon" /></div><h3>{{ __('Nothing to report for this period') }}</h3></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover w-100">
                    <thead><tr>@foreach ($headings as [$label, $align])<th class="{{ $align === 'end' ? 'text-end' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $i => $cell)
                                    <td class="{{ ($headings[$i][1] ?? null) === 'end' ? 'text-end' : '' }}" @if (($headings[$i][1] ?? null) === 'end') dir="ltr" @endif>@if (is_array($cell))<a class="text-decoration-none" href="{{ $cell[1] }}">{{ $cell[0] }}</a>@else{{ $cell }}@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($footer)
                        <tfoot><tr class="fw-bold">@foreach ($footer as $i => $cell)<td class="{{ ($headings[$i][1] ?? null) === 'end' ? 'text-end' : '' }}" @if (($headings[$i][1] ?? null) === 'end') dir="ltr" @endif>{{ $cell }}</td>@endforeach</tr></tfoot>
                    @endif
                </table>
            </div>
        @endif
        @if ($note)
            <p class="small text-muted mb-0">{{ $note }}</p>
        @endif
    </div>
</div>
@endsection
