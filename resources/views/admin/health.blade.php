@extends('layouts.admin', ['title' => __('System Health')])

@section('content')
<x-page-header :title="__('System Health')" icon="heart-pulse" />

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100 mb-0"><div class="card-body">
            <table class="table align-middle w-100 mb-0">
                <tbody>
                    @foreach ($checks as [$name, $ok, $detail])
                        <tr><td style="width:36px"><span class="{{ $ok ? 'text-success' : 'text-danger' }}"><x-icon :name="$ok ? 'circle-check' : 'triangle-exclamation'" /></span></td><td class="fw-semibold">{{ $name }}</td><td class="small text-muted">{{ $detail }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100 mb-0"><div class="card-body">
            @foreach ($facts as $name => $value)
                <div class="d-flex justify-content-between gap-3 border-bottom py-2 small"><span class="text-muted">{{ $name }}</span><span class="text-end" dir="auto">{{ $value }}</span></div>
            @endforeach
        </div></div>
    </div>
</div>
@endsection
