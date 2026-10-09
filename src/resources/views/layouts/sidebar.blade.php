<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="#" class="brand-link">
        <h4 class="brand-text font-weight-bold m-0 ml-3">{{config('sag.page_name_short')}}</h4>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->

        @if (\HoangPhamDev\SimpleAdminGenerator\Services\SearchManager::enabled())
        <form class="form-inline" method="GET" action="{{ route('sag.search') }}" data-sag-sidebar-search>
            <div class="input-group">
                <input class="form-control form-control-sidebar" type="search" name="q" placeholder="Search" aria-label="Search" value="{{ is_string(request()->query('q')) ? request()->query('q') : '' }}">
                <div class="input-group-append">
                    <button class="btn btn-sidebar" type="submit" aria-label="Search"><i class="fas fa-search fa-fw"></i></button>
                </div>
            </div>
        </form>
        @endif

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                @forelse($sagMenuItems ?? [] as $menuItem)
                    @include('sag::menu.sidebar_item', ['item' => $menuItem])
                @empty
                    <li class="nav-header">NO MENU ITEMS</li>
                @endforelse
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
