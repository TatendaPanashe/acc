@extends('layouts.main')

@section('title', $module['title'])

@section('content')
    @include('accounting.partials.page-header', ['title' => $module['title'], 'section' => 'Modules'])

    <div class="row">
        @foreach(($workspace['metrics'] ?? []) as $metric)
            <div class="col-xl-3 col-sm-6">
                <div class="card">
                    <div class="body d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block mb-2">{{ $metric['label'] }}</small>
                            <h4 class="mb-0">{{ $metric['value'] }}</h4>
                        </div>
                        <span class="{{ $metric['colour'] }} d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 42px; height: 42px;">
                            <i data-feather="{{ $metric['icon'] }}"></i>
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header d-flex align-items-center justify-content-between">
                    <h2><strong>{{ $module['title'] }}</strong> Workspace</h2>
                    <span class="badge col-blue">{{ ucfirst(str_replace('-', ' ', $moduleKey ?? 'module')) }}</span>
                </div>
                <div class="body">
                    <p class="text-muted mb-4">{{ $module['description'] }}</p>

                    <div class="row">
                        @foreach(($workspace['workstreams'] ?? []) as $workstream)
                            <div class="col-lg-4">
                                <div class="border rounded p-3 h-100 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="mb-0">{{ $workstream['title'] }}</h6>
                                        <span class="badge col-green">{{ $workstream['status'] }}</span>
                                    </div>
                                    <p class="text-muted mb-0">{{ $workstream['summary'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="header">
                    <h2><strong>{{ $workspace['recordsTitle'] ?? 'Module' }}</strong></h2>
                </div>
                <div class="body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                @foreach(array_slice($fields, 0, 5) as $field)
                                    <th>{{ $field['label'] }}</th>
                                @endforeach
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr>
                                    @foreach(array_slice($fields, 0, 5) as $field)
                                        @php
                                            $value = data_get($record, $field['name']);
                                            if ($value instanceof \Carbon\CarbonInterface) {
                                                $value = $value->format('Y-m-d');
                                            } elseif (is_bool($value)) {
                                                $value = $value ? 'Yes' : 'No';
                                            } elseif (isset($field['options'][$value])) {
                                                $value = $field['options'][$value];
                                            }
                                        @endphp
                                        <td>{{ $value ?: '-' }}</td>
                                    @endforeach
                                    <td class="text-end">
                                        <a href="{{ route('accounting.modules.records.edit', [$moduleKey, $record]) }}" class="btn btn-sm btn-outline-secondary">
                                            <i data-feather="edit-2"></i>
                                        </a>
                                        <form method="POST" action="{{ route('accounting.modules.records.destroy', [$moduleKey, $record]) }}" class="d-inline" onsubmit="return confirm('Delete this record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count(array_slice($fields, 0, 5)) + 1 }}" class="text-muted">No records configured for this module yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $records->links() }}
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="header">
                    <h2><strong>Capabilities</strong></h2>
                </div>
                <div class="body">
                    @foreach($module['capabilities'] as $capability)
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i data-feather="check-circle" class="col-green"></i>
                            <span>{{ $capability }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="header">
                    <h2><strong>New</strong> Record</h2>
                </div>
                <div class="body">
                    @include('accounting.modules.records._form', ['record' => null])
                </div>
            </div>
        </div>
    </div>
@endsection
