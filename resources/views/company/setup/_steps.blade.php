@if(!auth()->user()->company?->onboarding_completed_at)
    <div class="card">
        <div class="body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @foreach($steps as $key => $step)
                    @php $isCurrent = $key === $currentStep; @endphp
                    <a href="{{ route($step['route']) }}"
                       class="btn btn-sm {{ $isCurrent ? 'btn-primary' : ($step['done'] ? 'btn-outline-success' : 'btn-outline-secondary') }}">
                        <i class="fas {{ $step['done'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                        {{ $loop->iteration }}. {{ $step['label'] }}
                    </a>
                    @unless($loop->last)<i class="fas fa-chevron-right text-muted"></i>@endunless
                @endforeach
            </div>
            <p class="text-muted mb-0 mt-2">
                Complete each step to open your workspace. Your team can sign in once setup is finished.
            </p>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
