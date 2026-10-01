{{-- Super Admin panel --}}
@hasrole('super-admin')
    @include('livewire.admin.dashboard.partials.super-admin')
@else
    {{-- All other roles: role home with quick links from the side menu matrix --}}
    @include('livewire.admin.dashboard.partials.role-home')
@endhasrole
