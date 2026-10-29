# Release Notes for Heading Anchors

## [v1.0.0] - 2026-10-29

### Added

- `headingAnchors` Twig filter that adds unique, slugged `id`s and a `data-heading-anchor` attribute to headings in rendered HTML, keeping existing ids and leaving all other markup untouched
- `headingAnchorList()` Twig function that returns the anchored headings (`id`, plain `text`) in document order, for building in-page navigation
- Comments and the contents of `<script>`, `<style>`, `<textarea>` and `<title>` are never parsed as markup, so embed code is safe
- `data-heading-anchors-ignore` attribute to exclude an element and its contents
- `config/heading-anchors.php` settings: `tags` (default `['h2']`) and `ignoreAttribute`
