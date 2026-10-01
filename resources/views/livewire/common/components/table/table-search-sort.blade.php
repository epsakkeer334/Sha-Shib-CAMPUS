
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
        <input type="text" class="form-control form-control-sm ps-5" style="min-width: 220px;"
                placeholder="Search by key words..."
                wire:model.debounce.500ms="search">
    </div>

