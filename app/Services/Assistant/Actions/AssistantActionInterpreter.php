<?php

namespace App\Services\Assistant\Actions;

/**
 * Deterministic, regex-based extraction of the raw fields a "prepárame
 * una factura/cliente/producto/nota crédito..." message is interpreting
 * — never a price, tax, or total (those only ever come from Laravel's
 * own domain services, see IA 2.0-D report "vista previa confiable").
 * This class only ever produces free-text *terms* ("kata", "camisas")
 * and raw user-stated numbers; resolving a term to an actual row (and
 * detecting "no such row" / "more than one row") is the Propose*Action
 * classes' job, reusing the same FindCustomerTool-style tools the
 * read-only assistant already uses.
 */
class AssistantActionInterpreter
{
    private const NUMBER_WORDS = [
        'un' => '1', 'una' => '1', 'uno' => '1', 'dos' => '2', 'tres' => '3',
        'cuatro' => '4', 'cinco' => '5', 'seis' => '6', 'siete' => '7',
        'ocho' => '8', 'nueve' => '9', 'diez' => '10',
    ];

    /**
     * "Prepárame una factura para kata con 2 camisas." / "Factura 3
     * unidades del producto PRUEBA-001 a kata." / "Créame un borrador de
     * factura para kata con 2 camisas y método de pago transferencia."
     *
     * @return array{customer_term: ?string, lines: array<int, array{quantity: string, term: string}>, payment_type: ?string, payment_method: ?string, notes: ?string}
     */
    public function interpretInvoiceDraft(string $message): array
    {
        return [
            'customer_term' => $this->extractCustomerTerm($message),
            'lines' => $this->extractQuantityLines($message),
            'payment_type' => $this->extractPaymentType($message),
            'payment_method' => $this->extractPaymentMethod($message),
            'notes' => $this->extractNotes($message),
        ];
    }

    /**
     * "Prepárame una nota crédito total para FV-000003." / "Quiero
     * devolver una unidad de la factura FV-000002."
     *
     * @return array{invoice_number: ?string, total: bool, lines: array<int, array{quantity: string, term: string}>}
     */
    public function interpretCreditNoteDraft(string $message): array
    {
        preg_match('/\b(FV-\d{1,12})\b/i', $message, $matches);

        return [
            'invoice_number' => isset($matches[1]) ? strtoupper($matches[1]) : null,
            'total' => (bool) preg_match('/\btotal(mente)?\b/iu', $message),
            'lines' => $this->extractQuantityLines($message),
            'bare_quantity' => $this->extractBareQuantityHint($message),
        ];
    }

    /**
     * "una unidad de la factura FV-000002" names no product at all —
     * only ProposeCreditNoteDraftAction can tell whether that quantity
     * unambiguously applies (an invoice with exactly one line) or needs
     * clarification (more than one).
     */
    private function extractBareQuantityHint(string $message): ?string
    {
        $numberWord = implode('|', array_keys(self::NUMBER_WORDS));

        if (preg_match('/\b(\d+|'.$numberWord.')\s+unidad(?:es)?\s+de\s+la\s+factura\b/iu', $message, $matches)) {
            return is_numeric($matches[1]) ? $matches[1] : (self::NUMBER_WORDS[mb_strtolower($matches[1])] ?? '1');
        }

        return null;
    }

    /**
     * "Prepara un cliente llamado Juan Pérez con CC 123456." / "CC
     * 12345." (a continuation turn supplying only the missing field).
     *
     * @return array{name: ?string, identification_type: ?string, identification_number: ?string, person_type: ?string}
     */
    public function interpretCustomer(string $message): array
    {
        $identificationType = null;
        $identificationNumber = null;

        if (preg_match('/\b(cc|ce|nit|pas|ti|rc)\b\s*([\d.\-]{4,20})/iu', $message, $matches)) {
            $identificationType = strtoupper($matches[1]);
            $identificationNumber = rtrim($matches[2], '.-');
        }

        $name = null;
        if (preg_match('/\bllamad[oa]\s+([^.,;]+?)(?:\s+con\b|\s*,|\.|$)/iu', $message, $matches)) {
            $name = trim($matches[1]);
        } elseif (preg_match('/\b(?:cliente|registra(?:r)?\s+(?:este\s+)?cliente)\s*:?\s*([^.,;]+?)(?:\s+con\b|\s*,|\.|$)/iu', $message, $matches)) {
            $name = trim($matches[1]) ?: null;
        }

        return [
            'name' => $name !== '' ? $name : null,
            'identification_type' => $identificationType,
            'identification_number' => $identificationNumber,
            'person_type' => $identificationType === 'NIT' ? 'juridica' : ($identificationType !== null ? 'natural' : null),
        ];
    }

