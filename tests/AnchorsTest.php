<?php

namespace modlab\craftheadinganchors\tests;

use modlab\craftheadinganchors\models\Settings;
use modlab\craftheadinganchors\services\Anchors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnchorsTest extends TestCase
{
    private static string $anchored;

    public static function setUpBeforeClass(): void
    {
        self::$anchored = self::anchors()->anchor(self::fixture());
    }

    private static function anchors(array $settings = []): Anchors
    {
        return new Anchors(['settings' => new Settings($settings)]);
    }

    private static function fixture(): string
    {
        return (string)file_get_contents(__DIR__ . '/fixtures/content.html');
    }

    /**
     * @return array<string,array{string}>
     */
    public static function expectedMarkup(): array
    {
        return [
            'first repeat' => ['<h2 id="overview" data-heading-anchor>Overview</h2>'],
            'second repeat gets -2' => ['<h2 id="overview-2" data-heading-anchor>Overview</h2>'],
            'existing id kept, only marked' => ['<h2 data-heading-anchor id="custom-id">Kept Id</h2>'],
            'uppercase tag, attributes untouched' => ['<H2 id="upper-case-tag" data-heading-anchor class="text-4xl font-bold">Upper Case Tag</H2>'],
            '> inside attribute value' => ['<h2 id="attribute-with-greater-than" data-heading-anchor class="a" data-x="1 > 0">'],
            '& becomes "and"' => ['<h2 id="q-and-a" data-heading-anchor>Q &amp; A</h2>'],
            'nbsp treated as space' => ['<h2 id="main-content" data-heading-anchor>Main&nbsp;Content</h2>'],
            'accents kept' => ['<h2 id="café-résumé" data-heading-anchor>Café résumé</h2>'],
            'nested markup text' => ['<h2 id="nested-markup" data-heading-anchor><strong>Nested</strong> markup</h2>'],
            'whitespace-only heading untouched' => ['<h2>   </h2>'],
            'nbsp-only heading untouched' => ['<h2>&nbsp;</h2>'],
            'unconfigured tag untouched' => ['<h3>Not a configured tag</h3>'],
            'Alpine attributes byte-for-byte' => ['<div x-data="{ open: false }" @click="open = !open" :class="open ? \'a\' : \'b\'" x-on:keydown.escape="open = false">'],
            'heading inside Alpine element' => ['<h2 id="inside-alpine" data-heading-anchor>Inside Alpine</h2>'],
            'script untouched' => ['"<h2>From Script</h2>"'],
            'style untouched' => ['content: "<h2>";'],
            'comment untouched' => ['<!-- <h2>Commented Out</h2> -->'],
            'ignored element untouched' => ['<h2>Ignored Embed Heading</h2>'],
            'nested inside ignored element untouched' => ['<div><h2>Nested Ignored</h2></div>'],
            'heading after ignored element' => ['<h2 id="after-ignore" data-heading-anchor>After Ignore</h2>'],
            'svg untouched' => ['<svg viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" /></svg>'],
        ];
    }

    #[DataProvider('expectedMarkup')]
    public function testAnchorOutput(string $expected): void
    {
        $this->assertStringContainsString($expected, self::$anchored);
    }

    public function testNewIdsDontCollideWithExistingIds(): void
    {
        $html = '<main id="maincontent"><h2>Maincontent</h2></main>';
        $this->assertSame('<main id="maincontent"><h2 id="maincontent-2" data-heading-anchor>Maincontent</h2></main>', self::anchors()->anchor($html));
    }

    public function testOnlyHeadingTagsChange(): void
    {
        $strip = fn(string $html) => (string)preg_replace('/ id="[^"]*" data-heading-anchor(?=[\s>])| data-heading-anchor(?=[\s>])/', '', $html);
        $this->assertSame(self::fixture(), $strip(self::$anchored));
    }

    public function testAnchoringIsIdempotent(): void
    {
        $this->assertSame(self::$anchored, self::anchors()->anchor(self::$anchored));
    }

    public function testHeadingsListsAnchoredHeadingsInOrder(): void
    {
        $this->assertSame([
            ['id' => 'overview', 'text' => 'Overview'],
            ['id' => 'overview-2', 'text' => 'Overview'],
            ['id' => 'custom-id', 'text' => 'Kept Id'],
            ['id' => 'upper-case-tag', 'text' => 'Upper Case Tag'],
            ['id' => 'attribute-with-greater-than', 'text' => 'Attribute With Greater Than'],
            ['id' => 'q-and-a', 'text' => 'Q & A'],
            ['id' => 'main-content', 'text' => 'Main Content'],
            ['id' => 'café-résumé', 'text' => 'Café résumé'],
            ['id' => 'nested-markup', 'text' => 'Nested markup'],
            ['id' => 'inside-alpine', 'text' => 'Inside Alpine'],
            ['id' => 'after-ignore', 'text' => 'After Ignore'],
        ], self::anchors()->headings(self::$anchored));
    }

    public function testHeadingsIgnoresUnanchoredHtml(): void
    {
        $this->assertSame([], self::anchors()->headings(self::fixture()));
    }

    public function testConfiguredTags(): void
    {
        $this->assertSame(
            '<h2 id="section" data-heading-anchor>Section</h2><h3 id="sub" data-heading-anchor>Sub</h3><h4>No</h4>',
            self::anchors(['tags' => ['h2', 'H3']])->anchor('<h2>Section</h2><h3>Sub</h3><h4>No</h4>'),
        );
    }

    public function testCustomIgnoreAttribute(): void
    {
        $html = '<div data-skip><h2>Skipped</h2></div><h2>Kept</h2>';
        $this->assertSame(
            '<div data-skip><h2>Skipped</h2></div><h2 id="kept" data-heading-anchor>Kept</h2>',
            self::anchors(['ignoreAttribute' => 'data-skip'])->anchor($html),
        );
    }

    public function testIgnoredElementWithUnclosedChildIsStillSkipped(): void
    {
        $html = '<div data-heading-anchors-ignore><p>unclosed<h2>Inside</h2></div><h2>After</h2>';
        $this->assertSame(
            '<div data-heading-anchors-ignore><p>unclosed<h2>Inside</h2></div><h2 id="after" data-heading-anchor>After</h2>',
            self::anchors()->anchor($html),
        );
    }

    public function testUnclosedIgnoredElementFallsBackToAnchoring(): void
    {
        // Without its own closing tag the ignored element's end can't be found, so its headings are anchored
        $html = '<div data-heading-anchors-ignore><h2>Inside</h2>';
        $this->assertSame('<div data-heading-anchors-ignore><h2 id="inside" data-heading-anchor>Inside</h2>', self::anchors()->anchor($html));
    }

    public function testSettingsRejectNonHeadingTags(): void
    {
        $settings = new Settings(['tags' => ['h2', 'div']]);
        $this->assertFalse($settings->validate());
    }
}
