<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Audit Trail</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Audit Trail</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\Admin\AuditTrail::class"
            :columns="[
                ['label' => '#', 'field' => 'id', 'sortable' => true],
                ['label' => 'Date & Time', 'field' => 'formatted_created_at', 'sortable' => false],
                ['label' => 'User', 'field' => 'user.name', 'sortable' => false],
                ['label' => 'Institute', 'field' => 'institute.name', 'sortable' => false],
                ['label' => 'Module', 'field' => 'module', 'sortable' => true],
                ['label' => 'Action', 'field' => 'action', 'sortable' => true],
                ['label' => 'Record', 'field' => 'reference_label', 'sortable' => false],
                ['label' => 'IP Address', 'field' => 'ip_address', 'sortable' => false],
                ['label' => 'Details', 'field' => 'actions', 'type' => 'actions', 'actions' => ['view']],
            ]"
            :filters="['All' => 'All', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'approve' => 'Approve', 'reject' => 'Reject', 'bypass' => 'Bypass']"
            filter-field="action"
            sort-field="id"
            sort-direction="desc"
            title="Audit Log"
        />
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-primary bg-opacity-10 border-bottom">
                    <h4 class="modal-title fw-semibold mb-0">Audit Entry</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    @if($selected)
                        <dl class="row small mb-3">
                            <dt class="col-sm-3">Date & Time</dt><dd class="col-sm-9">{{ $selected['when'] }}</dd>
                            <dt class="col-sm-3">User</dt><dd class="col-sm-9">{{ $selected['user'] }}</dd>
                            <dt class="col-sm-3">Institute</dt><dd class="col-sm-9">{{ $selected['institute'] }}</dd>
                            <dt class="col-sm-3">Action</dt><dd class="col-sm-9">{{ ucfirst($selected['action']) }} — {{ $selected['module'] }}</dd>
                            <dt class="col-sm-3">Record</dt><dd class="col-sm-9">{{ $selected['reference'] }}</dd>
                            <dt class="col-sm-3">IP Address</dt><dd class="col-sm-9">{{ $selected['ip'] ?? '—' }}</dd>
                            @isset($selected['meta']['description'])
                                <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $selected['meta']['description'] }}</dd>
                            @endisset
                            @isset($selected['meta']['reason'])
                                <dt class="col-sm-3">Reason</dt><dd class="col-sm-9">{{ $selected['meta']['reason'] }}</dd>
                            @endisset
                        </dl>

                        @if(isset($selected['meta']['old']) || isset($selected['meta']['new']))
                            @php
                                $old = $selected['meta']['old'] ?? [];
                                $new = $selected['meta']['new'] ?? [];
                                $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
                            @endphp
                            <table class="table table-sm table-bordered small mb-0">
                                <thead class="table-light">
                                    <tr><th>Field</th><th>Before</th><th>After</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($fields as $field)
                                        @php
                                            $before = $old[$field] ?? null;
                                            $after = $new[$field] ?? null;
                                            $fmt = fn ($v) => is_array($v) ? json_encode($v) : (string) $v;
                                        @endphp
                                        <tr class="{{ $fmt($before) !== $fmt($after) ? 'table-warning' : '' }}">
                                            <td><code>{{ $field }}</code></td>
                                            <td class="text-break">{{ \Illuminate\Support\Str::limit($fmt($before), 200) }}</td>
                                            <td class="text-break">{{ \Illuminate\Support\Str::limit($fmt($after), 200) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-audit-modal', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).show();
    });
});
</script>
