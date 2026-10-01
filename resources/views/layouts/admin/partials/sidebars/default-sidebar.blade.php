<!-- Sidebar -->
@php
    $user = Auth::user();
    $dashboardRoute = match ($user->role ?? '') {
        'super-admin' => 'admin.dashboard',
        default       => 'admin.dashboard', // fallback
    };
@endphp

<div class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo" class="img-fluid" style="max-width: 90%;">
        </a>

        <a href="{{ route($dashboardRoute) }}" class="logo-small">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo">
        </a>
        <a href="{{ route($dashboardRoute) }}" class="dark-logo">
            <img src="{{ asset('admin/assets/img/small_logo.png') }}" alt="Logo">
        </a>
    </div>
    <!-- /Logo -->

    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <!-- DASHBOARD -->
                <li class="menu-title"><span>Main</span></li>
                <li>
                    <ul>
                        <li>
                            <a href="{{ route($dashboardRoute) }}">
                                <i class="ti ti-layout-dashboard"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- INSTITUTE MANAGEMENT -->
                @hasrole('super-admin')
                <li class="menu-title"><span>Institute Management</span></li>
                <li>
                    <ul>
                        <li class="submenu">
                            <a href="javascript:void(0);" class="{{ isActiveMenu(['admin.institutes*', 'admin.institute-users*']) ? 'active subdrop' : '' }}">
                                <i class="ti ti-building"></i>
                                <span>Institute Management</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="{{ isActiveMenu(['admin.institutes*']) ? 'display: block;' : '' }}">
                                <li>
                                    <a href="{{ route('admin.institutes') }}" class="menu-item {{ request()->routeIs('admin.institutes') ? 'active' : '' }}">
                                        <i class="ti ti-building-community"></i> Manage Institutes
                                    </a>
                                </li>

                            </ul>
                        </li>
                    </ul>
                </li>
                @endhasrole

            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->
