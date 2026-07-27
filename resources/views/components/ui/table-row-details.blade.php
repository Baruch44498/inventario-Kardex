@props([
    'id',
    'colspan',
])

<tr
    id="{{ $id }}"
    class="table-details-row"
    data-table-details-row
    hidden
>
    <td colspan="{{ $colspan }}">
        <div class="table-details-card">
            {{ $slot }}
        </div>
    </td>
</tr>
