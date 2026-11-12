<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyManagementController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('users')->orderBy('name')->get();

        return view('system.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('system.companies.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }
        Company::create($data);

        return redirect()->route('system.companies.index')->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        return view('system.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $this->validated($request, $company);
        if ($request->hasFile('logo')) {
            $oldLogo = $company->logo_path;
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }
        $company->update($data);

        return redirect()->route('system.companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        $company->update(['status' => false]);

        return back()->with('success', 'Company deactivated. Its historical records remain safely assigned.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:companies,name,'.($company?->id ?? 'NULL'),
            'registration_number' => 'nullable|string|max:255|unique:companies,registration_number,'.($company?->id ?? 'NULL'),
            'vat_number' => 'nullable|string|max:100|unique:companies,vat_number,'.($company?->id ?? 'NULL'),
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'country' => 'nullable|string|size:2',
            'status' => 'required|boolean',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    }
}
