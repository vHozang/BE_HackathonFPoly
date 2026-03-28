<?php
declare(strict_types=1);

namespace App\Core;

use ZipArchive;

class SpreadsheetReader
{
    public static function read(string $tmpPath, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw new HttpException('Unsupported file type. Only .xlsx and .csv are allowed.', 422, 'validation_error');
        }

        $rawRows = $extension === 'xlsx'
            ? self::readXlsxRows($tmpPath)
            : self::readCsvRows($tmpPath);

        if ($rawRows === []) {
            throw new HttpException('Spreadsheet is empty.', 422, 'validation_error');
        }

        $headers = array_map(static function (mixed $value): string {
            $text = trim((string) $value);
            return preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        }, $rawRows[0]);

        $rows = [];
        for ($i = 1, $max = count($rawRows); $i < $max; $i++) {
            $row = $rawRows[$i];
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }
            $rows[] = $assoc;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private static function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new HttpException('Cannot open CSV file.', 422, 'validation_error');
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = self::detectCsvDelimiter($firstLine === false ? '' : $firstLine);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }

        fclose($handle);
        return $rows;
    }

    private static function detectCsvDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;

        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    private static function readXlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new HttpException('ZipArchive extension is required to read .xlsx files.', 500, 'internal_server_error');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new HttpException('Cannot open XLSX file.', 422, 'validation_error');
        }

        $sheetPath = self::resolveFirstWorksheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();
            throw new HttpException('Cannot read first worksheet from XLSX file.', 422, 'validation_error');
        }

        $sharedStrings = self::extractSharedStrings($zip);
        $zip->close();

        libxml_use_internal_errors(true);
        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new HttpException('Invalid worksheet XML.', 422, 'validation_error');
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $sheet->xpath('//x:sheetData/x:row');
        if (!is_array($rowNodes)) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $cellNodes = $rowNode->xpath('x:c');
            if (!is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colLetters = preg_replace('/\d+/', '', $ref) ?? '';
                if ($colLetters === '') {
                    continue;
                }

                $columnIndex = self::columnLettersToIndex($colLetters);
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $sharedIndex = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $cells[$columnIndex] = $value;
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $maxIndex = (int) max(array_keys($cells));
            $normalized = array_fill(0, $maxIndex + 1, '');
            foreach ($cells as $index => $value) {
                $normalized[(int) $index] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private static function resolveFirstWorksheetPath(ZipArchive $zip): string
    {
        $defaultPath = 'xl/worksheets/sheet1.xml';
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return $defaultPath;
        }

        libxml_use_internal_errors(true);
        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if ($workbook === false || $rels === false) {
            return $defaultPath;
        }

        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheetNodes = $workbook->xpath('//x:sheets/x:sheet');
        if (!is_array($sheetNodes) || $sheetNodes === []) {
            return $defaultPath;
        }

        $relationshipId = (string) ($sheetNodes[0]->attributes('r', true)->id ?? '');
        if ($relationshipId === '') {
            return $defaultPath;
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relNodes = $rels->xpath('//r:Relationship[@Id="' . $relationshipId . '"]');
        if (!is_array($relNodes) || $relNodes === []) {
            return $defaultPath;
        }

        $target = (string) ($relNodes[0]['Target'] ?? '');
        if ($target === '') {
            return $defaultPath;
        }

        $target = ltrim($target, '/');
        if (!str_starts_with($target, 'worksheets/')) {
            return $defaultPath;
        }

        return 'xl/' . $target;
    }

    private static function extractSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        libxml_use_internal_errors(true);
        $shared = simplexml_load_string($xml);
        if ($shared === false) {
            return [];
        }

        $shared->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $shared->xpath('//x:si');
        if (!is_array($nodes)) {
            return [];
        }

        $values = [];
        foreach ($nodes as $node) {
            $textNodes = $node->xpath('.//x:t');
            if (is_array($textNodes) && $textNodes !== []) {
                $value = '';
                foreach ($textNodes as $textNode) {
                    $value .= (string) $textNode;
                }
                $values[] = $value;
            } else {
                $values[] = '';
            }
        }

        return $values;
    }

    private static function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
