<div class="d-flex align-items-center">
    @if($row->logo)
        <img src="{{ Storage::url($row->logo) }}"
             class="img-fluid rounded-circle"
             style="width:30px;height:30px;object-fit:cover;">
    @else
        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
             style="width:30px;height:30px;">
            <i class="ti ti-user text-muted"></i>
        </div>
    @endif
    <div class="ms-2">
        <p class="text-dark mb-0 ">{{ $row->company_name }}</p>
        <span class="fs-12 text-muted">{{ $row->user->name ?? '—' }}</span>
    </div>
</div>
