<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>@yield('title', 'acc263') | acc263</title>
    <!-- Favicon-->
    <link rel="icon" href="{{asset('assets/images/favicon.ico')}}" type="image/x-icon">
    <!-- Plugins Core Css -->
    <link href="{{ asset('assets/css/common.min.css') }}?v={{ filemtime(public_path('assets/css/common.min.css')) }}" rel="stylesheet">
    <link href="{{ asset('assets/fontawesome/css/fontawesome.css') }}?v={{ filemtime(public_path('assets/fontawesome/css/fontawesome.css')) }}" rel="stylesheet">
    <link href="{{ asset('assets/fontawesome/css/brands.css') }}?v={{ filemtime(public_path('assets/fontawesome/css/brands.css')) }}" rel="stylesheet">
    <link href="{{ asset('assets/fontawesome/css/solid.css') }}?v={{ filemtime(public_path('assets/fontawesome/css/solid.css')) }}" rel="stylesheet">
   
    <!-- Custom Css -->
    <link href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}" rel="stylesheet" />
    <!-- You can choose a theme from css/styles instead of get all themes -->
    <link href="{{ asset('assets/css/all-themes.css') }}?v={{ filemtime(public_path('assets/css/all-themes.css')) }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/acc263-uniform.css') }}?v={{ filemtime(public_path('assets/css/acc263-uniform.css')) }}" rel="stylesheet" />
    @stack('styles')
</head>

<body class="acc-app">
    <!-- Page Loader -->
    <div class="page-loader-wrapper">
        <div class="loader">
            <div class="m-t-30">
                <img class="loading-img-spin" src="{{asset('assets/images/loading.png')}}" alt="admin">
            </div>
            <p>Please wait...</p>
        </div>
    </div>
    <!-- #END# Page Loader -->
    <!-- Overlay For Sidebars -->
   
    <!-- #END# Overlay For Sidebars -->

    @include('includes.topbar')
    @include('includes.leftsidebar')
    @include('includes.rightsidebar')
    <section class="content">
        <div class="container-fluid">
            @auth
                @php $currentSubscription = auth()->user()->is_superuser ? null : auth()->user()->company?->subscription()->first(); @endphp
                @if($currentSubscription)
                    @php $billingNotice = app(\App\Services\Billing\SubscriptionAccess::class)->check($currentSubscription); @endphp
                    @if($billingNotice['state'] === 'open' && (($billingNotice['reason'] === 'trial' && $billingNotice['days_left'] <= 5) || $billingNotice['reason'] === 'grace'))
                        <div class="alert alert-{{ $billingNotice['reason'] === 'grace' ? 'danger' : 'warning' }} mb-3">
                            <i class="fas fa-clock"></i> {{ $billingNotice['message'] }}
                            @if($billingNotice['reason'] === 'trial') {{ $billingNotice['days_left'] }} {{ str('day')->plural($billingNotice['days_left']) }} left. @endif
                            @can('company.manage')<a href="{{ route('billing.index') }}" class="alert-link">{{ $billingNotice['reason'] === 'grace' ? 'Pay now' : 'Choose a plan' }}</a>@endcan
                        </div>
                    @endif
                @endif
                @cannot('operate')
                    <div class="alert alert-info mb-3"><i class="fas fa-eye"></i> You have <strong>read-only</strong> access ({{ auth()->user()->roleLabel() }}).</div>
                @endcannot
            @endauth
            @yield('content')
        </div>
    </section>


    <script>
        (function () {
            var loader = document.querySelector('.page-loader-wrapper');
            var hideLoader = function () {
                if (loader) {
                    loader.style.opacity = '0';
                    loader.style.pointerEvents = 'none';
                    window.setTimeout(function () { loader.style.display = 'none'; }, 200);
                }
            };

            hideLoader();
            window.addEventListener('load', hideLoader);
            window.setTimeout(hideLoader, 2000);
        })();
    </script>
    <script src="{{ asset('assets/js/common.min.js') }}?v={{ filemtime(public_path('assets/js/common.min.js')) }}"></script>
    <!-- Custom Js -->
    <script src="{{ asset('assets/js/admin.js') }}?v={{ filemtime(public_path('assets/js/admin.js')) }}"></script>
    @if(request()->routeIs('admin.dashboard'))
        <script src="{{asset('assets/js/echarts.min.js')}}"></script>
        <script src="{{asset('assets/js/apexcharts.min.js')}}"></script>
        <script src="{{asset('assets/js/index.js')}}"></script>
        <script src="{{asset('assets/js/todo.js')}}"></script>
    @endif
    <script src="{{ asset('assets/fontawesome/js/fontawesome.min.js') }}?v={{ filemtime(public_path('assets/fontawesome/js/fontawesome.min.js')) }}"></script>
    @stack('scripts')
    
@auth
    @cannot('operate')
        <script>
            // Read-only roles: disable controls on forms that would change data (the server rejects them anyway).
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('form').forEach(function (form) {
                    var method = (form.getAttribute('method') || 'GET').toUpperCase();
                    var isLogout = (form.getAttribute('action') || '').indexOf('/logout') !== -1;
                    if (method === 'GET' || isLogout) return;
                    form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
                        button.disabled = true;
                        button.title = 'Read-only access';
                    });
                });
            });
        </script>
    @endcannot
@endauth
</body>

</html>
