<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Lets a company admin create and manage the user accounts of their own company.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        return view('company.team.index', [
            'users' => User::where('company_id', $request->user()->company_id)
                ->where('is_superuser', false)
                ->orderBy('name')
                ->get(),
            'roles' => config('permissions.roles'),
        ]);
    }

    public function create(): View
    {
        return view('company.team.create', ['roles' => config('permissions.roles')]);
    }

    public function store(Request $request, \App\Services\Billing\Entitlements $entitlements): RedirectResponse
    {
        $data = $this->validated($request);
        $entitlements->assertWithinLimit($request->user()->company, 'users', 1, 'name');
        $this->assertRoleAllowed($request, $data['role'], $entitlements);

        $user = new User($data);
        $user->company_id = $request->user()->company_id;
        $user->is_superuser = false;
        $user->save();

        return redirect()->route('company.team.index')
            ->with('success', $user->name.' can now sign in as '.config('permissions.roles.'.$user->role).'.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureSameCompany($request, $user);

        return view('company.team.edit', ['member' => $user, 'roles' => config('permissions.roles')]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($request, $user);
        $data = $this->validated($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($data['role'] !== $user->role) {
            $this->assertRoleAllowed($request, $data['role'], app(\App\Services\Billing\Entitlements::class));
        }

        if ($request->user()->is($user) && $data['role'] !== $user->role) {
            return back()->withErrors(['role' => 'You cannot change your own role.'])->withInput();
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin' && $this->adminCount($user->company_id) <= 1) {
            return back()->withErrors(['role' => 'The company must keep at least one Company Admin.'])->withInput();
        }

        $user->update($data);

        return redirect()->route('company.team.index')->with('success', 'Team member updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($request, $user);

        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        if ($user->role === 'admin' && $this->adminCount($user->company_id) <= 1) {
            return back()->with('error', 'The company must keep at least one Company Admin.');
        }

        $user->delete();

        return back()->with('success', 'Team member removed.');
    }

    /** Finance Manager and Auditor roles are part of "Advanced permissions" (Business plan and above). */
    private function assertRoleAllowed(Request $request, string $role, \App\Services\Billing\Entitlements $entitlements): void
    {
        if (in_array($role, ['finance_manager', 'auditor'], true)) {
            $entitlements->assertFeature($request->user()->company, 'advanced_permissions', 'role');
        }
    }

    private function ensureSameCompany(Request $request, User $user): void
    {
        abort_if($user->is_superuser || $user->company_id !== $request->user()->company_id, 404);
    }

    private function adminCount(?int $companyId): int
    {
        return User::where('company_id', $companyId)->where('role', 'admin')->where('is_superuser', false)->count();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:255', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(array_keys(config('permissions.roles')))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
    }
}
