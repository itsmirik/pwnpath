<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Adds locale-aware attribute accessors backed by a translations table.
 *
 * Consumers must:
 *   - define `translationModel()` returning the translation FQCN
 *   - define `translatableAttributes()` returning field names
 *   - declare a `translations` HasMany relation on the model
 *
 * Fallback order: requested locale → app fallback → any available → null.
 */
trait Translatable
{
    /** @return class-string<Model> */
    abstract public function translationModel(): string;

    /** @return array<int, string> */
    abstract public function translatableAttributes(): array;

    public function translate(?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();

        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('app.fallback_locale'))
            ?? $translations->first();
    }

    public function tr(string $attribute, ?string $locale = null): mixed
    {
        return $this->translate($locale)?->{$attribute};
    }

    /** @return array<string, mixed> */
    public function localizedAttributes(?string $locale = null): array
    {
        $translation = $this->translate($locale);
        $out = [];

        foreach ($this->translatableAttributes() as $field) {
            $out[$field] = $translation?->{$field};
        }

        return $out;
    }
}
