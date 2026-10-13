{{-- Role-based dashboard (see App\Services\DashboardService) --}}
@include('livewire.admin.dashboard.partials.' . (in_array($type, ['super-admin', 'institute-admin', 'accounts', 'training-manager'], true) ? $type : 'staff'))
