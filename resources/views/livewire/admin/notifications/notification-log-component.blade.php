<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Notification Log</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active">Notification Log</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <livewire:admin.components.table.data-table
            :model-class="\App\Models\Admin\NotificationLog::class"
            :columns="[
                ['label' => '#', 'field' => 'id', 'sortable' => true],
                ['label' => 'Date & Time', 'field' => 'formatted_created_at', 'sortable' => false],
                ['label' => 'Event', 'field' => 'event_type', 'sortable' => true],
                ['label' => 'Channel', 'field' => 'channel', 'sortable' => true],
                ['label' => 'Recipient', 'field' => 'recipient', 'sortable' => false],
                ['label' => 'Institute', 'field' => 'institute.name', 'sortable' => false],
                ['label' => 'Status', 'field' => 'status_html', 'type' => 'html', 'sortable' => false],
                ['label' => 'Error', 'field' => 'error', 'sortable' => false],
            ]"
            :filters="['All' => 'All', 'pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed']"
            sort-field="id"
            sort-direction="desc"
            title="Notifications"
        />
    </div>
</div>
