@extends('layouts.main')

@section('title', 'Auto-coding')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'GL auto-coding', 'section' => 'AI/ML Automation'])
    @include('accounting.ai._tabs')

    @include('accounting.partials.metrics', ['metrics' => [
        ['Training lines', $evaluation['samples'], 'database', 'col-blue', 'from posted supplier invoices'],
        ['Accounts learned', $evaluation['accounts'], 'list', 'col-purple'],
        ['Top suggestion correct', $evaluation['accuracy'] !== null ? $evaluation['accuracy'].'%' : '—', 'check-circle', 'col-green', $evaluation['tested'] ? 'on the newest '.$evaluation['tested'].' lines' : 'needs 10+ lines'],
        ['Correct in top 3', $evaluation['top3'] !== null ? $evaluation['top3'].'%' : '—', 'award', 'col-orange'],
    ]])

    <div class="row">
        <div class="col-xl-7">
            <div class="card">
                <div class="header"><h2><strong>Try</strong> it</h2></div>
                <div class="body">
                    <form method="GET" class="row g-2 align-items-end mb-3">
                        <div class="col-md-6"><label class="form-label">Line description</label><input name="text" class="form-control" value="{{ $text }}" placeholder="ZESA electricity for March" required maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label">Supplier (optional)</label><select name="supplier_id" class="form-control"><option value="">—</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">Suggest</button></div>
                    </form>
                    @if($text !== '')
                        @forelse($suggestions as $s)
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>{{ $s['code'] }} {{ $s['name'] }}</span>
                                <span class="d-flex align-items-center gap-2" style="min-width:160px"><span class="progress flex-grow-1" style="height:6px" role="progressbar" aria-label="{{ round($s['probability'] * 100) }}%"><span class="progress-bar" style="width: {{ round($s['probability'] * 100) }}%"></span></span><small>{{ round($s['probability'] * 100) }}%</small></span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No suggestion yet: post some supplier invoices so the model can learn how you code them.</p>
                        @endforelse
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="header"><h2><strong>How</strong> it works</h2></div>
                <div class="body small">
                    <p>When someone types a line description on a supplier invoice, acc263 suggests the expense account under the account box. If no account is chosen yet and the model is at least 60% sure, it fills the account in; the person can always change it.</p>
                    <p>The model is a naive Bayes classifier trained on the words of every posted supplier invoice line and its supplier. It retrains itself as new invoices are posted (every 10 minutes at most) and is measured on the newest 20% of lines, which it has not seen.</p>
                    <p class="mb-0 text-muted">It runs inside acc263; your data is not sent anywhere.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
