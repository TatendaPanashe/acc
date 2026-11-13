@php
    $label = $run->status === 'draft' ? ($run->calculated_at ? 'Awaiting approval' : 'Draft: not calculated') : (\App\Modules\Payroll\Models\PayrollRun::STATUSES[$run->status] ?? $run->status);
    $class = ['draft' => $run->calculated_at ? 'bg-warning text-dark' : 'bg-light text-dark', 'approved' => 'bg-primary', 'paid' => 'bg-success', 'cancelled' => 'bg-secondary'][$run->status] ?? 'bg-light';
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
