{{-- One side-menu entry; groups render their children recursively (any depth). $level: 0 = top. --}}
@php $isActive = isActiveMenu(\App\Services\MenuService::activePatterns($item)); @endphp

@if(!empty($item['children']))
    <li class="submenu {{ $level > 0 ? 'submenu-two' : '' }}">
        <a href="javascript:void(0);" class="{{ $isActive ? 'active subdrop' : '' }}">
            @if($level === 0)<i class="{{ $item['icon'] }}"></i>@endif
            <span>{{ $item['label'] }}</span>
            <span class="menu-arrow {{ $level > 0 ? 'inside-submenu' : '' }}"></span>
        </a>
        <ul style="{{ $isActive ? 'display: block;' : '' }}">
            @foreach($item['children'] as $child)
                @include('layouts.admin.partials.sidebars.menu-item', ['item' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    </li>
@elseif($level === 0)
    <li>
        <a href="{{ route($item['route']) }}" class="{{ $isActive ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    </li>
@else
    <li>
        <a href="{{ route($item['route']) }}" class="menu-item {{ $isActive ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}
        </a>
    </li>
@endif
