{{-- $metrics: list of [label, value, icon (feather), colour class, hint?] --}}
<div class="row">
    @foreach($metrics as $metric)
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="body d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block mb-2">{{ $metric[0] }}</small>
                        <h4 class="mb-0">{{ $metric[1] }}</h4>
                        @if(!empty($metric[4]))<small class="text-muted">{{ $metric[4] }}</small>@endif
                    </div>
                    <span class="{{ $metric[3] ?? 'col-blue' }} d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 42px; height: 42px;">
                        <i data-feather="{{ $metric[2] }}"></i>
                    </span>
                </div>
            </div>
        </div>
    @endforeach
</div>
