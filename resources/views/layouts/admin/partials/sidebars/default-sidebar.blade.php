<!-- Sidebar (items and role matrix: config/menu.php) -->
@php
    $menuSections = \App\Services\MenuService::for(Auth::user());
@endphp

<div class="sidebar" id="sidebar">
    <!-- Logo -->
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
    <!-- /Logo -->

    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                @foreach($menuSections as $section)
                    <li class="menu-title"><span>{{ $section['title'] }}</span></li>
                    <li>
                        <ul>
                            @foreach($section['items'] as $item)
                                @include('layouts.admin.partials.sidebars.menu-item', ['item' => $item, 'level' => 0])
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->
