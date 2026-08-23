<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes (routes/api.php). Company must
 * exist before an HR account can be created for it - see
 * StoreStaffUserRequest's company_id rule.
 */
class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Company::query()->latest()->paginate(20));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create([
            'name' => $request->validated('name'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($company, 201);
    }
}
