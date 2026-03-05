<?php

namespace jelle\craftjsonplugin;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\base\Element;
use craft\events\ModelEvent;
use yii\base\Event;
use craft\elements\Asset;
use jelle\craftjsonplugin\models\Settings;
use jelle\craftjsonplugin\services\JsonService;
use jelle\craftjsonplugin\controllers\SyncController;

/**
 * JSON Plugin plugin
 *
 * @method static JsonPlugin getInstance()
 * @method Settings getSettings()
 */
class JsonPlugin extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public static JsonPlugin $plugin;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->set('jsonService', \jelle\craftjsonplugin\services\JsonService::class);

        $this->controllerMap['sync'] = SyncController::class;

        \yii\base\Event::on(
            \craft\base\Element::class,
            \craft\base\Element::EVENT_AFTER_SAVE,
            function ($event) {
                $element = $event->sender;

                if (($element instanceof Entry || $element instanceof Product) && !$element->getIsDraft() && !$element->getIsRevision()) {

                    $settings = $this->getSettings();
                    $includedSections = $settings->includedSections ?? [];

                    $sectionHandle = ($element instanceof Entry) ? $element->section->handle : 'products';
    
                    if (in_array($sectionHandle, $includedSections)) {
                        $this->get('jsonService')->pushSingleEntry($element->id);
                    }
                }
            }
        );

        \yii\base\Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function ($event) {
                $asset = $event->sender;

                if (ElementHelper::isDraftOrRevision($asset)) {
                    return;
                }

                $settings = $this->getSettings();
                $includedVolumes = $settings->includedVolumes ?? [];

                if (in_array($asset->getVolume()->handle, $includedVolumes)) {
                    $this->get('jsonService')->pushAsset($asset->id);
                }
            }
        );

        \yii\base\Event::on(
            \craft\base\Element::class,
            \craft\base\Element::EVENT_BEFORE_DELETE,
            function ($event) {
                $element = $event->sender;
                if ($element instanceof \craft\elements\Entry) {
                    $this->get('jsonService')->deleteEntry($element->id);
                }
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        $allSections = \Craft::$app->getEntries()->getAllSections();
        $sectionOptions = [];
        foreach ($allSections as $section) {
            $sectionOptions[] = ['label' => $section->name, 'value' => $section->handle];
        }

        $allFields = \Craft::$app->getFields()->getAllFields();
        $fieldOptions = [];
        foreach ($allFields as $field) {
            $fieldOptions[] = ['label' => $field->name, 'value' => $field->handle];
        }

        $allVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $volumeOptions = [];
        foreach ($allVolumes as $volume) {
            $volumeOptions[] = ['label' => $volume->name, 'value' => $volume->handle];
        }

        return \Craft::$app->view->renderTemplate('_json-plugin/settings', [
            'settings' => $this->getSettings(),
            'sectionOptions' => $sectionOptions,
            'fieldOptions' => $fieldOptions,
            'volumeOptions' => $volumeOptions,
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
    }
}
