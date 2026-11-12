<div class="block-header">
    <div class="row">
        <div class="col-12">
            <ul class="breadcrumb breadcrumb-style">
                <li class="breadcrumb-item">
                    <h4 class="page-title">{{ $title }}</h4>
                </li>
                <li class="breadcrumb-item bcrumb-1">
                    <a href="{{ route('dashboard') }}"><i class="fas fa-home"></i> Home</a>
                </li>
                @isset($section)
                    <li class="breadcrumb-item">{{ $section }}</li>
                @endisset
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ul>
        </div>
    </div>
</div>
@foreach(['success', 'warning', 'error'] as $flash)
    @if(session($flash))
        <div class="alert alert-{{ $flash === 'error' ? 'danger' : $flash }}">
            {{ session($flash) }}
        </div>
    @endif
@endforeach
