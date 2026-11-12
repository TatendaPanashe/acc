@extends('layouts.main')

@section('title', 'Edit '.$module['title'].' Record')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit '.$module['title'].' Record', 'section' => 'Modules'])

    @php
        $recordCode = $record->code ?? $record->asset_number ?? $record->job_number ?? $record->contract_number ?? $record->workflow_key ?? '#'.$record->id;
        $recordTitle = $record->title ?? $record->name ?? $record->description ?? $record->bank_account_name ?? $record->workflow_key;
    @endphp

    <div class="card">
        <div class="header">
            <h2><strong>{{ $recordCode }}</strong> {{ $recordTitle }}</h2>
        </div>
        <div class="body">
            @include('accounting.modules.records._form')
        </div>
    </div>
@endsection
