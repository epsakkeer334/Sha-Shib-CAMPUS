@props([
    'filter' => 'All',
    'options' => [
        'All' => 'All',
        'Active' => 'Active',
        'Inactive' => 'Inactive',
    ],
    'label' => 'Filter',
    'target' => 'filter'
])

<div class="dropdown">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
        <i class="ti ti-filter me-2"></i> {{ $filter }}
    </button>

    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        @foreach($options as $key => $value)
            <li>
                <a class="dropdown-item" href="#" wire:click.prevent="$set('{{ $target }}', '{{ $key }}')">{{ $value }}</a>
            </li>
        @endforeach
    </ul>
</div>
