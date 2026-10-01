@php
    $user = Auth::user();
    $dashboardRoute = match ($user->role ?? '') {
        'super-admin' => 'admin.dashboard',
        default       => 'admin.dashboard', // fallback
    };
@endphp
<div class="header">
    <div class="main-header">

        <div class="header-left">
            <a href="{{ route($dashboardRoute) }}" class="logo">
                <img src="{{ asset('admin/assets/img/small_logo.webp') }} " alt="Logo">
            </a>
            <a href="{{ route($dashboardRoute) }}" class="dark-logo">
                <img src="{{ asset('admin/assets/img/small_logo.webp') }}" alt="Logo">
            </a>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#sidebar">
            <span class="bar-icon">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </a>

        <div class="header-user">
            <div class="nav user-menu nav-list">

                <div class="me-auto d-flex align-items-center" id="header-search">
                    <a id="toggle_btn" href="javascript:void(0);" class="btn btn-menubar me-1">
                        <i class="ti ti-arrow-bar-to-left"></i>
                    </a>
                    <!-- Search -->
                    <div class="input-group input-group-flat d-inline-flex me-1">
                        <span class="input-icon-addon">
                            <i class="ti ti-search"></i>
                        </span>
                        <input type="text" class="form-control" placeholder="Search in HRMS">
                        <span class="input-group-text">
                            <kbd>CTRL + / </kbd>
                        </span>
                    </div>
                    <!-- /Search -->

                    <a href="javascript:void(0)" class="btn btn-menubar">
                        <i class="ti ti-settings-cog"></i>
                    </a>
                </div>

                <div class="d-flex align-items-center">
                    <div class="me-1">
                        <a href="#" class="btn btn-menubar btnFullscreen">
                            <i class="ti ti-maximize"></i>
                        </a>
                    </div>
                    <div class="dropdown me-1">
                        <a href="#" class="btn btn-menubar" data-bs-toggle="dropdown">
                            <i class="ti ti-layout-grid-remove"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="card mb-0 border-0 shadow-none">
                                <div class="card-header">
                                    <h4>Applications</h4>
                                </div>
                                <div class="card-body">
                                    <a href="javascript:void()" class="d-block pb-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-calendar text-gray-9"></i></span>Calendar
                                    </a>
                                    <a href="javascript:void()" class="d-block py-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-subtask text-gray-9"></i></span>To Do
                                    </a>
                                    <a href="javascript:void()" class="d-block py-2">
                                        <span class="avatar avatar-md bg-transparent-dark me-2"><i class="ti ti-notes text-gray-9"></i></span>Notes
                                    </a>

                                </div>
                            </div>
                        </div>
                    </div>



                    <div class="dropdown profile-dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center"
                            data-bs-toggle="dropdown">
                            <span class="avatar avatar-sm online">
                                <img src="{{ asset('admin/assets/img/profiles/avatar-12.jpg ') }}" alt="Img" class="img-fluid rounded-circle">
                            </span>
                        </a>
                        <div class="dropdown-menu shadow-none">
                            <div class="card mb-0">
                                <div class="card-header">
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-lg me-2 avatar-rounded">
                                            <img src="{{ asset('admin/assets/img/profiles/avatar-12.jpg') }}" alt="img">
                                        </span>
                                        <div>
                                            <h5 class="mb-0">{{ auth()->user()->name }}</h5>
                                            <p class="fs-12 fw-medium mb-0">{{ auth()->user()->email }}</p>
                                            <p class="fs-12 text-muted mb-0">{{ auth()->user()->role ?? 'User' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <a class="dropdown-item d-inline-flex align-items-center p-0 py-2" href="javascript:void()">
                                        <i class="ti ti-user-circle me-1"></i>My Profile
                                    </a>
                                    <a class="dropdown-item d-inline-flex align-items-center p-0 py-2" href="javascript:void()">
                                        <i class="ti ti-settings me-1"></i>Settings
                                    </a>

                                    <a class="dropdown-item d-inline-flex align-items-center p-0 py-2" href="javascript:void()">
                                        <i class="ti ti-circle-arrow-up me-1"></i>My Account
                                    </a>

                                </div>
                                <div class="card-footer">
                                    <a class="dropdown-item d-inline-flex align-items-center p-0 py-2" href="{{route('admin.logout')}}">
                                        <i class="ti ti-login me-2"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="dropdown mobile-user-menu">
            <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="javascript:void()">My Profile</a>
                <a class="dropdown-item" href="javascript:void()">Settings</a>
                <a class="dropdown-item" href="{{route('admin.logout')}}">Logout</a>
            </div>
        </div>
        <!-- /Mobile Menu -->

    </div>

</div>
