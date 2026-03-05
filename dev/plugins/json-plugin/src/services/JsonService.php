<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Asset;
use GuzzleHttp\Client;
use yii\base\Component;
use craft\elements\db\ElementQueryInterface;

class JsonService extends Component
{
    private string $nodeUrl = 'http://localhost:3000';

    private function getSettings()
    {
        return \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
    }

    public function pushSingleEntry(int $elementId): bool
    {
        try {
            $element = Craft::$app->getElements()->getElementById($elementId);
            if (!$element instanceof Entry) return false;

            $client = new Client(['timeout' => 3.0]);
            $client->post($this->nodeUrl . '/update-single-entry', [
                'json' => $this->prepareEntryData($element),
            ]);
            return true;
        } catch (\Exception $e) {
            Craft::error("Fout bij pushen entry {$elementId}: " . $e->getMessage(), 'json-plugin');
            return false;
        }
    }

    private function prepareEntryData(Entry $element): array
    {
        $settings = $this->getSettings();
        $includedFields = $settings->includedFields ?? [];

        $data = [
            'id'     => $element->id,
            'title'  => $element->title ?? 'Naamloos',
            'url'    => $element->url,
            'sectie' => $element->section->handle ?? 'onbekend',
        ];

        foreach ($includedFields as $handle) {
            try {
                $field = Craft::$app->getFields()->getFieldByHandle($handle);
                if (!$field) continue;

                $objectValue = $element->getFieldValue($handle);
                
                $data[$handle] = match (true) {
                    $field instanceof \craft\fields\Assets => $this->normalizeElements($objectValue, true),
                    $field instanceof \craft\fields\Entries,
                    $field instanceof \craft\fields\Categories => $this->normalizeElements($objectValue, false),
                    $field instanceof \craft\fields\Matrix => $this->normalizeContentBuilder($objectValue),
                    $field instanceof \craft\fields\Lightswitch => (bool)$objectValue,
                    str_contains(get_class($field), 'Money') => $this->normalizeMoney($objectValue),
                    default => $this->normalizeValue($objectValue),
                };
            } catch (\Exception $e) {
                continue;
            }
        }

        return $data;
    }

    private function normalizeElements($query, bool $isAsset): array
    {
        if (!$query instanceof ElementQueryInterface) return [];
        
        $elements = $query->all();
        return array_map(function ($el) use ($isAsset) {
            $base = [
                'id'    => $el->id,
                'title' => $el->title,
                'url'   => ($el instanceof Asset) ? $el->getUrl() : $el->url,
            ];

            if (!$isAsset && $el instanceof Entry) {
                foreach ($el->getFieldLayout()->getCustomFields() as $f) {
                    $base[$f->handle] = $this->normalizeValue($el->getFieldValue($f->handle));
                }
            }
            return $base;
        }, $elements);
    }

    private function normalizeMoney($value): string
    {
        if (!$value) return "0.00";
        
        $amountValue = 0;

        // Stap 1: Haal de numerieke waarde uit het object zonder te casten naar string
        if (is_object($value)) {
            if (method_exists($value, 'getAmount')) {
                $amountValue = (float)$value->getAmount();
            } elseif (isset($value->amount)) {
                $amountValue = (float)$value->amount;
            }
        } else {
            $amountValue = (float)$value;
        }

        // Stap 2: Check of het in centen is (vaak bij Commerce Money objecten)
        // We checken de numerieke waarde, NIET het object zelf.
        if ($amountValue > 100 && floor($amountValue) == $amountValue) {
             // Als er geen decimalen zijn, is het waarschijnlijk centen
             $amountValue = $amountValue / 100;
        }

        return number_format($amountValue, 2, '.', '');
    }

    private function normalizeContentBuilder($blocks): array
    {
        $output = [];
        $entries = ($blocks instanceof ElementQueryInterface) ? $blocks->all() : ($blocks ?? []);

        foreach ($entries as $block) {
            $blockData = ['type' => $block->getType()->handle, 'fields' => []];
            foreach ($block->getFieldLayout()->getCustomFields() as $subField) {
                $val = $block->getFieldValue($subField->handle);
                $blockData['fields'][$subField->handle] = $this->normalizeValue($val);
            }
            $output[] = $blockData;
        }
        return $output;
    }

    private function normalizeValue($value)
    {
        if ($value === null) return null;

        if ($value instanceof ElementQueryInterface) {
            $isAsset = str_contains(get_class($value), 'Asset');
            return $this->normalizeElements($value, $isAsset);
        }

        if ($value instanceof \DateTimeInterface) return $value->format(DATE_ATOM);
        
        if ($value instanceof \craft\fields\data\MultiOptionsFieldData) {
            return array_map(fn($o) => (string)$o, $value->getOptions());
        }

        if (is_object($value)) {
            // Check of dit object een Money klasse is voordat we verder gaan
            if (str_contains(get_class($value), 'Money')) {
                return $this->normalizeMoney($value);
            }
            if (str_contains(get_class($value), 'Address')) return (string)$value;
            return method_exists($value, '__toString') ? strip_tags((string)$value) : "[Object]";
        }

        if (is_array($value)) return array_map([$this, 'normalizeValue'], $value);
        
        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }

        return is_numeric($value) ? $value : strip_tags((string)$value);
    }

    public function syncAllContent(): array
    {
        $settings = $this->getSettings();
        $count = 0;
        try {
            $client = new Client(['timeout' => 5.0]);
            $client->post($this->nodeUrl . '/clear-all-data');
            usleep(500000);
            if (!empty($settings->includedSections)) {
                $entries = Entry::find()->section($settings->includedSections)->all();
                foreach ($entries as $e) { if ($this->pushSingleEntry($e->id)) $count++; }
            }
            return ['success' => true, 'count' => $count];
        } catch (\Exception $e) { return ['success' => false, 'message' => $e->getMessage()]; }
    }

    public function deleteEntry(int $elementId): void
    {
        try {
            (new Client(['timeout' => 3.0]))->post($this->nodeUrl . '/delete-entry', ['json' => ['id' => $elementId]]);
        } catch (\Exception $e) { Craft::error($e->getMessage()); }
    }
}
