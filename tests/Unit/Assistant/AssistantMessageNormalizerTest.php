<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\AssistantMessageNormalizer;
use Tests\TestCase;

class AssistantMessageNormalizerTest extends TestCase
{
    private function normalizer(): AssistantMessageNormalizer
    {
        return new AssistantMessageNormalizer;
    }

    // --- canonicalize(): synonym phrasing -----------------------------------

    public function test_dame_is_canonicalized_to_muestrame(): void
    {
        $this->assertSame('muéstrame las facturas de kata', $this->normalizer()->canonicalize('dame las facturas de kata'));
    }

    public function test_quiero_ver_lo_facturado_a_becomes_muestrame_facturas_de(): void
    {
        $result = $this->normalizer()->canonicalize('Quiero ver lo facturado a kata');

        $this->assertStringContainsString('muéstrame', mb_strtolower($result));
        $this->assertStringContainsString('facturas de kata', mb_strtolower($result));
    }

    public function test_cuanto_iva_llevo_becomes_cuanto_iva_generado(): void
    {
        $this->assertStringContainsString('iva generado', mb_strtolower($this->normalizer()->canonicalize('¿Cuánto IVA llevo?')));
    }

    public function test_cuanto_tengo_de_iva_style_phrases_are_not_forced_if_unmatched(): void
    {
        // Only the exact documented phrasings are rewritten — this is not
        // a general paraphraser. An unmatched sentence passes through
        // unchanged rather than being guessed at.
        $message = 'Hola, buenos días';

        $this->assertSame($message, $this->normalizer()->canonicalize($message));
    }

    public function test_que_iva_se_ha_causado_becomes_iva_generado(): void
    {
        $this->assertStringContainsString('iva generado', mb_strtolower($this->normalizer()->canonicalize('¿Qué IVA se ha causado?')));
    }

    public function test_dame_is_only_rewritten_at_the_start_of_the_message(): void
    {
        // Anchored to the start on purpose — "dame" appearing later in a
        // sentence (e.g. as part of a name) must never be rewritten.
        $message = 'Busca el cliente "Siempre Dame Una Mano SAS"';

        $this->assertSame($message, $this->normalizer()->canonicalize($message));
    }

    // --- canonicalize(): IVA tax-rate phrasing variants ---------------------

    public function test_iva_al_19_percent_drops_the_al_connector(): void
    {
        $result = mb_strtolower($this->normalizer()->canonicalize('productos con iva al 19%'));

        $this->assertStringContainsString('iva 19%', $result);
        $this->assertStringNotContainsString('iva al', $result);
    }

    public function test_que_tengan_iva_becomes_con_iva(): void
    {
        $result = mb_strtolower($this->normalizer()->canonicalize('productos que tengan iva 19%'));

        $this->assertStringContainsString('con iva 19%', $result);
    }

    public function test_gravados_con_iva_becomes_con_iva(): void
    {
        $result = mb_strtolower($this->normalizer()->canonicalize('productos gravados con iva 19%'));

        $this->assertStringContainsString('con iva 19%', $result);
        $this->assertStringNotContainsString('gravado', $result);
    }

    public function test_con_impuesto_iva_becomes_con_iva(): void
    {
        $result = mb_strtolower($this->normalizer()->canonicalize('productos con impuesto iva 19%'));

        $this->assertStringContainsString('con iva 19%', $result);
        $this->assertStringNotContainsString('impuesto', $result);
    }

    // --- correctVocabulary(): bounded typo tolerance ------------------------

    public function test_corrects_a_single_letter_insertion_in_a_command_word(): void
    {
        $corrected = $this->normalizer()->correctVocabulary('cuantao iva llevo');

        $this->assertNotNull($corrected);
        $this->assertStringContainsString('cuanto', mb_strtolower($corrected));
    }

    public function test_corrects_factruas_to_facturas(): void
    {
        $corrected = $this->normalizer()->correctVocabulary('muestrame las factruas emitidas');

        $this->assertNotNull($corrected);
        $this->assertStringContainsString('facturas', mb_strtolower($corrected));
    }

    public function test_corrects_clietne_to_cliente(): void
    {
        $corrected = $this->normalizer()->correctVocabulary('busca el clietne kata');

        $this->assertNotNull($corrected);
        $this->assertStringContainsString('cliente', mb_strtolower($corrected));
    }

    public function test_returns_null_when_nothing_needs_correcting(): void
    {
        $this->assertNull($this->normalizer()->correctVocabulary('muéstrame las facturas de kata'));
    }

    public function test_never_touches_a_token_that_contains_a_digit(): void
    {
        // "FV-000002"-like tokens, NITs, SKUs — anything with a digit —
        // must never be "corrected", even if it happens to be close in
        // edit distance to a vocabulary word.
        $corrected = $this->normalizer()->correctVocabulary('busca FV-000002');

        $this->assertNull($corrected);
    }

    public function test_never_touches_a_short_word(): void
    {
        // "IVA" itself (3 letters) is never a candidate for correction as
        // an INPUT token — only words of 4+ letters are considered, to
        // avoid false positives on short, common words.
        $corrected = $this->normalizer()->correctVocabulary('iva del mes');

        $this->assertNull($corrected);
    }

    public function test_never_corrects_a_customer_like_name_far_from_any_vocabulary_word(): void
    {
        $corrected = $this->normalizer()->correctVocabulary('busca el cliente Bucaramanga');

        $this->assertNull($corrected);
    }

    public function test_never_corrects_a_word_several_edits_away_from_any_vocabulary_word(): void
    {
        // "clientada" is several edits away from "cliente" — only
        // distance <= 1 is considered safe enough to correct.
        $corrected = $this->normalizer()->correctVocabulary('clientada kata');

        $this->assertNull($corrected);
    }

    // --- suggestVocabularyCorrection(): low-confidence tier (distance 2) ---

    public function test_suggests_borradores_for_a_distance_2_typo(): void
    {
        // "barradires" is two edits away from "borradores" — too far for
        // correctVocabulary()'s safe tier, but still plausibly a typo of
        // it. This tier only ever suggests, never auto-corrects.
        $this->assertNull($this->normalizer()->correctVocabulary('barradires'));
        $this->assertSame('borradores', $this->normalizer()->suggestVocabularyCorrection('barradires de este mes'));
    }

    public function test_suggestion_returns_null_for_an_unrelated_word_far_from_any_vocabulary(): void
    {
        $this->assertNull($this->normalizer()->suggestVocabularyCorrection('bpotrraodres de est mes'));
    }

    public function test_suggestion_never_touches_a_token_that_contains_a_digit(): void
    {
        $this->assertNull($this->normalizer()->suggestVocabularyCorrection('busca FV-000002'));
    }
}