    /**
     * "Prepara un producto Camisa Azul por $100.000 con IVA 19%." /
     * "Registra un servicio de consultoría por $250.000 exento."
     *
     * @return array{name: ?string, price: ?string, tax_rate: ?float, exempt: bool, type: ?string}
     */
    public function interpretProduct(string $message): array
    {
        $type = null;
        if (preg_match('/\bservicio\b/iu', $message)) {
            $type = 'service';
        } elseif (preg_match('/\bproducto\b/iu', $message)) {
            $type = 'product';
        }

        $name = null;
        if (preg_match('/\b(?:producto|servicio)\s+(?:de\s+)?([^.,;]+?)\s+por\s*\$?\s*[\d.,]+/iu', $message, $matches)) {
            $name = trim($matches[1]);
        }

        $price = null;
        if (preg_match('/por\s*\$?\s*([\d.,]+)/iu', $message, $matches)) {
            $price = $this->normalizeAmount($matches[1]);
        }

        $exempt = (bool) preg_match('/\bexent[oa]\b/iu', $message);
        $taxRate = null;
        if (! $exempt && preg_match('/\biva\s*(\d+(?:[.,]\d+)?)\s*%?/iu', $message, $matches)) {
            $taxRate = (float) str_replace(',', '.', $matches[1]);
        }

        return [
            'name' => $name !== '' ? $name : null,
            'price' => $price,
            'tax_rate' => $taxRate,
            'exempt' => $exempt,
            'type' => $type,
        ];
    }

    /**
     * @return array<int, array{quantity: string, term: string}>
     */
    private function extractQuantityLines(string $message): array
    {
        $numberWord = implode('|', array_keys(self::NUMBER_WORDS));
        $pattern = '/\b(\d+|'.$numberWord.')\s+(?:unidades?\s+(?:del?\s+)?(?:producto\s+)?|unidad\s+de\s+(?:la\s+)?)?'
            .'([a-záéíóúñ][\wáéíóúñ\-]*(?:\s+[a-záéíóúñ][\wáéíóúñ]*){0,3}?)'
            .'(?=\s+(?:a|para|al?\s+cliente)\b|[.,;]|$)/iu';

        if (! preg_match_all($pattern, $message, $allMatches, PREG_SET_ORDER)) {
            return [];
        }

        $lines = [];
        foreach ($allMatches as $match) {
            $quantity = is_numeric($match[1]) ? $match[1] : (self::NUMBER_WORDS[mb_strtolower($match[1])] ?? '1');
            $term = trim($match[2]);

            if ($term === '' || preg_match('/^(borrador|factura|cliente|producto|nota)\b/iu', $term)) {
                continue;
            }

            $lines[] = ['quantity' => $quantity, 'term' => $term];
        }

        return $lines;
    }

    private function extractCustomerTerm(string $message): ?string
    {
        if (preg_match('/\bpara\s+(?:el\s+cliente\s+)?([^.,;]+?)(?:\s+con\b|\s*[.,;]|$)/iu', $message, $matches)) {
            return trim($matches[1]) ?: null;
        }

        if (preg_match('/\ba\s+([a-záéíóúñ][^.,;]*?)\s*[.,;]?\s*$/iu', $message, $matches)) {
            return trim($matches[1]) ?: null;
        }

        return null;
    }

    private function extractPaymentType(string $message): ?string
    {
        if (preg_match('/\bcr[eé]dito\b/iu', $message)) {
            return 'credito';
        }

        if (preg_match('/\bcontado\b/iu', $message)) {
            return 'contado';
        }

        return null;
    }

    private function extractPaymentMethod(string $message): ?string
    {
        foreach (['efectivo', 'transferencia', 'consignacion', 'consignación', 'tarjeta'] as $method) {
            if (preg_match('/\b'.preg_quote($method, '/').'\b/iu', $message)) {
                return str_replace('ó', 'o', mb_strtolower($method));
            }
        }

        return null;
    }

    private function extractNotes(string $message): ?string
    {
        if (preg_match('/\bobservaci[oó]n(?:es)?\s*:?\s*([^.]+)/iu', $message, $matches)) {
            return trim($matches[1]) ?: null;
        }

        return null;
    }

    private function normalizeAmount(string $raw): string
    {
        // "100.000" (thousands dot) and "100,50" (decimal comma) both
        // occur in natural Spanish phrasing — a dot followed by exactly
        // 3 digits and nothing after is a thousands separator, anything
        // else is treated as a decimal point.
        $raw = preg_replace('/\.(?=\d{3}(\D|$))/u', '', $raw) ?? $raw;

        return str_replace(',', '.', $raw);
    }
}
