{{-- $tabs: list of [label, route name, active pattern]; links the user cannot open are hidden. --}}
<ul class="nav nav-tabs mb-3">
    @foreach($tabs as [$label, $route, $pattern])
        @canroute($route)
            <li class="nav-item"><a class="nav-link {{ request()->routeIs(...explode('|', $pattern)) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a></li>
        @endcanroute
    @endforeach
</ul>
