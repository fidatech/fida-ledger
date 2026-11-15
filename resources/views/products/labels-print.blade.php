@extends('layouts.app', ['title' => __('Barcode Labels')])

@section('content')
<div class="d-print-none">
    <x-page-header :title="__('Barcode Labels')" icon="barcode" :subtitle="__('Labels').': '.count($labels)" :crumbs="[[__('Barcode Labels'), route('products.labels')], [__('Print')]]">
        @if ($labels)
            <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
        @endif
    </x-page-header>
    @unless ($labels)
        <div class="alert alert-warning">{{ __('Type how many labels you want beside at least one product.') }}</div>
    @endunless
</div>

<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:6px" dir="ltr">
    @foreach ($labels as $label)
        <div style="border:1px dashed #bbb;padding:6px 8px;text-align:center;break-inside:avoid;background:#fff;color:#000">
            <div style="font-size:11px;font-weight:600;line-height:1.2;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" dir="auto">{{ $label['name'] }}</div>
            <div style="height:42px;margin:3px 0">{!! str_replace('<svg ', '<svg width="100%" height="42" ', $label['svg']) !!}</div>
            <div style="font-size:10px;letter-spacing:1px">{{ $label['code'] }}</div>
            @if ($showPrice)
                <div style="font-size:12px;font-weight:700">{{ money($label['price'], base_currency()) }}</div>
            @endif
        </div>
    @endforeach
</div>
@endsection
