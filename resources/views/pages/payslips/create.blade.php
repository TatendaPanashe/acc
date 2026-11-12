@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')


    <div class="container">
        <h1>Generate Payslip</h1>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form action="{{ route('payslips.generate') }}" method="POST">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="user_id">Select Employee:</label> {{-- Corrected label for --}}
                        <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror" required> {{-- Corrected name and id to user_id --}}
                            <option value="">-- Select Employee --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('id') == $user->id ? 'selected' : '' }}> {{-- Corrected value to $user->id --}}
                                    {{ $user->name }} ({{ $user->first_name ?? 'N/A' }}) {{-- Displaying user name and employee_id --}}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id') {{-- Corrected error check to user_id --}}
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label for="pay_period_start">Pay Period Start Date:</label>
                        <input type="date" name="pay_period_start" id="pay_period_start" class="form-control @error('pay_period_start') is-invalid @enderror" value="{{ old('pay_period_start') }}" required>
                        @error('pay_period_start')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label for="pay_period_end">Pay Period End Date:</label>
                        <input type="date" name="pay_period_end" id="pay_period_end" class="form-control @error('pay_period_end') is-invalid @enderror" value="{{ old('pay_period_end') }}" required>
                        @error('pay_period_end')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label for="pay_date">Pay Date:</label>
                        <input type="date" name="pay_date" id="pay_date" class="form-control @error('pay_date') is-invalid @enderror" value="{{ old('pay_date') }}" required>
                        @error('pay_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Generate Payslip PDF</button>
                </form>
            </div>
        </div>
    </div>
@endsection
