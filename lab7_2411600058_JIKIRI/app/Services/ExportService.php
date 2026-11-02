<?php

namespace App\Services;

use App\Exports\Export;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    private const PDF_ROW_LIMIT = 1000;      // PDFs are built in memory, so very large tables are cut off

    /** Streams a CSV file (UTF-8 with BOM so Excel reads it correctly). */
    public function csv(Export $export, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$export->title(), $export->subtitle()]);
            fputcsv($out, $export->headings());

            foreach ($export->rows() as $row) {
                fputcsv($out, array_map(fn ($cell) => $this->safe($cell), $row));
            }

            fputcsv($out, []);
            foreach ($export->summary() as $label => $value) {
                fputcsv($out, [$label, $value]);
            }

            fclose($out);
        }, $this->filename($name, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A landscape A4 table report. */
    public function pdf(Export $export, string $name): Response
    {
        $rows = [];
        $truncated = false;

        foreach ($export->rows() as $row) {
            if (count($rows) >= self::PDF_ROW_LIMIT) {
                $truncated = true;
                break;
            }
            $rows[] = $row;
        }

        return Pdf::loadView('exports.pdf-table', [
            'appName' => config('app.name'),
            'title' => $export->title(),
            'subtitle' => $export->subtitle(),
            'headings' => $export->headings(),
            'rows' => $rows,
            'summary' => $export->summary(),
            'truncated' => $truncated,
            'limit' => self::PDF_ROW_LIMIT,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape')->download($this->filename($name, 'pdf'));
    }

    /** One purchase order as a printable document (download button and email attachment). */
    public function purchaseOrderPdf(PurchaseOrder $order): DomPdf
    {
        $order->loadMissing(['supplier', 'items.product', 'createdBy', 'approvedBy']);

        return Pdf::loadView('exports.purchase-order', [
            'order' => $order,
            'appName' => config('app.name'),
        ])->setPaper('a4', 'portrait');
    }

    /** Stops spreadsheet formula injection: text starting with = + - @ is prefixed with an apostrophe. */
    private function safe(mixed $cell): mixed
    {
        if (is_string($cell) && $cell !== '' && ! is_numeric($cell) && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $cell;
        }

        return $cell;
    }

    private function filename(string $name, string $extension): string
    {
        return $name . '_' . now()->format('Y-m-d_His') . '.' . $extension;
    }
}