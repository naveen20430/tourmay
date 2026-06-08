<?php
/**
 * Branded invoice PDF builder — purple gradient theme matching invoice.php
 */
class InvoicePdfBuilder {
    private const PAGE_W = 612;
    private const PAGE_H = 792;
    private const MARGIN = 36;

    private array $pages = [];
    private array $pageOps = [];
    private array $images = [];
    private float $y = 0;
    private int $imageCounter = 0;

    private float $primaryR = 0.4;
    private float $primaryG = 0.494;
    private float $primaryB = 0.918;
    private float $secondaryR = 0.463;
    private float $secondaryG = 0.294;
    private float $secondaryB = 0.635;

    public function __construct() {
        $this->newPage();
        $this->y = self::PAGE_H - self::MARGIN;
    }

    public function newPage(): void {
        if (!empty($this->pageOps)) {
            $this->pages[] = $this->pageOps;
        }
        $this->pageOps = [];
        $this->y = self::PAGE_H - self::MARGIN;
    }

    private function ensureSpace(float $needed): void {
        if ($this->y - $needed < self::MARGIN + 24) {
            $this->newPage();
        }
    }

    private function money(float $amount): string {
        return 'INR ' . number_format($amount, 0);
    }

    private function rightAlignX(string $text, int $size, float $rightEdge): float {
        return $rightEdge - (strlen($text) * $size * 0.48);
    }

