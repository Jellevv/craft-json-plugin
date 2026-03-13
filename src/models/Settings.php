<?php
namespace jelle\craftjsonplugin\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public string $openaiApiKey = '';
    public string $openaiModel = 'gpt-4o-mini';
    public string $aiProvider = 'openai';
    public string $groqApiKey = '';
    public string $groqModel = 'llama-3.3-70b-versatile';

    public mixed $temperature = 0.5;
    public mixed $maxTokens = 300;
    public mixed $maxVraagLength = 500;
    public mixed $rateLimit = 50;
    public string $chatbotName = 'Assistent';
    public string $systemPrompt = 'Je bent een behulpzame assistent die uitsluitend antwoord geeft op basis van de verstrekte data.';
    public string $primaryColor = '#006bc2';
    public mixed $chatWidth = 300;
    public mixed $chatHeight = 400;
    public string $welcomeMessage = 'Hallo! Ik ben {name}, hoe kan ik je helpen?';
    public bool $useFallbackMessage = true;
    public string $fallbackMessage = 'Sorry, ik heb geen informatie over dit onderwerp. Neem gerust contact met ons op voor meer hulp.';
    public array $includedSections = [];
    public array $includedFields = [];

    //public array $includedVolumes = [];

    public function rules(): array
    {
        return [
            [
                ['maxTokens'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 300 : $value;
                }
            ],
            [
                ['maxVraagLength'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 500 : $value;
                }
            ],
            [
                ['rateLimit'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 50 : $value;
                }
            ],
            [
                ['temperature'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 0.5 : $value;
                }
            ],
            [
                ['chatWidth'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 300 : $value;
                }
            ],
            [
                ['chatHeight'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 400 : $value;
                }
            ],
            [
                ['primaryColor'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? '#006bc2' : $value;
                }
            ],

            [['chatbotName', 'welcomeMessage'], 'required'],
            [
                ['openaiApiKey'],
                'required',
                'when' => function ($model) {
                    return $model->aiProvider === 'openai';
                }
            ],
            [
                ['groqApiKey'],
                'required',
                'when' => function ($model) {
                    return $model->aiProvider === 'groq';
                }
            ],

            [['openaiApiKey', 'openaiModel', 'chatbotName', 'primaryColor', 'systemPrompt', 'welcomeMessage', 'fallbackMessage'], 'string'],
            [['temperature'], 'number', 'min' => 0, 'max' => 2],
            [['maxTokens'], 'integer', 'min' => 50, 'max' => 2000],
            [['maxVraagLength'], 'integer', 'min' => 50, 'max' => 2000],
            [['rateLimit'], 'integer', 'min' => 1, 'max' => 500],
            [['chatWidth'], 'integer', 'min' => 280, 'max' => 600],
            [['chatHeight'], 'integer', 'min' => 280, 'max' => 900],
            [['useFallbackMessage'], 'boolean'],
            [['includedSections', 'includedFields'], 'safe'],
            [
                ['fallbackMessage'],
                'required',
                'when' => function ($model) {
                    return $model->useFallbackMessage === true;
                }
            ],
            [['aiProvider', 'groqApiKey', 'groqModel'], 'string'],
            [['aiProvider'], 'in', 'range' => ['openai', 'groq']],
        ];
    }
    public function afterValidate(): void
{
    \Craft::error('Settings fouten na validatie: ' . json_encode($this->getErrors()), 'json-plugin');
    parent::afterValidate();
}
}
