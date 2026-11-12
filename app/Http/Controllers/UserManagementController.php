<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('company')->orderBy('name')->get();

        return view('system.users.index', compact('users'));
    }

    public function create()
    {
        $companies = Company::where('status', true)->orderBy('name')->get();

        return view('system.users.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['is_superuser'] = false;
        User::create($validated);

        return redirect()->route('system.users.index')->with('success', 'User created and assigned to the company.');
    }

    public function edit(User $user)
    {
        $companies = Company::where('status', true)->orderBy('name')->get();

        return view('system.users.edit', compact('user', 'companies'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validated($request, $user);
        if (!$validated['password']) {
            unset($validated['password']);
        }

        if ($user->is_superuser) {
            unset($validated['company_id'], $validated['role']);
        }

        $user->update($validated);

        return redirect()->route('system.users.index')->with('success', 'User and company assignment updated.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_if($user->is_superuser, 422, 'The system superuser cannot be deleted.');
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'alpha_dash', 'max:255', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'company_id' => [$user?->is_superuser ? 'nullable' : 'required', 'exists:companies,id'],
            'role' => [$user?->is_superuser ? 'nullable' : 'required', 'in:admin,finance_manager,accountant,auditor'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }
}
