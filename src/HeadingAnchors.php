<?php

namespace modlab\craftheadinganchors;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use modlab\craftheadinganchors\models\Settings;
use modlab\craftheadinganchors\services\Anchors;
use modlab\craftheadinganchors\twig\Extension;

/**
 * Heading Anchors plugin
 *
 * @method static HeadingAnchors getInstance()
 * @method Settings getSettings()
 */
class HeadingAnchors extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'anchors' => Anchors::class,
            ],
        ];
    }

    public function getAnchors(): Anchors
    {
        /** @var Anchors */
        return $this->get('anchors');
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->getView()->registerTwigExtension(new Extension());
    }
}
