<?php

namespace modlab\craftheadinganchors\services;

use craft\errors\InvalidHtmlTagException;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use modlab\craftheadinganchors\HeadingAnchors;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Finds headings in rendered HTML, gives them anchor ids, and lists them.
 *
 * The HTML is scanned rather than re-serialized through a DOM, so everything
 * outside the edited heading tags (Alpine attributes, SVG, embed code) comes
 * back exactly as it went in. Comments and the contents of raw text elements
 * (`<script>`, `<style>`, `<textarea>`, `<title>`) are never treated as markup,
 * so a pasted embed script containing `"<h2>"` is left alone.
 */
class Anchors extends Component
{
    /**
     * Attribute added to every heading the plugin anchors. Used by `headings()`
     * and available to front-end code (e.g. a scroll spy).
     */
    public const MARKER_ATTRIBUTE = 'data-heading-anchor';

    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'textarea', 'title'];

    /**
     * Adds a unique `id` and the marker attribute to each configured heading
     * that has text. Headings that already have an `id` keep it.
     */
    public function anchor(string $html): string
    {
        $usedIds = $this->existingIds($html);
        $output = '';
        $copiedTo = 0;

        foreach ($this->findHeadings($html) as $heading) {
            $insert = '';

            if ($heading['id'] === null) {
                $heading['id'] = $this->uniqueId($heading['text'], $usedIds);
                $insert .= ' id="' . Html::encode($heading['id']) . '"';
            }

            if (!$heading['marked']) {
                $insert .= ' ' . self::MARKER_ATTRIBUTE;
            }

            if ($insert === '') {
                continue;
            }

            // Insert right after the tag name, leaving the rest of the tag untouched
            $output .= substr($html, $copiedTo, $heading['nameEnd'] - $copiedTo) . $insert;
            $copiedTo = $heading['nameEnd'];
        }

        return $output . substr($html, $copiedTo);
    }

    /**
     * Returns the anchored headings in the HTML, in document order.
     *
     * @return array<int,array{id:string,text:string}> Text is plain (not HTML-encoded).
     */
    public function headings(string $html): array
    {
        $headings = [];

        foreach ($this->findHeadings($html) as $heading) {
            if ($heading['marked'] && $heading['id'] !== null) {
                $headings[] = ['id' => $heading['id'], 'text' => $heading['text']];
            }
        }

        return $headings;
    }

    /**
     * Scans the HTML for configured headings that have text.
     *
     * @return array<int,array{id:?string,text:string,marked:bool,nameEnd:int}>
     */
    private function findHeadings(string $html): array
    {
        $settings = HeadingAnchors::getInstance()->getSettings();
        $tags = $settings->getTags();
        $ignoreAttribute = strtolower($settings->ignoreAttribute);
        $headings = [];
        $length = strlen($html);
        $pos = 0;

        while (($lt = strpos($html, '<', $pos)) !== false) {
            // Comments
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $commentEnd = strpos($html, '-->', $lt + 4);
                $pos = $commentEnd === false ? $length : $commentEnd + 3;
                continue;
            }

            // Only opening tags matter; closing tags and stray `<` are skipped
            if (!preg_match('/<([a-zA-Z][\w\-]*)/A', $html, $match, 0, $lt)) {
                $pos = $lt + 1;
                continue;
            }

            $name = strtolower($match[1]);
            $nameEnd = $lt + strlen($match[0]);
            $tag = $this->readOpeningTag($html, $nameEnd);

            if ($tag === null) {
                $pos = $lt + 1;
                continue;
            }

            [$attributes, $tagEnd] = $tag;

            if (in_array($name, self::RAW_TEXT_ELEMENTS, true)) {
                $close = stripos($html, "</$name", $tagEnd);
                $pos = $close === false ? $length : $close;
                continue;
            }

            if (array_key_exists($ignoreAttribute, $attributes)) {
                $pos = $this->elementEnd($html, $lt) ?? $tagEnd;
                continue;
            }

            if (in_array($name, $tags, true)) {
                $close = stripos($html, "</$name", $tagEnd);

                if ($close !== false) {
                    $text = $this->headingText(substr($html, $tagEnd, $close - $tagEnd));

                    if ($text !== '') {
                        $id = $attributes['id'] ?? null;
                        $headings[] = [
                            'id' => is_string($id) && $id !== '' ? $id : null,
                            'text' => $text,
                            'marked' => array_key_exists(self::MARKER_ATTRIBUTE, $attributes),
                            'nameEnd' => $nameEnd,
                        ];
                    }

                    // Headings can't contain headings, so skip to the closing tag
                    $pos = $close;
                    continue;
                }
            }

            $pos = $tagEnd;
        }

        return $headings;
    }

    /**
     * Reads the attributes of an opening tag, starting just after its name.
     *
     * @return array{0:array<string,string|true>,1:int}|null Lowercased attribute names => raw values, and the offset after `>`.
     */
    private function readOpeningTag(string $html, int $offset): ?array
    {
        $attributes = [];

        try {
            while (($attribute = Html::parseTagAttribute($html, $offset, $start, $end)) !== null) {
                [$name, $value] = $attribute;
                $attributes[strtolower($name)] = $value;
                $offset = $end;
            }
        } catch (InvalidArgumentException) {
            return null;
        }

        if (!preg_match('/\s*\/?>/A', $html, $match, 0, $offset)) {
            return null;
        }

        return [$attributes, $offset + strlen($match[0])];
    }

    /**
     * Returns the offset after the element that starts at `$offset`, or null if
     * the markup is too malformed to tell.
     */
    private function elementEnd(string $html, int $offset): ?int
    {
        try {
            return Html::parseTag($html, $offset)['end'];
        } catch (InvalidHtmlTagException) {
            return null;
        }
    }

    private function headingText(string $innerHtml): string
    {
        $text = html_entity_decode(strip_tags($innerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param array<string,true> $usedIds Updated with the returned id.
     */
    private function uniqueId(string $text, array &$usedIds): string
    {
        $slug = StringHelper::toKebabCase(str_replace('&', ' and ', $text)) ?: 'section';
        $id = $slug;

        for ($n = 2; isset($usedIds[$id]); $n++) {
            $id = "$slug-$n";
        }

        $usedIds[$id] = true;

        return $id;
    }

    /**
     * Collects every `id` value already in the HTML, so new ids never collide.
     * Deliberately broad (it also matches inside scripts); a false positive only
     * means a `-2` suffix.
     *
     * @return array<string,true>
     */
    private function existingIds(string $html): array
    {
        preg_match_all('/\sid\s*=\s*(["\']?)([^"\'\s>]+)\1/i', $html, $matches);

        return array_fill_keys($matches[2], true);
    }
}
