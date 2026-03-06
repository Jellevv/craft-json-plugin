<?php

namespace jelle\craftjsonplugin\controllers;

use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use jelle\craftjsonplugin\JsonPlugin;
use yii\web\Response;

class SyncController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionSyncAll(): Response
    {
        $service = JsonPlugin::$plugin->get('jsonService');
        $result = $service->syncAllContent();

        if ($result['success']) {
            Craft::$app->getSession()->setNotice("Synchronisatie voltooid: {$result['count']} items gepusht naar de chatbot.");
        } else {
            Craft::$app->getSession()->setError("Synchronisatie mislukt: " . ($result['message'] ?? 'Onbekende fout'));
        }

return $this->redirect('settings/plugins/json-plugin');    }
}
