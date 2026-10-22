<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Contrôle d'une pièce jointe de la messagerie.
 *
 * Remplace le couple mimes/extensions, qui reposait sur une liste blanche :
 * celle-ci refusait sans raison des fichiers techniques courants et devait
 * être complétée à chaque nouveau besoin.
 *
 * Deux règles seulement, chacune pour un risque précis :
 *
 *  - les formats qu'un double-clic lance sont refusés, pour que la messagerie
 *    ne puisse pas servir à diffuser un programme depuis un compte de
 *    confiance ;
 *  - un fichier qui s'annonce comme une image doit en être une, sans quoi un
 *    script renommé en .jpg s'afficherait comme une photo dans le fil.
 *
 * Le reste est téléchargé sans jamais être exécuté : les fichiers vivent hors
 * du dossier public et ne sont servis que par une route authentifiée, avec
 * Content-Disposition: attachment.
 */
class SafeAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Fichier invalide.');

            return;
        }

        $config = config('messaging.attachments');

        // getClientOriginalExtension ne retient que ce qui suit le DERNIER
        // point : « facture.pdf.exe » est bien vu comme un .exe.
        $extension = strtolower($value->getClientOriginalExtension());

        if (in_array($extension, $config['blocked_extensions'] ?? [], true)) {
            $fail('Les programmes exécutables ne peuvent pas être envoyés ici.');

            return;
        }

        // Hors des images, le nom annoncé ne promet rien de vérifiable : le
        // fichier sera téléchargé tel quel, sans jamais être interprété.
        if (! in_array($extension, $config['image_extensions'] ?? [], true)) {
            return;
        }

        $type = $this->typeReel($value);

        // « image/ » suffit, plutôt qu'une liste close de types : finfo n'écrit
        // pas toujours le même type pour un même format (un BMP ressort en
        // image/bmp ou image/x-ms-bmp selon les systèmes), et une liste close
        // finirait par refuser de vraies photos. Ce qu'on vise ici est un
        // script ou un exécutable maquillé en image, qui ne ressort jamais en
        // image/quoi-que-ce-soit.
        //
        // L'affichage dans le fil reste gouverné par inline_mimes, appliqué au
        // type réellement détecté : rien ne s'affiche sans en être vraiment un.
        if ($type === null || ! str_starts_with($type, 'image/')) {
            $fail('Ce fichier annonce une image mais n\'en est pas une.');
        }
    }

    /**
     * Type réel du fichier, déduit de son contenu — null s'il est illisible.
     *
     * On interroge finfo directement plutôt que par guessExtension() : sur un
     * fichier illisible, celui-ci laisse remonter un avertissement que Laravel
     * transforme en exception, et un envoi douteux rendait alors une erreur 500
     * au lieu d'un refus de validation.
     *
     * Un contenu indéchiffrable est refusé, jamais accepté par défaut.
     */
    private function typeReel(UploadedFile $fichier): ?string
    {
        $type = @(new \finfo(FILEINFO_MIME_TYPE))->file($fichier->getPathname());

        return is_string($type) && $type !== '' ? $type : null;
    }
}
