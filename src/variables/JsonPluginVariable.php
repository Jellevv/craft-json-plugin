<?php
namespace jelle\craftjsonplugin\variables;

use Craft;

class JsonPluginVariable
{
    public function render(): string
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            return '';
        }

        $view = Craft::$app->getView();
        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();

        try {
            return $view->renderTemplate('json-plugin/_placeholder', [
                'chatbotName' => $settings->chatbotName ?: 'Assistent',
                'primaryColor' => ltrim($settings->primaryColor ?: '006bc2', '#'),
                'chatWidth' => $settings->chatWidth ?: 300,
                'chatHeight' => $settings->chatHeight ?: 500,
                'welcomeMessage' => $settings->welcomeMessage ?: 'Hallo! Hoe kan ik je helpen?',
            ], $view::TEMPLATE_MODE_CP);
        } catch (\Exception $e) {
            Craft::error("Template niet gevonden: " . $e->getMessage(), 'json-plugin');
            return '';
        }
    }
}
