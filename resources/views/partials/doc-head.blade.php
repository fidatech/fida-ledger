{{-- Date, currency, rate and warehouse boxes shared by order forms. $doc is the saved document or null. --}}
@php
    $num = fn ($number) => $number === null || $number === '' ? '' : (str_contains((string) $number, '.') ? rtrim(rtrim((string) $number, '0'), '.') : (string) $number);
@endphp
<div class="col-6 col-md-2">
    <label class="form-label" for="date">{{ __('Date') }} <span class="text-danger">*</span></label>
    <x-date name="date" :value="old('date', $doc?->date ?? today_iso())" :required="true" id="date" />
</div>
<div class="col-6 col-md-2">
    <label class="form-label" for="{{ $secondDate }}">{{ $secondLabel }}</label>
    <x-date :name="$secondDate" :value="old($secondDate, $doc?->{$secondDate})" id="{{ $secondDate }}" />
</div>
<div class="col-6 col-md-2">
    <label class="form-label" for="warehouse_id">{{ __('Warehouse') }} <span class="text-danger">*</span></label>
    <select class="form-select" id="warehouse_id" name="warehouse_id" required data-plain>
        @foreach ($warehouses as $warehouse)
            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $doc?->warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-6 col-md-1">
    <label class="form-label" for="currency">{{ __('Currency') }}</label>
    <select class="form-select" id="currency" name="currency" required data-plain>
        @foreach (currencies() as $code)
            <option value="{{ $code }}" @selected(old('currency', $doc?->currency ?? base_currency()) === $code)>{{ $code }}</option>
        @endforeach
    </select>
</div>
<div class="col-6 col-md-1">
    <label class="form-label" for="rate">{{ __('Rate') }}</label>
    <input type="text" inputmode="decimal" class="form-control" id="rate" name="rate" value="{{ $num(old('rate', $doc && $doc->currency !== base_currency() ? $doc->rate : '')) }}" autocomplete="off" dir="ltr" title="{{ __('Only for a currency other than :currency.', ['currency' => base_currency()]) }}">
</div>
