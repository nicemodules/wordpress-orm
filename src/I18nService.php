<?php

namespace NiceModules\ORM;

interface I18nService
{
    /**
     * Gets translation for current selected language from default service language
     * @param string $text
     * @return string
     */
    public function translateDefaultToCurrent(string $text): string;

    /**
     * Gets translation from current selected language to default service language
     * @param string $text
     * @return string
     */
    public function translateCurrentToDefault(string $text): string;
    
    
    public function needTranslation(): bool;
    
    public function getLanguage(): string;
}