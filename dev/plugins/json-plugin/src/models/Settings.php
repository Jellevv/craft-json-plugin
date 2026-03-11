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
    public string $primaryColor = '#1f7a5c';
    public array $includedSections = [];
    public array $includedFields = [];
    public array $includedVolumes = [];

    public function rules(): array
    {
        return [
            [['openaiApiKey', 'openaiModel', 'chatbotName', 'primaryColor', 'systemPrompt'], 'string'],
            [['temperature'], 'number', 'min' => 0, 'max' => 2],
            [['includedSections', 'includedFields', 'includedVolumes'], 'safe'],
        ];
    }
}
