<div class="card shadow-sm border-0">
    <!-- Header Row -->
    <div class="card-header bg-white border-bottom">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <h5 class="fw-semibold mb-0">{{ $title }}</h5>

            @if(!empty($filters))
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                        <i class="ti ti-filter me-2"></i> {{ $filter }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        @foreach($filters as $key => $value)
                            <li>
                                <a class="dropdown-item" wire:click="$set('filter', '{{ $key }}')">{{ $value }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <!-- Sub-header -->
    <div class="border-bottom px-3 py-2 bg-light d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Per Page:</span>
            <select wire:model="perPage" class="form-select form-select-sm w-auto">
                <option>5</option>
                <option>10</option>
                <option>25</option>
                <option>50</option>
            </select>
            <span class="text-muted small">Entries</span>
        </div>

        <div class="position-relative">
            <i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-4 text-muted"></i>
            <input type="text" class="form-control form-control-sm ps-5" style="min-width: 220px;" placeholder="Search..." wire:model.debounce.500ms="search">
        </div>
    </div>

    <!-- Table -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        @foreach($columns as $column)
                            @php
                                $isSortable = $column['sortable'] ?? true; // Default to true if not specified
                            @endphp

                            @if($isSortable)
                                <th wire:click="sortBy('{{ $column['field'] }}')" style="cursor: pointer;">
                                    {{ $column['label'] }}
                                    @if($sortField === $column['field'])
                                        <i class="ti {{ $sortDirection === 'asc' ? 'ti-arrow-up' : 'ti-arrow-down' }}"></i>
                                    @endif
                                </th>
                            @else
                                <th>
                                    {{ $column['label'] }}
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $index => $row)
                        <tr>
                            @foreach($columns as $column)
                                @php
                                    $field = $column['field'];
                                    $value = data_get($row, $field);
                                    $type  = $column['type'] ?? 'default';
                                    $partial = "livewire.admin.components.table.columns.{$type}";

                                    // Add conditional styling for specific columns
                                    $tdClass = '';
                                    $tdStyle = '';

                                    if ($field === 'id' || $type === 'sl_no') {
                                        $tdClass = 'text-center text-muted small';
                                        $tdStyle = 'width: 60px;';
                                    }
                                @endphp

                                <td class="{{ $tdClass }}" style="{{ $tdStyle }}">
                                    @if(View::exists($partial))
                                        @include($partial, compact('row', 'column', 'value', 'index', 'rows', 'field'))
                                    @else
                                        {{ $value ?? '—' }}
                                    @endif
                                </td>
                            @endforeach

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-center text-muted py-4">
                                <i class="ti ti-mood-sad fs-2"></i><br>
                                No records found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-white border-top">
        <div class="text-muted small">
            Showing <strong>{{ $rows->firstItem() ?? 0 }}</strong> to
            <strong>{{ $rows->lastItem() ?? 0 }}</strong> of
            <strong>{{ $rows->total() }}</strong> entries
        </div>
        <div>{{ $rows->links() }}</div>
    </div>
</div>
