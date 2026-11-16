<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/expense-categories', [
            'categories' => ExpenseCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ExpenseCategory::query()->create($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back();
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]));

        return back();
    }
}