    private function sanitize(string $text): string {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('/[^\x20-\x7E]/', '', (string) $text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function wrap(string $text, int $maxChars): array {
        $text = preg_replace('/\s+/', ' ', trim($text));
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

    public function fillRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void {
        $this->pageOps[] = [
            'type' => 'rect',
            'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'r' => $r, 'g' => $g, 'b' => $b,
        ];
    }

    public function drawLine(float $x1, float $y1, float $x2, float $y2, float $r, float $g, float $b, float $width = 0.5): void {
        $this->pageOps[] = [
            'type' => 'line',
            'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2,
            'r' => $r, 'g' => $g, 'b' => $b, 'width' => $width,
        ];
    }

    public function text(float $x, float $y, string $text, int $size = 10, bool $bold = false, float $r = 0.12, float $g = 0.16, float $b = 0.22): void {
        $this->pageOps[] = [
            'type' => 'text',
            'x' => $x, 'y' => $y,
            'text' => $this->sanitize($text),
            'size' => $size, 'bold' => $bold,
            'r' => $r, 'g' => $g, 'b' => $b,
        ];
    }

    public function textBlock(float $x, float &$y, string $text, int $size = 10, bool $bold = false, float $r = 0.12, float $g = 0.16, float $b = 0.22, int $maxChars = 80, float $lineGap = 4): void {
        foreach ($this->wrap($text, $maxChars) as $line) {
            $this->text($x, $y, $line, $size, $bold, $r, $g, $b);
            $y -= $size + $lineGap;
        }
    }

    public function embedImage(string $path, float $x, float $y, float $w, float $h): void {
        if (!is_file($path)) {
            return;
        }

        $jpegData = $this->imageToJpegBytes($path);
        if ($jpegData === null) {
            return;
        }

        $this->imageCounter++;
        $name = 'Im' . $this->imageCounter;
        $info = @getimagesizefromstring($jpegData);
        $imgW = $info[0] ?? 100;
        $imgH = $info[1] ?? 40;

        $this->images[$name] = [
            'width' => $imgW,
            'height' => $imgH,
            'data' => $jpegData,
        ];

        $this->pageOps[] = [
            'type' => 'image',
            'name' => $name,
            'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
        ];
    }

    private function imageToJpegBytes(string $path): ?string {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $data = @file_get_contents($path);
            return $data !== false ? $data : null;
        }

        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($src);
        imagedestroy($canvas);

        return $jpeg !== false ? $jpeg : null;
    }

    public function drawHeader(string $siteName, string $tagline, string $address, string $invoiceNumber, string $issuedDate, string $status, ?string $logoPath): void {
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $headerH = 96;
        $headerY = self::PAGE_H - $headerH;

        $this->fillRect(0, $headerY, self::PAGE_W, $headerH, $this->secondaryR, $this->secondaryG, $this->secondaryB);
        $this->fillRect(0, $headerY, self::PAGE_W * 0.55, $headerH, $this->primaryR, $this->primaryG, $this->primaryB);

        if ($logoPath && is_file($logoPath)) {
            $this->fillRect($left, $headerY + 18, 88, 60, 1, 1, 1);
            $this->embedImage($logoPath, $left + 8, $headerY + 24, 72, 48);
        }

        $textX = $logoPath && is_file($logoPath) ? $left + 100 : $left + 8;
        $this->text($textX, $headerY + 58, $siteName, 16, true, 1, 1, 1);
        $this->text($textX, $headerY + 40, $tagline, 9, false, 0.95, 0.95, 1);
        if ($address !== '') {
            $addrY = $headerY + 26;
            foreach ($this->wrap($address, 42) as $i => $line) {
                $this->text($textX, $addrY - ($i * 11), $line, 8, false, 0.9, 0.9, 1);
            }
        }

        $this->text($this->rightAlignX('TAX INVOICE', 8, $right - 8), $headerY + 78, 'TAX INVOICE', 8, true, 1, 1, 1);
        $this->text($this->rightAlignX($invoiceNumber, 13, $right - 8), $headerY + 60, $invoiceNumber, 13, true, 1, 1, 1);
        $issued = 'Issued: ' . $issuedDate;
        $this->text($this->rightAlignX($issued, 9, $right - 8), $headerY + 44, $issued, 9, false, 0.92, 0.92, 1);

        $statusColor = match (strtolower($status)) {
            'paid' => [0.09, 0.55, 0.33],
            'failed' => [0.6, 0.13, 0.13],
            default => [0.72, 0.45, 0.09],
        };
        $badgeW = max(52, strlen($status) * 5.5 + 16);
        $badgeX = $right - $badgeW;
        $this->fillRect($badgeX, $headerY + 18, $badgeW, 18, $statusColor[0], $statusColor[1], $statusColor[2]);
        $this->text($badgeX + $badgeW / 2 - (strlen($status) * 2.8), $headerY + 26, strtoupper($status), 7, true, 1, 1, 1);

        $this->y = $headerY - 20;
    }

    public function drawMetaCards(array $cards): void {
        $this->ensureSpace(72);
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $gap = 10;
        $cardW = ($right - $left - (2 * $gap)) / 3;
        $cardH = 64;
        $cardY = $this->y - $cardH;

        foreach ($cards as $i => $card) {
            $x = $left + ($i * ($cardW + $gap));
            $this->fillRect($x, $cardY, $cardW, $cardH, 0.97, 0.98, 1);
            $this->fillRect($x, $cardY, 3, $cardH, $this->primaryR, $this->primaryG, $this->primaryB);
            $this->drawLine($x, $cardY, $x + $cardW, $cardY, 0.88, 0.9, 0.95, 0.5);
            $this->drawLine($x, $cardY + $cardH, $x + $cardW, $cardY + $cardH, 0.88, 0.9, 0.95, 0.5);
            $this->text($x + 12, $cardY + $cardH - 14, strtoupper($card['label']), 7, true, $this->primaryR, $this->primaryG, $this->primaryB);
            $this->text($x + 12, $cardY + $cardH - 30, $card['title'], 10, true);
            $subY = $cardY + $cardH - 44;
            foreach (array_slice($card['lines'] ?? [], 0, 2) as $line) {
                $this->text($x + 12, $subY, $line, 8, false, 0.45, 0.5, 0.58);
                $subY -= 11;
            }
        }

        $this->y = $cardY - 18;
    }

    public function drawAlert(string $text, string $type = 'info'): void {
        $this->ensureSpace(36);
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $w = $right - $left;

        if ($type === 'warning') {
            $bg = [1, 0.97, 0.93];
            $border = [0.98, 0.73, 0.45];
        } else {
            $bg = [0.93, 0.95, 1];
            $border = [0.65, 0.71, 0.98];
        }

        $lines = $this->wrap($text, 95);
        $h = 16 + (count($lines) * 12);
        $boxY = $this->y - $h;

        $this->fillRect($left, $boxY, $w, $h, $bg[0], $bg[1], $bg[2]);
        $this->drawLine($left, $boxY, $left + $w, $boxY, $border[0], $border[1], $border[2], 1);
        $this->drawLine($left, $boxY + $h, $left + $w, $boxY + $h, $border[0], $border[1], $border[2], 1);

        $lineY = $boxY + $h - 14;
        foreach ($lines as $line) {
            $this->text($left + 10, $lineY, $line, 8, false, 0.25, 0.28, 0.35);
            $lineY -= 12;
        }

        $this->y = $boxY - 14;
    }

    public function drawTable(array $headers, array $rows, array $widths): void {
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $rowH = 22;
        $headerH = 24;

        $this->ensureSpace($headerH + $rowH);
        $tableTop = $this->y;
        $tableW = $right - $left;

        $this->fillRect($left, $tableTop - $headerH, $tableW, $headerH, $this->secondaryR, $this->secondaryG, $this->secondaryB);
        $this->fillRect($left, $tableTop - $headerH, $tableW * 0.7, $headerH, $this->primaryR, $this->primaryG, $this->primaryB);

        $colX = $left + 8;
        foreach ($headers as $i => $header) {
            $this->text($colX, $tableTop - $headerH + 9, $header, 7, true, 1, 1, 1);
            $colX += $widths[$i];
        }

        $currentY = $tableTop - $headerH;
        foreach ($rows as $rowIndex => $row) {
            $cells = $row['cells'];
            $sub = $row['sub'] ?? '';
            $neededH = $sub !== '' ? 34 : $rowH;

            if ($currentY - $neededH < self::MARGIN + 30) {
                $this->newPage();
                $currentY = $this->y;
                $this->fillRect($left, $currentY - $headerH, $tableW, $headerH, $this->primaryR, $this->primaryG, $this->primaryB);
                $colX = $left + 8;
                foreach ($headers as $i => $header) {
                    $this->text($colX, $currentY - $headerH + 9, $header, 7, true, 1, 1, 1);
                    $colX += $widths[$i];
                }
                $currentY -= $headerH;
            }

            if ($rowIndex % 2 === 0) {
                $this->fillRect($left, $currentY - $neededH, $tableW, $neededH, 0.98, 0.99, 1);
            }

            $this->drawLine($left, $currentY - $neededH, $left + $tableW, $currentY - $neededH, 0.9, 0.92, 0.95, 0.5);

            $colX = $left + 8;
            foreach ($cells as $i => $cell) {
                $bold = $i === 0;
                $color = $i === count($cells) - 1
                    ? [$this->secondaryR, $this->secondaryG, $this->secondaryB]
                    : [0.2, 0.25, 0.33];
                $this->text($colX, $currentY - 14, (string) $cell, $bold ? 9 : 8, $bold, $color[0], $color[1], $color[2]);
                $colX += $widths[$i];
            }

            if ($sub !== '') {
                $this->text($left + 8, $currentY - 28, $sub, 7, false, 0.45, 0.5, 0.58);
            }

            $currentY -= $neededH;
        }

        $this->drawLine($left, $currentY, $left + $tableW, $currentY, 0.88, 0.9, 0.95, 0.8);
        $this->y = $currentY - 16;
    }

    public function drawTotals(float $subtotal, float $cabTotal, float $total): void {
        $this->ensureSpace(90);
        $boxW = 220;
        $boxH = 78 + ($cabTotal > 0 ? 14 : 0);
        $boxX = self::PAGE_W - self::MARGIN - $boxW;
        $boxY = $this->y - $boxH;

        $this->fillRect($boxX, $boxY + $boxH - 22, $boxW, 22, $this->primaryR, $this->primaryG, $this->primaryB);
        $this->text($boxX + 12, $boxY + $boxH - 10, 'PAYMENT SUMMARY', 8, true, 1, 1, 1);
        $this->fillRect($boxX, $boxY, $boxW, $boxH - 22, 1, 1, 1);
        $this->drawLine($boxX, $boxY, $boxX + $boxW, $boxY, 0.88, 0.9, 0.95, 0.8);
        $this->drawLine($boxX + $boxW, $boxY, $boxX + $boxW, $boxY + $boxH, 0.88, 0.9, 0.95, 0.8);
        $this->drawLine($boxX, $boxY, $boxX, $boxY + $boxH, 0.88, 0.9, 0.95, 0.8);

        $lineY = $boxY + $boxH - 38;
        $this->text($boxX + 12, $lineY, 'Subtotal', 8, false, 0.45, 0.5, 0.58);
        $subtotalText = $this->money($subtotal);
        $this->text($this->rightAlignX($subtotalText, 8, $boxX + $boxW - 12), $lineY, $subtotalText, 8, false, 0.45, 0.5, 0.58);

        if ($cabTotal > 0) {
            $lineY -= 14;
            $cabText = $this->money($cabTotal);
            $this->text($boxX + 12, $lineY, 'Cab charges', 8, false, 0.45, 0.5, 0.58);
            $this->text($this->rightAlignX($cabText, 8, $boxX + $boxW - 12), $lineY, $cabText, 8, false, 0.45, 0.5, 0.58);
        }

        $lineY -= 16;
        $this->drawLine($boxX + 10, $lineY + 6, $boxX + $boxW - 10, $lineY + 6, 0.75, 0.78, 0.95, 0.6);
        $totalText = $this->money($total);
        $this->text($boxX + 12, $lineY - 4, 'Total Payable', 10, true);
        $this->text($this->rightAlignX($totalText, 10, $boxX + $boxW - 12), $lineY - 4, $totalText, 10, true, $this->secondaryR, $this->secondaryG, $this->secondaryB);

        $this->y = $boxY - 20;
    }

    public function drawSectionTitle(string $title): void {
        $this->ensureSpace(28);
        $this->text(self::MARGIN, $this->y, $title, 13, true, $this->secondaryR, $this->secondaryG, $this->secondaryB);
        $this->drawLine(self::MARGIN, $this->y - 8, self::PAGE_W - self::MARGIN, $this->y - 8, $this->primaryR, $this->primaryG, $this->primaryB, 1.2);
        $this->y -= 24;
    }

    public function drawItineraryTour(string $title, string $description, array $days): void {
        $this->ensureSpace(50);
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $w = $right - $left;

        $descLines = $description !== '' ? $this->wrap($description, 90) : [];
        $dayBlocks = max(1, count($days));
        $estH = 36 + (count($descLines) * 11) + ($dayBlocks * 34);
        if ($this->y - $estH < self::MARGIN + 20) {
            $this->newPage();
        }

        $boxY = $this->y - $estH + 10;
        $this->fillRect($left, $boxY, $w, $estH, 0.98, 0.99, 1);
        $this->drawLine($left, $boxY, $left + $w, $boxY, 0.88, 0.9, 0.95, 0.8);
        $this->drawLine($left, $boxY + $estH, $left + $w, $boxY + $estH, 0.88, 0.9, 0.95, 0.8);

        $contentY = $boxY + $estH - 16;
        $this->text($left + 12, $contentY, $title, 11, true, $this->secondaryR, $this->secondaryG, $this->secondaryB);
        $contentY -= 16;

        foreach ($descLines as $line) {
            $this->text($left + 12, $contentY, $line, 8, false, 0.45, 0.5, 0.58);
            $contentY -= 11;
        }

        if (empty($days)) {
            $this->text($left + 12, $contentY, 'Itinerary details will be shared before departure.', 8, false, 0.45, 0.5, 0.58);
        } else {
            foreach ($days as $day) {
                if ($contentY < $boxY + 30) {
                    break;
                }
                $dayNo = (string) ($day['day'] ?? '');
                $dayTitle = (string) ($day['title'] ?? 'Schedule');
                $dayDesc = (string) ($day['description'] ?? '');

                $this->fillRect($left + 12, $contentY - 24, 52, 28, $this->primaryR, $this->primaryG, $this->primaryB);
                $this->text($left + 20, $contentY - 8, 'DAY', 6, true, 1, 1, 1);
                $this->text($left + 28, $contentY - 18, $dayNo, 11, true, 1, 1, 1);

                $this->text($left + 74, $contentY - 8, $dayTitle, 9, true);
                $descY = $contentY - 20;
                foreach (array_slice($this->wrap($dayDesc, 72), 0, 2) as $line) {
                    $this->text($left + 74, $descY, $line, 7, false, 0.45, 0.5, 0.58);
                    $descY -= 10;
                }

                $contentY -= 36;
                $this->drawLine($left + 12, $contentY + 8, $left + $w - 12, $contentY + 8, 0.9, 0.92, 0.96, 0.4);
            }
        }

        $this->y = $boxY - 14;
    }

    public function drawFooter(string $text): void {
        $this->ensureSpace(30);
        $left = self::MARGIN;
        $right = self::PAGE_W - self::MARGIN;
        $this->drawLine($left, $this->y, $right, $this->y, 0.88, 0.9, 0.95, 0.8);
        $this->y -= 14;
        foreach ($this->wrap($text, 100) as $line) {
            $this->text(self::PAGE_W / 2 - (strlen($line) * 2.2), $this->y, $line, 8, false, 0.45, 0.5, 0.58);
            $this->y -= 11;
        }
    }

    public function save(string $path): bool {
        if (!empty($this->pageOps)) {
            $this->pages[] = $this->pageOps;
            $this->pageOps = [];
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $this->buildPdf()) !== false;
    }

    private function buildPageStream(array $ops): string {
        $stream = '';

        foreach ($ops as $op) {
            if ($op['type'] === 'rect') {
                $stream .= sprintf(
                    "q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f Q\n",
                    $op['r'], $op['g'], $op['b'],
                    $op['x'], $op['y'], $op['w'], $op['h']
                );
            } elseif ($op['type'] === 'line') {
                $stream .= sprintf(
                    "q %.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S Q\n",
                    $op['r'], $op['g'], $op['b'], $op['width'],
                    $op['x1'], $op['y1'], $op['x2'], $op['y2']
                );
            } elseif ($op['type'] === 'image') {
                $stream .= sprintf(
                    "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n",
                    $op['w'], $op['h'], $op['x'], $op['y'], $op['name']
                );
            } elseif ($op['type'] === 'text') {
                $font = $op['bold'] ? 'F2' : 'F1';
                $stream .= "BT\n";
                $stream .= '/' . $font . ' ' . $op['size'] . " Tf\n";
                $stream .= sprintf('%.3f %.3f %.3f rg ', $op['r'], $op['g'], $op['b']);
                $stream .= sprintf('1 0 0 1 %.2f %.2f Tm ', $op['x'], $op['y']);
                $stream .= '(' . $op['text'] . ") Tj\nET\n";
            }
        }

        return $stream;
    }

    private function buildPdf(): string {
        $objects = [];
        $addObject = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };

        $fontRegular = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBold = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

        $imageObjectIds = [];
        foreach ($this->images as $name => $img) {
            $imageObjectIds[$name] = $addObject(
                '<< /Type /XObject /Subtype /Image /Width ' . $img['width'] .
                ' /Height ' . $img['height'] .
                " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) .
                " >>\nstream\n" . $img['data'] . "\nendstream"
            );
        }

        $pageObjectIds = [];
        foreach ($this->pages as $pageOps) {
            $stream = $this->buildPageStream($pageOps);
            $contentId = $addObject('<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");

            $xObjects = '';
            if (!empty($imageObjectIds)) {
                $parts = [];
                foreach ($imageObjectIds as $name => $id) {
                    $parts[] = '/' . $name . ' ' . $id . ' 0 R';
                }
                $xObjects = ' /XObject << ' . implode(' ', $parts) . ' >>';
            }

            $pageObjectIds[] = $addObject(
                '<< /Type /Page /Parent __PARENT__ /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H .
                '] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >>' .
                $xObjects . ' >> >>'
            );
        }

        $pagesId = $addObject('<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>');
        foreach ($pageObjectIds as $idx => $pageId) {
            $objects[$pageId - 1] = str_replace('__PARENT__', $pagesId . ' 0 R', $objects[$pageId - 1]);
        }

        $catalogId = $addObject('<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>');

        $header = "%PDF-1.4\n";
        $body = '';
        $offsets = [0 => 0];
        for ($i = 0; $i < count($objects); $i++) {
            $n = $i + 1;
            $offsets[$n] = strlen($header) + strlen($body);
            $body .= $n . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($header) + strlen($body);
        $pdf = $header . $body;
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . ' /Root ' . $catalogId . " 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
