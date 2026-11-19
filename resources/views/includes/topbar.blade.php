{{-- Top bar: brand, sidebar toggles, Help, settings and Logout. Everything stays visible on phones
     (no collapse), so Logout is always one tap away. --}}
<nav class="navbar acc-topbar">
    <div class="container-fluid">
        <div class="navbar-header">
            {{-- Opens the left menu on small screens (template JS toggles it below its breakpoint). --}}
            <a href="#" onClick="return false;" class="bars" aria-label="Open menu"></a>
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('assets/images/kodomo-mark.png') }}" alt="Kodomo Technologies" />
                <span class="logo-name">acc263</span>
            </a>
        </div>

        {{-- Collapses the left menu to icons on wide screens. --}}
        <a href="#" onClick="return false;" class="sidemenu-collapse acc-topbar-btn acc-topbar-desktop" aria-label="Collapse menu" title="Collapse menu">
            <i data-feather="menu"></i>
        </a>

        <div class="acc-topbar-actions">
            <a href="{{ route('help.index') }}" class="acc-topbar-btn acc-topbar-help {{ request()->routeIs('help.*') ? 'active' : '' }}" title="Help Centre: guides and downloads">
                <i class="fas fa-question-circle"></i><span class="acc-topbar-label">Help</span>
            </a>
            <a href="#" onClick="return false;" class="js-right-sidebar acc-topbar-btn" data-close="true" title="Display settings" aria-label="Display settings">
                <i class="fas fa-cog"></i>
            </a>
            @auth
                <button type="submit" form="logout-form" class="acc-topbar-btn acc-topbar-logout" title="Log out">
                    <i class="fas fa-power-off"></i><span class="acc-topbar-label">Logout</span>
                </button>
            @endauth
        </div>
    </div>
</nav>
@auth
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
@endauth
