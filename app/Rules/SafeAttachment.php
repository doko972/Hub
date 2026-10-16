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

        $images = $config['image_extensions'] ?? [];

        // guessExtension() déduit l'extension du CONTENU réel, pas du nom.
        if (in_array($extension, $images, true)
            && ! in_array($value->guessExtension(), $images, true)) {
            $fail('Ce fichier annonce une image mais n\'en est pas une.');
        }
    }
}
