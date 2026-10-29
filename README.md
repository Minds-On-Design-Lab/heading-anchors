# Heading Anchors

Automatically gives headings in rendered HTML unique, readable anchor ids (`#our-mission`), and lists them so templates can build an "On this page" menu. Built for rich content such as a CKEditor field with nested entries, where headings come from both the editor's text and from content block templates.

The plugin only handles ids and the heading list. The menu's markup, styling and any scroll behaviour stay in your templates.

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Installation

```bash
composer require mod-lab/craft-heading-anchors
php craft plugin/install heading-anchors
```

## Usage

### Add anchor ids

Wrap the rendered content in the `headingAnchors` filter:

```twig
{% apply headingAnchors %}
  {% for chunk in entry.mainContent %}
    …
  {% endfor %}
{% endapply %}
```

Every `<h2>` with text gets an `id` and a `data-heading-anchor` attribute:

```html
<h2 id="our-mission" data-heading-anchor>Our Mission</h2>
```

- **Ids** are the kebab-cased heading text (the same as Twig's `|kebab`), with `&` read as "and". Repeats get `-2`, `-3`, ….
- **No collisions:** ids never clash with any `id` already in the HTML.
- **Existing ids are kept:** a heading that already has an `id` keeps it and is only marked.
- **Empty headings are skipped.**

### List the headings

`headingAnchorList(html)` returns the anchored headings in document order, with plain text:

```twig
{% set content %}{% include '_components/contentBlocks' %}{% endset %}
{% set headings = headingAnchorList(content) %}

{% if headings|length >= 2 %}
  <nav aria-label="On this page">
    <ol>
      {% for heading in headings %}
        <li><a href="#{{ heading.id }}">{{ heading.text }}</a></li>
      {% endfor %}
    </ol>
  </nav>
{% endif %}

{{ content }}
```

Render the content into a variable first, then build the menu from it, so the menu can appear before the content on the page.

### Exclude something

Add `data-heading-anchors-ignore` to any element to skip it and everything inside it, e.g. a third-party embed:

```twig
<div data-heading-anchors-ignore>{{ entry.embedCode|raw }}</div>
```

## Configuration

Optionally, create `config/heading-anchors.php`:

```php
<?php

return [
    // Heading tags to anchor and list
    'tags' => ['h2'],
    // Attribute that excludes an element and its contents
    'ignoreAttribute' => 'data-heading-anchors-ignore',
];
```

## How it works

The HTML is scanned, not rebuilt through a DOM. Only the heading's opening tag is changed, by inserting ` id="…" data-heading-anchor` after the tag name, so everything else comes back byte-for-byte: Alpine attributes (`@click`, `:class`), SVG, whitespace and embed code.

Comments and the contents of `<script>`, `<style>`, `<textarea>` and `<title>` are never treated as markup. A pasted embed whose script contains `"<h2>…</h2>"` is left untouched.

Headings that JavaScript adds after the page loads, or that live inside an `<iframe>`, aren't part of the rendered HTML, so they aren't anchored or listed.
