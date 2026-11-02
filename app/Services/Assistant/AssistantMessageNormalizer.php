<?php

namespace App\Services\Assistant;

/**
 * Two independent, deliberately narrow normalizations applied before the
 * deterministic dispatcher runs:
 *
 * - canonicalize(): rewrites a small, explicit table of synonym phrases
 *   ("dame" / "quiero ver" / "cuánto iva llevo" / ...) into the single
 *   canonical wording the rest of AiAssistantService already recognises.
 *   This is the "mantenible" alternative to scattering every new synonym
 *   across each keyword list separately — one table, one place to extend.
 *
 * - correctVocabulary(): safe, bounded typo tolerance. Only ever touches
 *   a whole word that is NOT already in the fixed intent vocabulary, is
 *   at least 4 characters, contains no digit or hyphen, and is within
 *   Levenshtein distance 1 of exactly one vocabulary word. It never runs
 *   on tokens that look like identifiers (FV-000002, NIT, SKU — anything
 *   with a digit or hyphen), so it can never "correct" a customer name,
 *   document number, or code. Called only as a fallback retry when the
 *   normal, uncorrected message produced zero tool results — never as
 *   the primary interpretation — so it can't silently change behaviour
 *   for a message that already matched something real.
 *
 * - suggestVocabularyCorrection(): the next, strictly lower-confidence
 *   tier — distance exactly 2, length 5+. Deliberately never used to
 *   pick a word to then dispatch with: a
 *   distance-2 guess is only ever surfaced back to the user as a
 *   question ("¿te refieres a…?"). This exists so a badly misspelled
 *   intent word never gets silently treated as "query ran, zero
 *   results" — see AiAssistantService::toolResults() for the three-tier
 *   policy (exact/synonym → safe-correct-and-run → ask-don't-guess).
 */
class AssistantMessageNormalizer
{
    /**
     * Pattern => canonical replacement. `^`-anchored entries only rewrite
     * a leading command verb (safe: a real entity name is never going to
     * be the very first word of a message that starts with "busca"/
     * "muéstrame"/etc.); the rest are distinctive multi-word phrases that
     * are not realistic substrings of a customer/product name.
     *
     * @var array<string, string>
     */
    private const SYNONYM_MAP = [
        '/^dame\s+/iu' => 'muéstrame ',
        '/^quiero\s+ver\s+/iu' => 'muéstrame ',
        '/^ense[ñn]ame\s+/iu' => 'muéstrame ',
        '/\blo\s+facturado\s+a\b/iu' => 'facturas de',
        // "Muéstrame lo de Juan" ≈ "muéstrame facturas de Juan" — the
        // dominant meaning of "lo de <alguien>" in this domain's chat.
        '/^mu[eé]strame\s+lo\s+de\s+/iu' => 'muéstrame facturas de ',
        '/\bcu[aá]nt[oa]\s+iva\s+(llevo|tengo|acumulo|acumulado)\b/iu' => 'cuánto iva generado',
        '/\bcu[aá]nto\s+tengo\s+de\s+iva\b/iu' => 'cuánto iva generado',
        '/\biva\s+que\s+(llevo|tengo)\b/iu' => 'iva generado',
        '/\bdime\s+el\s+iva\s+generado\b/iu' => 'iva generado',
        '/\bqu[eé]\s+iva\s+se\s+ha\s+causado\b/iu' => 'iva generado',
        '/\biva\s+causado\b/iu' => 'iva generado',
        // "IVA al 19%" / "que tengan IVA 19%" / "gravados con IVA 19%" /
        // "con impuesto IVA 19%" — all collapse to the one "con iva N%"
        // shape the tax-rate extractor and cleanResidualTerm() already
        // understand, instead of each needing its own regex downstream.
        '/\biva\s+al\s+(\d+(?:[.,]\d+)?\s*%?)/iu' => 'iva $1',
        '/\b(?:que\s+tengan|tengan|gravados?\s+con|con\s+impuesto)\s+iva\b/iu' => 'con iva',
    ];

    /**
     * @var array<int, string>
     */
    private const INTENT_VOCABULARY = [
        'cuanto', 'cuánto', 'cuanta', 'cuánta', 'cuantos', 'cuántos', 'cuantas', 'cuántas',
        'factura', 'facturas', 'cliente', 'clientes', 'producto', 'productos',
        'servicio', 'servicios', 'iva', 'impuesto', 'impuestos', 'borrador', 'borradores',
        'nota', 'notas', 'credito', 'crédito', 'trazabilidad', 'pendiente', 'pendientes',
        'anulada', 'anuladas', 'emitida', 'emitidas', 'facturado', 'facturacion', 'facturación',
        'tiene', 'tienen', 'generado', 'muestrame', 'muéstrame', 'busca', 'documentos', 'documento',
    ];

