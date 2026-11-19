<?php

namespace App\Http\Controllers;

use App\Support\Exports\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a table the page already shows (with its filters applied) into an Excel file.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:120'],
            'headers' => ['required', 'array', 'max:40'],
            'headers.*' => ['nullable', 'string', 'max:200'],
            'rows' => ['present', 'array', 'max:20000'],
            'rows.*' => ['array', 'max:40'],
        ]);

        $rows = array_map(
            fn (array $row) => array_map(fn ($cell) => is_int($cell) || is_float($cell) ? $cell : (is_scalar($cell) ? (string) $cell : null), array_values($row)),
            $validated['rows'],
        );
        $name = Str::slug(pathinfo($validated['filename'], PATHINFO_FILENAME)) ?: 'export';

        return XlsxExport::download($name.'.xlsx', array_values(array_map(fn ($h) => (string) $h, $validated['headers'])), array_values($rows));
    }
}
