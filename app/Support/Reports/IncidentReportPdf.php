<?php

namespace App\Support\Reports;

use App\Models\Incident;
use Illuminate\Support\Collection;

class IncidentReportPdf
{
    private const PAGE_WIDTH = 842;
    private const PAGE_HEIGHT = 595;

    /** @param Collection<int, Incident> $incidents */
    public function make(Collection $incidents, array $filters): string
    {
        $rows = $incidents->map(fn (Incident $incident): array => [
            $incident->code,
            $incident->occurred_at?->format('d/m/Y H:i') ?? '-',
            $incident->incidentType?->name ?? '-',
            $incident->forestArea?->name ?? '-',
            $incident->reporter?->name ?? 'Anonim',
            $incident->caseStatus?->name ?? '-',
        ])->all();

        $pages = $this->paginateRows($rows);

        return $this->buildDocument($pages === [] ? [[]] : $pages, $filters, $incidents->count());
    }

    private function buildDocument(array $pages, array $filters, int $reportCount): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageReferences = [];
        $nextObjectId = 3;
        $fontObjectId = 3 + (count($pages) * 2);

        foreach ($pages as $pageRows) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageReferences[] = $pageObjectId.' 0 R';
            $content = $this->pageContent($pageRows, $filters, $reportCount);
            $objects[$contentObjectId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontObjectId." 0 R >> >> /Contents ".$contentObjectId." 0 R >>";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pageReferences).' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $objectId => $object) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObjectId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObjectId + 1)."\n0000000000 65535 f \n";
        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId] ?? 0);
        }

        return $pdf."trailer\n<< /Size ".($maxObjectId + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";
    }

    private function pageContent(array $rows, array $filters, int $reportCount): string
    {
        $content = $this->fillRectangle(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [0.97, 0.98, 0.98]);
        $content .= $this->fillRectangle(0, 535, self::PAGE_WIDTH, 60, [0.06, 0.24, 0.13]);
        $content .= $this->text('FORESTGUARD', 36, 565, 20, [1, 1, 1]);
        $content .= $this->text('LAPORAN INSIDEN', 36, 545, 11, [0.84, 0.94, 0.87]);
        $content .= $this->text('Dicetak: '.now()->format('d/m/Y H:i'), 650, 565, 8, [0.9, 0.96, 0.92]);
        $content .= $this->fillRectangle(36, 470, 770, 42, [1, 1, 1]);
        $content .= $this->strokeRectangle(36, 470, 770, 42, [0.82, 0.86, 0.84]);
        $content .= $this->text('RINGKASAN', 50, 493, 9, [0.06, 0.24, 0.13]);
        $content .= $this->text('Jumlah laporan: '.$reportCount, 50, 478, 10, [0.15, 0.18, 0.17]);
        $content .= $this->text('Filter: '.$this->filterSummary($filters), 260, 478, 8, [0.28, 0.32, 0.3]);

        $columns = [['Kode', 85], ['Tanggal', 95], ['Jenis Insiden', 150], ['Kawasan Hutan', 180], ['Pelapor', 130], ['Status', 110]];
        $tableTop = 448;
        $headerHeight = 28;
        $x = 36;
        foreach ($columns as [$label, $width]) {
            $content .= $this->fillRectangle($x, $tableTop - $headerHeight, $width, $headerHeight, [0.09, 0.4, 0.23]);
            $content .= $this->strokeRectangle($x, $tableTop - $headerHeight, $width, $headerHeight, [0.75, 0.82, 0.78]);
            $content .= $this->text($label, $x + 7, $tableTop - 18, 8, [1, 1, 1]);
            $x += $width;
        }
        foreach ($rows as $rowIndex => $row) {
            $rowHeight = $this->rowHeight($row);
            $y = $tableTop - $headerHeight - $this->rowsHeight(array_slice($rows, 0, $rowIndex + 1));
            $x = 36;
            foreach ($columns as $columnIndex => [, $width]) {
                $background = $rowIndex % 2 === 0 ? [1, 1, 1] : [0.95, 0.97, 0.96];
                $content .= $this->fillRectangle($x, $y, $width, $rowHeight, $background);
                $content .= $this->strokeRectangle($x, $y, $width, $rowHeight, [0.84, 0.87, 0.85]);
                $lines = $this->wrapCell($row[$columnIndex], $columnIndex);
                foreach ($lines as $lineIndex => $line) {
                    $content .= $this->text(
                        $line,
                        $x + 7,
                        $y + $rowHeight - 12 - ($lineIndex * 9),
                        7.5,
                        [0.12, 0.15, 0.13],
                    );
                }
                $x += $width;
            }
        }
        if ($rows === []) {
            $rowHeight = 27;
            $y = $tableTop - $headerHeight - $rowHeight;
            $content .= $this->fillRectangle(36, $y, 750, $rowHeight, [1, 1, 1]);
            $content .= $this->strokeRectangle(36, $y, 750, $rowHeight, [0.84, 0.87, 0.85]);
            $content .= $this->text('Tidak ada laporan sesuai filter.', 50, $y + 10, 8, [0.35, 0.38, 0.36]);
        }

        return $content.$this->text('Dokumen ini dibuat secara otomatis oleh ForestGuard.', 36, 28, 7.5, [0.4, 0.44, 0.42]);
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, array<int, array<int, string>>>
     */
    private function paginateRows(array $rows): array
    {
        if ($rows === []) {
            return [[]];
        }

        $pages = [];
        $pageRows = [];
        $usedHeight = 0;
        $availableHeight = 390;

        foreach ($rows as $row) {
            $rowHeight = $this->rowHeight($row);
            if ($pageRows !== [] && $usedHeight + $rowHeight > $availableHeight) {
                $pages[] = $pageRows;
                $pageRows = [];
                $usedHeight = 0;
            }

            $pageRows[] = $row;
            $usedHeight += $rowHeight;
        }

        if ($pageRows !== []) {
            $pages[] = $pageRows;
        }

        return $pages;
    }

    /** @param array<int, string> $row */
    private function rowHeight(array $row): int
    {
        $lineCount = 1;
        foreach ($row as $columnIndex => $value) {
            $lineCount = max($lineCount, count($this->wrapCell($value, $columnIndex)));
        }

        return max(27, 10 + ($lineCount * 9));
    }

    /** @param array<int, array<int, string>> $rows */
    private function rowsHeight(array $rows): int
    {
        return array_sum(array_map(fn (array $row): int => $this->rowHeight($row), $rows));
    }

    /** @return array<int, string> */
    private function wrapCell(string $value, int $columnIndex): array
    {
        $limits = [15, 17, 26, 31, 22, 18];
        $wrapped = wordwrap($value, $limits[$columnIndex] ?? 20, "\n", true);

        return explode("\n", $wrapped);
    }

    private function filterSummary(array $filters): string
    {
        $values = array_filter([
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['status_label'] ?? $filters['status'] ?? null,
            $filters['incident_type_label'] ?? null,
            $filters['forest_area_label'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        return $values === [] ? 'Semua data' : implode(' | ', $values);
    }

    private function fillRectangle(float $x, float $y, float $width, float $height, array $color): string
    {
        return sprintf("q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f Q\n", ...array_merge($color, [$x, $y, $width, $height]));
    }

    private function strokeRectangle(float $x, float $y, float $width, float $height, array $color): string
    {
        return sprintf("%.3f %.3f %.3f RG %.2f %.2f %.2f %.2f re S\n", ...array_merge($color, [$x, $y, $width, $height]));
    }

    private function text(string $value, float $x, float $y, float $size, array $color): string
    {
        return sprintf("%.3f %.3f %.3f rg BT /F1 %.1f Tf %.2f %.2f Td (%s) Tj ET\n", ...array_merge($color, [$size, $x, $y, $this->escapeText($value)]));
    }

    private function escapeText(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}
