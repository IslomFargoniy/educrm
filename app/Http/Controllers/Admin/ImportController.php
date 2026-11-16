<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentImporter;
use App\Support\CurrentBranch;
use App\Support\Exports\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/import', [
            'preview' => $request->session()->get('import_preview'),
            'branchId' => app(CurrentBranch::class)->id(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return XlsxExport::download('import-shablon.xlsx', StudentImporter::HEADERS, [
            ['Ali Valiyev', '+998901234567', 'Vali Valiyev', '+998901112233', 'Ingliz-1', today()->toDateString(), -50000],
            ['Olim Karimov', '', 'Karim Karimov', '+998935556677', 'Matematika-2', today()->toDateString(), 100000],
        ]);
    }

    public function preview(Request $request, StudentImporter $importer): RedirectResponse
    {
        $branchId = app(CurrentBranch::class)->id();
        abort_if($branchId === null, 422, __('Choose a branch first.'));
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        $rows = $importer->preview($request->file('file')->getRealPath(), $branchId);
        $request->session()->put('import_preview', ['branch_id' => $branchId, 'rows' => $rows]);

        return back();
    }

    public function confirm(Request $request, StudentImporter $importer): RedirectResponse
    {
        $preview = $request->session()->pull('import_preview');
        abort_if($preview === null, 422);
        $this->authorizeBranch($preview['branch_id']);

        $count = $importer->import($preview['rows'], $preview['branch_id']);
        $this->toast(__('Imported students: :count.', ['count' => $count]));

        return back();
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('import_preview');

        return back();
    }
}
