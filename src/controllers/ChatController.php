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

        // ── Rate limiting op IP ────────────────────────────────
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateCacheKey = 'chat_ratelimit_' . md5($ip);
        $count = \Craft::$app->getCache()->get($rateCacheKey) ?: 0;
        $limit = (int) ($settings->rateLimit ?? 50);

        if ($count >= $limit) {
            return $this->asJson([
                'error' => true,
                'antwoord' => 'Je hebt het maximaal aantal vragen bereikt. Probeer het later opnieuw.'
            ]);
        }

        \Craft::$app->getCache()->set($rateCacheKey, $count + 1, 3600);

        // ── Invoer validatie ───────────────────────────────────
        $vraag = $request->getBodyParam('vraag') ?? '';
        $maxVraagLength = (int) ($settings->maxVraagLength ?? 500);

        if (empty(trim($vraag))) {
            return $this->asJson(['error' => true, 'antwoord' => 'Geen vraag ontvangen.']);
        }

        if (mb_strlen($vraag) > $maxVraagLength) {
            return $this->asJson([
                'error' => true,
                'antwoord' => "Je vraag is te lang. Het maximum is {$maxVraagLength} tekens."
            ]);
        }

        $sessionId = preg_replace('/[^a-z0-9]/', '', $request->getBodyParam('sessionId') ?? '');
        if (strlen($sessionId) < 5) {
            $sessionId = bin2hex(random_bytes(8));
        }

        $pageUrl = $request->getBodyParam('pageUrl') ?? '';
        if ($pageUrl && !filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            $pageUrl = '';
        }

        // ── Vraag verwerken ────────────────────────────────────
        $service = JsonPlugin::$plugin->get('jsonService');

        try {
            $antwoord = $service->getAiResponse($vraag, $sessionId, $pageUrl);
            return $this->asJson([
                'antwoord' => $antwoord,
                'sessionId' => $sessionId
            ]);
        } catch (\Exception $e) {
            Craft::error('Chat fout: ' . $e->getMessage(), 'json-plugin');
            return $this->asJson([
                'error' => true,
                'antwoord' => 'Er is iets misgegaan. Probeer het later opnieuw.'
            ]);
        }
    }
}
