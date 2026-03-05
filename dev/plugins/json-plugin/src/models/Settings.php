<?php

namespace jelle\craftjsonplugin\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public array $includedSections = [];
    public array $includedFields = [];
    public array $includedVolumes = [];

    public function rules(): array
    {
        return [
            [['includedSections', 'includedFields', 'includedVolumes'], 'safe'],
        ];
    }
}
