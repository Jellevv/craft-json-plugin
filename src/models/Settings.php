<?php
namespace jelle\craftjsonplugin\models;

use craft\base\Model;

class Settings extends Model
{
    public string $openaiApiKey = '';
    public string $openaiModel = 'gpt-4o-mini';
    public float $temperature = 0.5;
    public int $maxTokens = 300;
    public int $maxVraagLength = 500;
    public int $rateLimit = 50;
    public string $chatbotName = 'Assistent';
    public string $systemPrompt = 'Je bent een behulpzame assistent die uitsluitend antwoord geeft op basis van de verstrekte data.';
    public string $primaryColor = '#006bc2';
    public int $chatWidth = 300;
    public int $chatHeight = 400;
    public string $welcomeMessage = 'Hallo! Ik ben {name}, hoe kan ik je helpen?';
    public bool $useFallbackMessage = true;
    public string $fallbackMessage = 'Sorry, ik heb geen informatie over dit onderwerp. Neem gerust contact met ons op voor meer hulp.';
    public array $includedSections = [];
    public array $includedFields = [];

    //public array $includedVolumes = [];

    public function rules(): array
    {
        return [
            [['openaiApiKey', 'openaiModel', 'chatbotName', 'primaryColor', 'systemPrompt', 'welcomeMessage'], 'string'],
            [['temperature'], 'number', 'min' => 0, 'max' => 2],
            [['maxTokens'], 'integer', 'min' => 0, 'max' => 2000],
            [['maxVraagLength'], 'integer', 'min' => 0, 'max' => 2000],
            [['rateLimit'], 'integer', 'min' => 1, 'max' => 500],
            [['chatWidth'], 'integer', 'min' => 280, 'max' => 600],
            [['chatHeight'], 'integer', 'min' => 280, 'max' => 900],
            [['includedSections', 'includedFields'], 'safe'],
            [['useFallbackMessage'], 'boolean'],
            [['fallbackMessage'], 'string'],
        ];
    }
}
