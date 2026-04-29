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
        $settings = JsonPlugin::$plugin->getSettings();

        $ip = $request->getUserIP();
        $cache = Craft::$app->getCache();
        $rateCacheKey = 'chat_ratelimit_' . md5($ip);

        $count = (int) $cache->get($rateCacheKey);
        $limit = (int) ($settings->rateLimit ?? 50);

        if ($count >= $limit) {
            return $this->asJson([
                'error' => true,
                'antwoord' => 'Je hebt het maximaal aantal vragen bereikt. Probeer het later opnieuw.'
            ]);
        }
        $cache->set($rateCacheKey, $count + 1, 3600);

        $vraag = trim($request->getBodyParam('vraag', ''));
        $maxVraagLength = (int) ($settings->maxVraagLength ?? 500);

        if (!$vraag) {
            return $this->asJson(['error' => true, 'antwoord' => 'Geen vraag ontvangen.']);
        }

        if (mb_strlen($vraag) > $maxVraagLength) {
            return $this->asJson([
                'error' => true,
                'antwoord' => "Je vraag is te lang. Het maximum is {$maxVraagLength} tekens."
            ]);
        }

        $sessionId = preg_replace('/[^a-z0-9]/', '', $request->getBodyParam('sessionId') ?? '');
        $isNewSession = strlen($sessionId) < 5;
        if ($isNewSession) {
            $sessionId = bin2hex(random_bytes(8));
        }

        try {
            $service = JsonPlugin::$plugin->get('chat');
            $antwoord = $service->getAiResponse($vraag, $sessionId, $request->getBodyParam('pageUrl'));

            $responseData = ['antwoord' => $antwoord];
            if ($isNewSession) {
                $responseData['sessionId'] = $sessionId;
            }

            return $this->asJson($responseData);
        } catch (\Exception $e) {
            Craft::error('Chat fout: ' . $e->getMessage(), 'json-plugin');
            return $this->asJson([
                'error' => true,
                'antwoord' => 'Er is iets misgegaan. Probeer het later opnieuw.'
            ]);
        }
    }
}
