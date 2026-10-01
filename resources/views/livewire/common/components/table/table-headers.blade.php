@props(['columns'])
<thead>
    <tr>
        @foreach ($columns as $column)
            @if (!isset($column['sortable']) || $column['sortable'])
                <th style="cursor:pointer;" wire:click="sortBy('{{ $column['field'] }}')">
                    {{ $column['label'] }}
                    @if ($sortField === $column['field'])
                        <i class="ti ti-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                    @endif
                </th>
            @else
                <th>{{ $column['label'] }}</th>
            @endif
        @endforeach

    </tr>
</thead>

