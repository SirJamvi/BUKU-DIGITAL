<?php
namespace App\Services\Admin;

use App\Models\ExpenseCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ExpenseCategoryService
{
    public function getAllCategoriesWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return ExpenseCategory::where('business_id', Auth::user()->business_id)->latest()->paginate($perPage);
    }

    public function createCategory(array $data): ExpenseCategory
    {
        $data['business_id'] = Auth::user()->business_id;
        $data['created_by'] = Auth::id();
        return ExpenseCategory::create($data);
    }
}