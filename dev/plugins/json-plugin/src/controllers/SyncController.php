<?php

namespace jelle\craftjsonplugin\controllers;

use Craft;
use craft\web\Controller;
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
            Craft::$app->getSession()->setNotice("Synchronisatie voltooid: {$result['count']} items gepusht.");
        } else {
            Craft::$app->getSession()->setError("Synchronisatie mislukt: " . $result['message']);
        }

        return $this->redirect('settings/plugins/_json-plugin');
    }
}
