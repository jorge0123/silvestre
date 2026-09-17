<?php

namespace App\Services;

use App\Enums\RiskLevel;
use App\Support\ProhibitedItems;

/**
 * Primera barrera automática sobre nombre y descripción de cualquier contenido.
 *
 * Qué SÍ hace: atrapa lo obvio y lo descarado, y manda lo dudoso a una persona.
 * Qué NO hace: sustituir la revisión humana. Un filtro de palabras se evade en
 * cinco minutos ("c0ca1na"), así que la normalización de aquí sube el costo de
 * evadirlo, pero la defensa de verdad son la verificación de identidad, los
 * reportes y la cola de moderación.
 */
class ContentScanner
{
    /** Resultado de un escaneo. */
    public function scan(string ...$texts): ScanResult
    {
        $haystack = $this->normalize(implode(' ', array_filter($texts)));

        foreach (ProhibitedItems::prohibited() as $category => $terms) {
            if ($hit = $this->firstMatch($haystack, $terms)) {
                return new ScanResult(RiskLevel::Prohibited, $category, $hit);
            }
        }

        foreach (ProhibitedItems::restricted() as $category => $config) {
            if ($hit = $this->firstMatch($haystack, $config['terms'])) {
                return new ScanResult(RiskLevel::Restricted, $category, $hit, $config['document']);
            }
        }

        foreach (ProhibitedItems::sensitive() as $category => $terms) {
            if ($hit = $this->firstMatch($haystack, $terms)) {
                return new ScanResult(RiskLevel::Sensitive, $category, $hit);
            }
        }

        return new ScanResult(RiskLevel::Allowed);
    }

    /**
     * Baja todo a minúsculas, quita acentos y deshace las sustituciones típicas
     * de evasión (0→o, 1→i, 3→e, 4→a, 5→s, @→a, $→s).
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
            '@' => 'a', '$' => 's', '!' => 'i',
        ]);

        // Colapsa separadores usados para partir palabras: c-o-c-a, c.o.c.a
        $text = preg_replace('/[\-_.*\s]+/u', ' ', $text) ?? $text;

        return ' '.trim($text).' ';
    }

    /** @param array<int, string> $terms */
    private function firstMatch(string $haystack, array $terms): ?string
    {
        foreach ($terms as $term) {
            $needle = $this->normalize($term);

            // Con espacios alrededor para no marcar "arma" dentro de "armazón".
            if (str_contains($haystack, $needle)) {
                return trim($needle);
            }
        }

        return null;
    }
}

/**
 * Veredicto del escaneo. Inmutable a propósito: se guarda tal cual en el
 * registro de moderación para poder auditar por qué se bloqueó algo.
 */
readonly class ScanResult
{
    public function __construct(
        public RiskLevel $risk,
        public ?string $category = null,
        public ?string $matchedTerm = null,
        public ?string $requiredDocument = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->risk === RiskLevel::Allowed;
    }

    public function blocks(): bool
    {
        return $this->risk->blocksPublication();
    }

    public function needsReview(): bool
    {
        return $this->risk->needsReview();
    }

    /** Mensaje para el negocio: qué pasó y qué tiene que hacer. */
    public function message(): string
    {
        $base = $this->risk->userMessage();

        if ($this->requiredDocument) {
            return "{$base} Documento necesario: {$this->requiredDocument}.";
        }

        if ($this->category && $this->risk->blocksPublication()) {
            return "{$base} Categoría detectada: {$this->category}.";
        }

        return $base;
    }

    public function toArray(): array
    {
        return [
            'risk' => $this->risk->value,
            'category' => $this->category,
            'matched_term' => $this->matchedTerm,
            'required_document' => $this->requiredDocument,
        ];
    }
}
