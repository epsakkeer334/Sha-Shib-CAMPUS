{{-- Super Admin panel --}}
@hasrole('super-admin')
    @include('livewire.admin.dashboard.partials.super-admin')
@endhasrole

{{-- Institute Admin --}}
@hasrole('institute-admin')
    @include('livewire.admin.dashboard.partials.institute-admin')
@endhasrole

{{-- Accounts --}}
@hasrole('accounts')
    @include('livewire.admin.dashboard.partials.accounts')
@endhasrole

{{-- Training Manager --}}
@hasrole('training-manager')
    @include('livewire.admin.dashboard.partials.training-manager')
@endhasrole

{{-- Head of Training (HOT) --}}
@hasrole('hot')
    @include('livewire.admin.dashboard.partials.hot')
@endhasrole

{{-- BiC --}}
@hasrole('bic')
    @include('livewire.admin.dashboard.partials.bic')
@endhasrole

{{-- Faculty --}}
@hasrole('faculty')
    @include('livewire.admin.dashboard.partials.faculty')
@endhasrole



