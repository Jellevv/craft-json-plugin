<?php

namespace jelle\craftjsonplugin\controllers;

use Craft;
use craft\web\Controller;
use jelle\craftjsonplugin\JsonPlugin;
use yii\web\Response;

class ChatController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionVraag(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $vraag = $request->getBodyParam('vraag');
        $sessionId = $request->getBodyParam('sessionId') ?: 'default';
        $pageUrl = $request->getBodyParam('pageUrl') ?: '';

        $service = JsonPlugin::$plugin->get('jsonService');

        try {
            $antwoord = $service->getAiResponse($vraag, $sessionId, $pageUrl);
            return $this->asJson([
                'antwoord' => $antwoord,
                'sessionId' => $sessionId
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'error' => true,
                'antwoord' => 'Er is iets misgegaan: ' . $e->getMessage()
            ]);
        }
    }
}
