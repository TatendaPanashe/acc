@extends('layouts.main')
@section('title', 'Team')
@section('content')
    @include('accounting.partials.page-header', ['title' => 'Team', 'section' => 'Company'])
    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>{{ auth()->user()->company?->name }}</strong> users</h2>
            <a href="{{ route('company.team.create') }}" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add team member</a>
        </div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach($users as $member)
                    <tr>
                        <td>{{ $member->name }} @if(auth()->user()->is($member))<span class="badge bg-secondary">you</span>@endif</td>
                        <td>{{ $member->username }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->roleLabel() }}</td>
                        <td class="text-end">
                            <a href="{{ route('company.team.edit', $member) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a>
                            @unless(auth()->user()->is($member))
                                <form class="d-inline" method="POST" action="{{ route('company.team.destroy', $member) }}" onsubmit="return confirm('Remove this team member? They will no longer be able to sign in.')">
                                    @csrf @method('DELETE')<button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
