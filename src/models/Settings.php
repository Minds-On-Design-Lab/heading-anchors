<?php

namespace modlab\craftheadinganchors\models;

use craft\base\Model;

/**
 * Heading Anchors settings
 */
class Settings extends Model
{
    /**
     * @var string[] Heading tag names to anchor and list, e.g. `['h2']` or `['h2', 'h3']`.
     */
    public array $tags = ['h2'];

    /**
     * @var string Attribute that excludes an element, and everything inside it, from anchoring.
     */
    public string $ignoreAttribute = 'data-heading-anchors-ignore';

    protected function defineRules(): array
    {
        return [
            ['tags', function(string $attribute) {
                foreach ($this->tags as $tag) {
                    // The config file can supply anything, despite the property's PHPDoc type
                    // @phpstan-ignore function.alreadyNarrowedType
                    if (!is_string($tag) || !preg_match('/^h[1-6]$/i', $tag)) {
                        $this->addError($attribute, 'Tags must be heading tag names (h1–h6).');
                        return;
                    }
                }
            }],
            ['ignoreAttribute', 'required'],
        ];
    }

    /**
     * Returns the configured heading tag names, lowercased.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return array_map('strtolower', $this->tags);
    }
}
