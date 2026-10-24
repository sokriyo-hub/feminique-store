<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download (UTF-8 with BOM, so Excel shows Bangla correctly).
 * Rows are written one by one, so big exports do not fill the server memory.
 */
class CsvWriter
{
    /**
     * @param  Closure  $produce  receives a function: $write(array $row)
     */
    public static function download(string $filename, array $header, Closure $produce): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $produce): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '', "\r\n");

            $produce(function (array $row) use ($out): void {
                fputcsv($out, $row, ',', '"', '', "\r\n");
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Text cell. A cell starting with = + - @ would be run as a formula by Excel, so it gets a leading '
    public static function text(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    // 1200.00 -> "1200", 99.5 -> "99.5", null -> ""
    public static function number(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}