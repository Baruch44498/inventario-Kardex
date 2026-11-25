@props([
    'id',
    'colspan',
])

<tr
    id="{{ $id }}"
    class="table-details-row"
    role="row"
    data-table-details-row
    hidden
>
    <td role="cell" colspan="{{ $colspan }}">
        <div class="table-details-card">
            {{ $slot }}
        </div>
    </td>
</tr>
