<?php

namespace App\Services\Documents\Understanding;

interface AiClassifierInterface
{
    /**
     * Check if AI provider is enabled and configured
     */
    public function isAvailable(): bool;

    /**
     * Classify document text and return strict metadata array
     */
    public function classify(string $excerpt, array $availableDepartments, array $allowedTypes): ?array;
}
