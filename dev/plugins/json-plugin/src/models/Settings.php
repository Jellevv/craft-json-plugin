<?php
namespace jelle\craftjsonplugin\models;

use craft\base\Model;

class Settings extends Model
{
    public string $openaiApiKey = '';
    public string $openaiModel = 'gpt-4o-mini';
    public float $temperature = 0.5;
    public string $chatbotName = 'Assistent';
    public string $systemPrompt = 'Je bent een behulpzame assistent die uitsluitend antwoord geeft op basis van de verstrekte data.';
    public string $primaryColor = '#006bc2';
    public int $chatWidth = 300;
    public int $chatHeight = 400;
    public string $welcomeMessage = 'Hallo! Ik ben {name}, hoe kan ik je helpen?';
    public array $includedSections = [];
    public array $includedFields = [];
    public array $includedVolumes = [];

    public function rules(): array
    {
        return [
            [['openaiApiKey', 'openaiModel', 'chatbotName', 'primaryColor', 'systemPrompt', 'welcomeMessage'], 'string'],
            [['temperature'], 'number', 'min' => 0, 'max' => 2],
            [['chatWidth'], 'integer', 'min' => 280, 'max' => 600],
            [['chatHeight'], 'integer', 'min' => 280, 'max' => 900],
            [['includedSections', 'includedFields', 'includedVolumes'], 'safe'],
        ];
    }
}
