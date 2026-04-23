<?php

namespace jelle\craftjsonplugin\services;

use craft\elements\Entry;
use craft\elements\db\ElementQueryInterface;
use yii\base\Component;

class NormalizationService extends Component
{
    public function normalizeElements($query, bool $isAsset): array
    {
        if (!$query instanceof ElementQueryInterface) {
            return [];
        }

        return array_map(function ($el) use ($isAsset) {
            $base = $isAsset
                ? ['type' => 'asset', 'id' => $el->id, 'filename' => $el->title, 'url' => $el->getUrl()]
                : ['type' => 'entry', 'id' => $el->id, 'title' => $el->title, 'url' => $el->url];

            if (!$isAsset && $el instanceof Entry) {
                foreach ($el->getFieldLayout()->getCustomFields() as $f) {
                    $base[$f->handle] = $this->normalizeValue($el->getFieldValue($f->handle));
                }
            }

            return $base;
        }, $query->all());
    }

    public function normalizeContentBuilder($blocks): array
    {
        $output = [];
        $entries = $blocks instanceof ElementQueryInterface ? $blocks->all() : ($blocks ?? []);

        foreach ($entries as $block) {
            $blockData = ['type' => $block->getType()->handle, 'fields' => []];
            foreach ($block->getFieldLayout()->getCustomFields() as $subField) {
                $blockData['fields'][$subField->handle] = $this->normalizeValue($block->getFieldValue($subField->handle));
            }
            $output[] = $blockData;
        }

        return $output;
    }

    public function normalizeMoney($value): string
    {
        if (!$value) {
            return '0.00';
        }

        $amount = 0.0;

        if (is_object($value)) {
            if (method_exists($value, 'getAmount')) {
                $amount = (float) $value->getAmount();
            } elseif (isset($value->amount)) {
                $amount = (float) $value->amount;
            }
        } else {
            $amount = (float) $value;
        }

        if ($amount > 100 && floor($amount) == $amount) {
            $amount = $amount / 100;
        }

        return number_format($amount, 2, '.', '');
    }

    public function normalizeValue($value)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ElementQueryInterface) {
            return $this->normalizeElements($value, str_contains(get_class($value), 'Asset'));
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \craft\fields\data\MultiOptionsFieldData) {
            return array_map(fn($o) => (string) $o, $value->getOptions());
        }

        if (is_object($value)) {
            $class = get_class($value);
            if (str_contains($class, 'Money')) {
                return $this->normalizeMoney($value);
            }
            if (str_contains($class, 'Address')) {
                return (string) $value;
            }
            return method_exists($value, '__toString') ? strip_tags((string) $value) : '[Object]';
        }

        if (is_array($value)) {
            return array_map([$this, 'normalizeValue'], $value);
        }

        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return is_numeric($value) ? $value : strip_tags((string) $value);
    }
}
