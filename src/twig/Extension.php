<?php

namespace modlab\craftheadinganchors\twig;

use modlab\craftheadinganchors\HeadingAnchors;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig API
 *
 * - `headingAnchors` filter: adds anchor ids to headings, e.g. `{% apply headingAnchors %}…{% endapply %}`
 * - `headingAnchorList(html)` function: returns the anchored headings as `[{id, text}]`
 */
class Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('headingAnchors', fn(mixed $html): string => HeadingAnchors::getInstance()->getAnchors()->anchor((string)$html), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('headingAnchorList', fn(mixed $html): array => HeadingAnchors::getInstance()->getAnchors()->headings((string)$html)),
        ];
    }
}
