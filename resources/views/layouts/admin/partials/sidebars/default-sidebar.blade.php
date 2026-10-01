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
                                @php $isActive = isActiveMenu(\App\Services\MenuService::activePatterns($item)); @endphp

                                @if(!empty($item['children']))
                                    {{-- Collapsible group --}}
                                    <li class="submenu">
                                        <a href="javascript:void(0);" class="{{ $isActive ? 'active subdrop' : '' }}">
                                            <i class="{{ $item['icon'] }}"></i>
                                            <span>{{ $item['label'] }}</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <ul style="{{ $isActive ? 'display: block;' : '' }}">
                                            @foreach($item['children'] as $child)
                                                <li>
                                                    <a href="{{ route($child['route']) }}"
                                                       class="menu-item {{ isActiveMenu(\App\Services\MenuService::activePatterns($child)) ? 'active' : '' }}">
                                                        <i class="{{ $child['icon'] }}"></i> {{ $child['label'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ route($item['route']) }}" class="{{ $isActive ? 'active' : '' }}">
                                            <i class="{{ $item['icon'] }}"></i>
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->
