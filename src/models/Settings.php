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
    public string $claudeApiKey = '';
    public string $claudeModel = 'claude-sonnet-4-6';
    public string $geminiApiKey = '';
    public string $geminiModel = 'gemini-2.0-flash';
    public mixed $temperature = null;
    public mixed $maxTokens = null;
    public mixed $maxVraagLength = null;
    public mixed $rateLimit = null;
    public mixed $embeddingTopK = null;
    public mixed $embeddingChunkSize = null;
    public string $chatbotName = 'Assistent';
    public string $systemPrompt = 'Je bent een behulpzame assistent die uitsluitend antwoord geeft op basis van de verstrekte data.';
    public string $primaryColor = '#006bc2';
    public mixed $chatWidth = null;
    public mixed $chatHeight = null;
    public string $welcomeMessage = 'Hallo! Ik ben {name}, hoe kan ik je helpen?';
    public bool $useFallbackMessage = false;
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
                ['embeddingTopK'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 3 : $value;
                }
            ],
            [
                ['embeddingChunkSize'],
                'filter',
                'filter' => function ($value) {
                    return ($value === '' || $value === null) ? 500 : $value;
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
                function ($attribute) {
                    $value = $this->$attribute;
                    if ($value && !str_starts_with($value, '$')) {
                        $this->addError($attribute, 'API key moet via een ENV variabele ingesteld worden (bijv. $OPENAI_API_KEY).');
                    }
                }
            ],
            [
                ['groqApiKey'],
                function ($attribute) {
                    $value = $this->$attribute;
                    if ($value && !str_starts_with($value, '$')) {
                        $this->addError($attribute, 'API key moet via een ENV variabele ingesteld worden (bijv. $GROQ_API_KEY).');
                    }
                }
            ],
            [
                ['claudeApiKey'],
                function ($attribute) {
                    $value = $this->$attribute;
                    if ($value && !str_starts_with($value, '$')) {
                        $this->addError($attribute, 'API key moet via een ENV variabele ingesteld worden (bijv. $CLAUDE_API_KEY).');
                    }
                }
            ],
            [
                ['geminiApiKey'],
                function ($attribute) {
                    $value = $this->$attribute;
                    if ($value && !str_starts_with($value, '$')) {
                        $this->addError($attribute, 'API key moet via een ENV variabele ingesteld worden (bijv. $GEMINI_API_KEY).');
                    }
                }
            ],

            [['openaiApiKey', 'openaiModel', 'chatbotName', 'primaryColor', 'systemPrompt', 'welcomeMessage', 'fallbackMessage'], 'string'],
            [['temperature'], 'number', 'min' => 0, 'max' => 2],
            [['maxTokens'], 'integer', 'min' => 50, 'max' => 2000],
            [['maxVraagLength'], 'integer', 'min' => 10, 'max' => 2000],
            [['rateLimit'], 'integer', 'min' => 1, 'max' => 500],
            [['embeddingTopK'], 'integer', 'min' => 1, 'max' => 100],
            [['embeddingChunkSize'], 'integer', 'min' => 200, 'max' => 2000],
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
            [['aiProvider', 'groqApiKey', 'groqModel', 'claudeApiKey', 'claudeModel', 'geminiApiKey', 'geminiModel'], 'string'],
            [['aiProvider'], 'in', 'range' => ['openai', 'groq', 'claude', 'gemini']],
            [
                ['claudeApiKey'],
                'required',
                'when' => function ($model) {
                    return $model->aiProvider === 'claude';
                }
            ],
            [
                ['geminiApiKey'],
                'required',
                'when' => function ($model) {
                    return $model->aiProvider === 'gemini';
                }
            ],
        ];
    }
}
