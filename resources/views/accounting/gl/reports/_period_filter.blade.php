<div class="card">
    <div class="body">
        <form method="GET" action="{{ $route }}" class="row align-items-end">
            <div class="col-md-4 mb-2">
                <label class="form-label">Accounting Period</label>
                <select name="period_id" class="form-control">
                    <option value="">{{ $emptyLabel ?? 'As at today' }}</option>
                    @foreach($periods as $filterPeriod)
                        <option value="{{ $filterPeriod->id }}" @selected(request('period_id') == $filterPeriod->id)>{{ $filterPeriod->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Apply</button>
                <a href="{{ $route }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>
