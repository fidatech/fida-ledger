{{-- One block of a statement: a heading, its accounts and a total. $rows: [['account' => Account, 'amount' => '..']] --}}
<tr class="table-light"><th colspan="2">{{ $heading }}</th></tr>
@foreach ($rows as $row)
    <tr>
        <td style="padding-inline-start:28px"><a class="text-decoration-none" href="{{ route('accounts.show', ['id' => $row['account']->id, 'from' => $from ?? '2000-01-01', 'until' => $until]) }}">{{ $row['account']->name }}</a></td>
        <td class="text-end" dir="ltr">{{ money($row['amount']) }}</td>
    </tr>
@endforeach
@foreach ($extra ?? [] as $label => $amount)
    <tr><td style="padding-inline-start:28px">{{ $label }}</td><td class="text-end" dir="ltr">{{ money($amount) }}</td></tr>
@endforeach
<tr class="fw-bold"><td>{{ $totalLabel }}</td><td class="text-end" dir="ltr">{{ money($total) }}</td></tr>
