@extends('layouts.main')

@section('title', 'Statutory Reports')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Statutory Reports', 'section' => 'Reporting'])

    <div class="card">
        <div class="header">
            <h2><strong>Protected</strong> XLS Reports</h2>
        </div>
        <div class="body">
            <form method="GET" class="row align-items-end mb-4">
                <div class="col-md-4">
                    <label for="period_id">Accounting Period</label>
                    <select id="period_id" name="period_id" class="form-control">
                        <option value="">All posted periods</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}" @selected((string) request('period_id') === (string) $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit"><i data-feather="filter"></i> Apply</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Format</th>
                            <th>Protection</th>
                            <th class="text-end">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $key => $label)
                            <tr>
                                <td>
                                    <strong>{{ $label }}</strong>
                                    @if($key === 'statutory-pack')
                                        <small class="text-muted d-block">Includes all statutory worksheets in one workbook.</small>
                                    @endif
                                </td>
                                <td>XLS workbook</td>
                                <td><span class="badge col-green">Locked worksheet</span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-primary" href="{{ route('accounting.gl.statutory.download', [$key, 'period_id' => request('period_id')]) }}">
                                        <i data-feather="download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
