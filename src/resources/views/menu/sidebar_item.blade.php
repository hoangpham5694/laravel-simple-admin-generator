@php
    $children = $item->childrenRecursive;
    $hasChildren = $children->isNotEmpty();
    $isActive = (bool) $item->menu_active;
@endphp

<li class="nav-item {{ $hasChildren && $isActive ? 'menu-is-opening menu-open' : '' }}">
    <a
        href="{{ $hasChildren ? '#' : $item->resolved_url }}"
        class="nav-link {{ $isActive ? 'active' : '' }}"
        target="{{ $item->target_window }}"
        @if($item->target_window === '_blank') rel="noopener noreferrer" @endif
    >
        <i class="nav-icon {{ $item->icon ?: ($hasChildren ? 'fas fa-folder' : 'far fa-circle') }}"></i>
        <p>
            {{ $item->title }}
            @if($hasChildren)
                <i class="fas fa-angle-left right"></i>
            @endif
        </p>
    </a>

    @if($hasChildren)
        <ul class="nav nav-treeview">
            @foreach($children as $child)
                @include('sag::menu.sidebar_item', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>