    public function canonicalize(string $message): string
    {
        foreach (self::SYNONYM_MAP as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return $message;
    }

    /**
     * Returns a corrected copy of $message, or null if nothing needed
     * correcting (so the caller can tell "no change" apart from "changed
     * to something identical").
     */
    public function correctVocabulary(string $message): ?string
    {
        $tokens = preg_split('/(\s+)/u', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return null;
        }

        $changed = false;

        foreach ($tokens as $index => $token) {
            $corrected = $this->correctToken($token);

            if ($corrected !== null) {
                $tokens[$index] = $corrected;
                $changed = true;
            }
        }

        return $changed ? implode('', $tokens) : null;
    }

    private function correctToken(string $token): ?string
    {
        $plain = $this->plainCore($token);

        if ($plain === null || mb_strlen($plain) < 4) {
            return null;
        }

        $closest = $this->closestVocabularyWord($plain, maxDistance: 1);

        if ($closest === null) {
            return null;
        }

        preg_match('/^(\p{P}*)(.*?)(\p{P}*)$/u', $token, $matches);

        return $matches[1].$closest.$matches[3];
    }

    /**
     * Strictly lower-confidence than correctToken(): distance exactly 2,
     * and a higher length floor (5, not 4) since a 2-edit tolerance on a
     * short word matches far too many unrelated words to be safe. Never
     * returns a word that correctToken() would already have accepted —
     * callers only reach this once the distance<=1 pass has already come
     * back empty — and the caller must never dispatch using this guess,
     * only ask the user to confirm it.
     */
    public function suggestVocabularyCorrection(string $message): ?string
    {
        $tokens = preg_split('/\s+/u', $message, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return null;
        }

        foreach ($tokens as $token) {
            $plain = $this->plainCore($token);

            if ($plain === null || mb_strlen($plain) < 5) {
                continue;
            }

            $suggestion = $this->closestVocabularyWord($plain, maxDistance: 2, minDistance: 2);

            if ($suggestion !== null) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * Strips leading/trailing punctuation and lowercases, or null for an
     * identifier-like token (contains a digit/hyphen) or one already in
     * the vocabulary — the two cases no correction tier should ever
     * touch.
     */
    private function plainCore(string $token): ?string
    {
        if (! preg_match('/^\p{P}*(.*?)\p{P}*$/u', $token, $matches)) {
            return null;
        }

        $plain = mb_strtolower($matches[1]);

        if ($plain === '' || preg_match('/[\d\-]/u', $plain) || in_array($plain, self::INTENT_VOCABULARY, true)) {
            return null;
        }

        return $plain;
    }

    private function closestVocabularyWord(string $plain, int $maxDistance, int $minDistance = 0): ?string
    {
        $closest = null;
        $closestDistance = null;

        foreach (self::INTENT_VOCABULARY as $candidate) {
            $distance = $this->editDistance($plain, $candidate);

            if ($distance < $minDistance || $distance > $maxDistance) {
                continue;
            }

            if ($closestDistance === null || $distance < $closestDistance) {
                $closest = $candidate;
                $closestDistance = $distance;
            }
        }

        return $closest;
    }

    /**
     * Optimal-string-alignment (restricted Damerau-Levenshtein) distance:
     * same as PHP's built-in levenshtein(), plus adjacent-transposition
     * as a single edit. PHP's native levenshtein() treats a transposed
     * pair ("factruas" vs "facturas", "clietne" vs "cliente") as distance
     * 2, which the fixed ≤1 threshold would reject — yet a swapped
     * adjacent pair is one of the most common real typos, so it is
     * deliberately counted as a single edit here.
     */
    private function editDistance(string $a, string $b): int
    {
        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);
        $d = [];

        for ($i = 0; $i <= $lenA; $i++) {
            $d[$i][0] = $i;
        }

        for ($j = 0; $j <= $lenB; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = mb_substr($a, $i - 1, 1) === mb_substr($b, $j - 1, 1) ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,
                    $d[$i][$j - 1] + 1,
                    $d[$i - 1][$j - 1] + $cost,
                );

                if (
                    $i > 1 && $j > 1
                    && mb_substr($a, $i - 1, 1) === mb_substr($b, $j - 2, 1)
                    && mb_substr($a, $i - 2, 1) === mb_substr($b, $j - 1, 1)
                ) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$lenA][$lenB];
    }
}
