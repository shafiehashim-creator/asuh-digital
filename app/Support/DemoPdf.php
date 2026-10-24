<?php

namespace App\Support;

/**
 * Builds a small, valid multi-page PDF without any library, for demo books
 * in local development. Not used in production.
 */
class DemoPdf
{
    public static function make(string $title, int $pages = 8): string
    {
        $objects = [];
        $kids = [];

        // 1: catalog, 2: pages tree, 3: font. Pages start at 4 (page + content each).
        $pageObjects = [];
        for ($i = 1; $i <= $pages; $i++) {
            $pageId = 2 + $i * 2;
            $contentId = $pageId + 1;
            $kids[] = "{$pageId} 0 R";

            $hue = [[0.23, 0.12, 0.43], [0.77, 0.06, 0.48], [0.08, 0.35, 0.84], [0.97, 0.58, 0.13]][$i % 4];
            $text = self::escape($i === 1 ? $title : "Halaman {$i}");
            $sub = self::escape($i === 1 ? 'Contoh e-book ASUH Digital' : 'Jom Jadi Baik!');
            $stream = sprintf(
                "%.2F %.2F %.2F rg 0 0 420 595 re f\n1 1 1 rg 30 180 360 260 re f\n0.14 0.12 0.13 rg\nBT /F1 26 Tf 50 360 Td (%s) Tj ET\nBT /F1 14 Tf 50 320 Td (%s) Tj ET\nBT /F1 11 Tf 50 200 Td (%d / %d) Tj ET\n",
                $hue[0], $hue[1], $hue[2], $text, $sub, $i, $pages,
            );

            $pageObjects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 595] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $pageObjects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$pages} >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects += $pageObjects;
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        // Helvetica in a basic PDF has no glyphs for curly quotes or dashes.
        $ascii = strtr($text, ['–' => '-', '—' => '-', '‘' => "'", '’' => "'"]);

        return addcslashes($ascii, '()\\');
    }
}
