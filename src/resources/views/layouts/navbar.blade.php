<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>

    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        @if (\HoangPhamDev\SimpleAdminGenerator\Services\SearchManager::enabled())
        <li class="nav-item" data-sag-navbar-search>
            <a class="nav-link" data-widget="navbar-search" href="#" role="button" aria-label="Open search"><i class="fas fa-search"></i></a>
            <div class="navbar-search-block">
                <form class="form-inline" method="GET" action="{{ route('sag.search') }}" style="position: relative">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-navbar" type="search" name="q" placeholder="Search" aria-label="Search" value="{{ is_string(request()->query('q')) ? request()->query('q') : '' }}"
                            @if (config('sag.search.suggestions.enabled', true))
                            data-sag-search-input data-url="{{ route('sag.search.suggestions') }}" data-min-length="{{ config('sag.search.min_length', 2) }}" data-max-length="{{ config('sag.search.max_length', 200) }}" data-debounce="{{ config('sag.search.suggestions.debounce_ms', 300) }}" aria-controls="sag-search-suggestions" aria-expanded="false" autocomplete="off"
                            @endif>
                        <div class="input-group-append">
                            <button class="btn btn-navbar" type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                            <button class="btn btn-navbar" type="button" data-widget="navbar-search" aria-label="Close search"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    @if (config('sag.search.suggestions.enabled', true))
                    <div id="sag-search-suggestions" data-sag-search-results class="bg-white border rounded shadow" hidden aria-live="polite" style="position:absolute;top:100%;left:0;right:0;max-height:65vh;overflow:auto;z-index:1050"></div>
                    @endif
                </form>
            </div>
        </li>
        @endif

        <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                <span class="badge badge-warning navbar-badge">15</span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">15 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-envelope mr-2"></i> 4 new messages
                    <span class="float-right text-muted text-sm">3 mins</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-users mr-2"></i> 8 friend requests
                    <span class="float-right text-muted text-sm">12 hours</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-file mr-2"></i> 3 new reports
                    <span class="float-right text-muted text-sm">2 days</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
            </div>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-user"></i>
                <span>{{ Auth::guard('admin')->user()->first_name }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">

                <a href="{{route('sag.profile')}}" class="dropdown-item">Profile</a>
                <div class="dropdown-divider"></div>
                <a href="javascript:void(0)" onclick="$('#formLogout').submit()" class="dropdown-item">
                    Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<form action="{{route('sag.logout')}}" id="formLogout" method="post">@csrf</form>
