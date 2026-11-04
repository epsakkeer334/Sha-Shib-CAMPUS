<!-- Sidebar (items and role matrix: config/menu.php) -->
@php
    $menuSections = \App\Services\MenuService::for(Auth::user());
@endphp

<div class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
            <img src="{{ asset('brand/logo-navy.png') }}" alt="Sha-Shib Campus" class="img-fluid brand-logo">
        </a>

        <a href="{{ route('admin.dashboard') }}" class="logo-small">
            <img src="{{ asset('brand/seal-navy.png') }}" alt="Sha-Shib Campus" class="brand-seal brand-seal-navy">
            <img src="{{ asset('brand/seal-white.png') }}" alt="Sha-Shib Campus" class="brand-seal brand-seal-white">
        </a>
        <a href="{{ route('admin.dashboard') }}" class="dark-logo">
            <img src="{{ asset('brand/logo-white.png') }}" alt="Sha-Shib Campus" class="img-fluid brand-logo">
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
