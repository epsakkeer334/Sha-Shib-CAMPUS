<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo" class="img-fluid" style="max-width: 90%;">
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo-small">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo">
        </a>
        <a href="{{ route('admin.dashboard') }}" class="dark-logo">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo">
        </a>
    </div>

    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="menu-title"><span>Main</span></li>
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="ti ti-layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                @hasrole('super-admin')
                <li class="menu-title"><span>Institute Management</span></li>
                <li>
                    <a href="{{ route('admin.institutes') }}" class="{{ request()->routeIs('admin.institutes') ? 'active' : '' }}">
                        <i class="ti ti-building-community"></i>
                        <span>Manage Institutes</span>
                    </a>
                </li>
                @endhasrole
            </ul>
        </div>
    </div>
</div>
