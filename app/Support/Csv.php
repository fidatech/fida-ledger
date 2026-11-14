<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sends rows as a CSV file that Excel opens correctly (UTF-8 with a byte-order mark, so Dari and Pashto show properly). */
class Csv
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows  may be a generator, so a long history is never held in memory
     */
    public static function download(string $name, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                // a cell starting with = + - @ could run as a formula in a spreadsheet: it is made plain text
                fputcsv($out, array_map(fn ($cell) => is_string($cell) && preg_match('/^[=+@]|^-(?!\d)/', $cell) ? "'".$cell : $cell, $row));
            }
            fclose($out);
        }, $name.'-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
