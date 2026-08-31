<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide la destination d un outil selon sa nature.
 *
 * Une adresse web et un chemin réseau n ont rien de commun : appliquer la règle
 * « url » à un chemin le rejetterait, et accepter n importe quelle chaîne comme
 * adresse laisserait créer des vignettes mortes.
 *
 * Contrôles écrits sans expression régulière : les chemins Windows sont saturés
 * d antislashs, que chaque couche d échappement rend illisibles.
 */
class ToolLink implements ValidationRule
{
    public function __construct(private readonly ?string $type)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valeur = trim((string) $value);

        if ($this->type === "path") {
            if (!self::estCheminReseau($valeur)) {
                $fail("Indiquez un chemin réseau valide, par exemple \\\\serveur\\partage ou H:\\Dossier.");
            }

            return;
        }

        if (!str_starts_with(strtolower($valeur), "http://")
            && !str_starts_with(strtolower($valeur), "https://")) {
            $fail("Indiquez une adresse web commençant par http:// ou https://.");

            return;
        }

        if (!filter_var($valeur, FILTER_VALIDATE_URL)) {
            $fail("Cette adresse web n est pas valide.");
        }
    }

    /**
     * Lettre de lecteur (H:\\…) ou chemin UNC (\\\\serveur\\partage).
     */
    public static function estCheminReseau(string $valeur): bool
    {
        $separateurs = ["\\", "/"];

        $lecteur = strlen($valeur) >= 3
            && ctype_alpha($valeur[0])
            && $valeur[1] === ":"
            && in_array($valeur[2], $separateurs, true);

        // UNC : deux séparateurs, un nom de serveur, puis au moins un partage.
        $unc = strlen($valeur) > 4
            && in_array($valeur[0], $separateurs, true)
            && in_array($valeur[1], $separateurs, true);

        return $lecteur || $unc;
    }
}
