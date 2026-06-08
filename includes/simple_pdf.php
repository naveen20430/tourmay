<?php
/**
 * Minimal text PDF generator (no external dependencies).
 */
class SimplePdfDocument {
    private array $pages = [];
    private array $lines = [];
    private float $y = 760;
    private float $margin = 48;
    private float $pageHeight = 792;
    private float $pageWidth = 612;

    public function addTitle(string $text): void {
        $this->addLine($text, 15, true);
        $this->y -= 6;
    }

    public function addSpacer(int $points = 10): void {
        $this->y -= $points;
        if ($this->y < $this->margin + 40) {
            $this->newPage();
        }
    }

    public function addLine(string $text, int $fontSize = 11, bool $bold = false): void {
        $wrapped = $this->wrapText($text, $fontSize);
        foreach ($wrapped as $line) {
            if ($this->y < $this->margin + 30) {
                $this->newPage();
            }
            $this->lines[] = [
                'text' => $this->escape($line),
                'size' => $fontSize,
                'bold' => $bold,
                'y' => $this->y,
            ];
            $this->y -= $fontSize + 6;
        }
    }

    public function newPage(): void {
        if (!empty($this->lines)) {
            $this->pages[] = $this->lines;
        }
        $this->lines = [];
        $this->y = 760;
    }

    private function wrapText(string $text, int $fontSize): array {
        $text = preg_replace('/\s+/', ' ', trim($text));
        $maxChars = $fontSize >= 14 ? 58 : ($fontSize >= 12 ? 72 : 88);
        if ($text === '') {
            return [''];
        }
        if (strlen($text) <= $maxChars) {
            return [$text];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($candidate) > $maxChars) {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function escape(string $text): string {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('/[^\x20-\x7E]/', '', (string) $text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    public function save(string $path): bool {
        $this->newPage();
        if (empty($this->pages)) {
            $this->addLine('Document');
            $this->newPage();
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $this->buildPdf()) !== false;
    }

    private function buildPdf(): string {
        $objects = [];

        $addObject = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };

        $fontRegular = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBold = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

        $pageObjectIds = [];
        foreach ($this->pages as $pageLines) {
            $stream = "BT\n";
            $currentFont = null;
            foreach ($pageLines as $line) {
                $fontKey = ($line['bold'] ? 'B' : 'R') . $line['size'];
                if ($currentFont !== $fontKey) {
                    $fontRef = $line['bold'] ? 'F2' : 'F1';
                    $stream .= "/{$fontRef} {$line['size']} Tf\n";
                    $currentFont = $fontKey;
                }
                $stream .= "1 0 0 1 {$this->margin} {$line['y']} Tm ({$line['text']}) Tj\n";
            }
            $stream .= 'ET';

            $contentId = $addObject('<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
            $pageObjectIds[] = [
                'id' => $addObject('<< /Type /Page /Parent __PARENT__ /MediaBox [0 0 ' . $this->pageWidth . ' ' . $this->pageHeight . '] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> >>'),
                'placeholder' => '__PARENT__',
            ];
        }

        $pagesId = $addObject('<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($page) => $page['id'] . ' 0 R', $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>');

        foreach ($pageObjectIds as $page) {
            $objects[$page['id'] - 1] = str_replace('__PARENT__', $pagesId . ' 0 R', $objects[$page['id'] - 1]);
        }

        $catalogId = $addObject('<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>');

        $header = "%PDF-1.4\n";
        $body = '';
        $offsets = [0 => 0];

        for ($i = 0; $i < count($objects); $i++) {
            $objectNumber = $i + 1;
            $offsets[$objectNumber] = strlen($header) + strlen($body);
            $body .= $objectNumber . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($header) + strlen($body);
        $pdf = $header . $body;
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer' . "\n";
        $pdf .= '<< /Size ' . (count($objects) + 1) . ' /Root ' . $catalogId . " 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
