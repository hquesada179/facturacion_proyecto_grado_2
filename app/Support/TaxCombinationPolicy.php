<?php

namespace App\Support;

use App\Models\Tax;
use Illuminate\Support\Collection;

/**
 * Decides whether a set of taxes assigned to the same product or
 * invoice line makes business sense together. Shared by the form-level
 * Rule (App\Rules\ValidTaxCombination, checked at submission time) and
 * the ValidationEngine rule (checked again at the final "Validar
 * factura" step) so the two layers can never disagree.
 *
 * Only two things are rejected, both already present in the seeded tax
 * catalog (IVA19/IVA5/IVA0/EXENTO/EXCLUIDO/INC8):
 *
 *  - More than one tax whose nature is "IVA". Exento is itself an
 *    IVA-nature tax (condition=exento), so this single rule already
 *    covers "two different IVA rates at once" AND "Exento + IVA
 *    gravado" without needing a second, separate check.
 *  - "Excluido" (condition=excluido) together with any IVA-nature tax:
 *    an excluded item sits entirely outside the VAT system, so no
 *    IVA-nature tax — gravado or exento — can apply to it at the same
 *    time.
 *
 * INC is independent of all of the above and may freely combine with
 * either IVA or Excluido — that combination is common in practice and
 * nothing in this prototype's scope asks for it to be blocked.
 */
final class TaxCombinationPolicy
{
    /**
     * @param  iterable<Tax>  $taxes
     */
    public static function violationFor(iterable $taxes): ?string
    {
        $taxes = Collection::make($taxes);

        $ivaTaxes = $taxes->filter(fn (Tax $tax): bool => $tax->nature === 'IVA');

        if ($ivaTaxes->count() > 1) {
            return 'No puedes combinar varios impuestos de tipo IVA (incluye Exento) en el mismo producto o línea: '
                .$ivaTaxes->pluck('code')->filter()->implode(', ').'.';
        }

        $hasExcluido = $taxes->contains(fn (Tax $tax): bool => $tax->condition === 'excluido');

        if ($hasExcluido && $ivaTaxes->isNotEmpty()) {
            return '"Excluido" no se puede combinar con un impuesto de tipo IVA en el mismo producto o línea.';
        }

        return null;
    }
}
