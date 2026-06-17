<?php

function sanitizeRichTextHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><a><div>';
    $html = strip_tags($html, $allowed);

    $html = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);

    $html = preg_replace_callback('/\sstyle\s*=\s*("|\')([^"\']*)(\1)/i', static function (array $matches): string {
        $styles = [];
        if (preg_match('/font-size\s*:\s*[^;"\']+/i', $matches[2], $fontSize)) {
            $styles[] = trim($fontSize[0]);
        }
        if (preg_match('/font-weight\s*:\s*(bold|[5-9]00)/i', $matches[2], $fontWeight)) {
            $styles[] = trim($fontWeight[0]);
        }
        if (empty($styles)) {
            return '';
        }

        return ' style="' . htmlspecialchars(implode('; ', $styles), ENT_QUOTES, 'UTF-8') . '"';
    }, $html);

    return trim($html);
}

function richTextHasContent(string $html): bool
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\xc2\xa0/u', ' ', $text ?? '');

    return $text !== null && trim($text) !== '';
}

function formatTourDescriptionForDisplay(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (strip_tags($text) === $text) {
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    return sanitizeRichTextHtml($text);
}
