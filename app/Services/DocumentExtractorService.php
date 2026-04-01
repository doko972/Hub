<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class DocumentExtractorService
{
    // Limite d'extraction : ~50 000 caractères (≈12 500 tokens)
    private const MAX_CHARS = 50000;

    public function extract(UploadedFile $file): array
    {
        $mime     = $file->getMimeType();
        $filename = $file->getClientOriginalName();

        if ($this->isImage($mime)) {
            return $this->extractImage($file, $filename);
        }

        if ($mime === 'application/pdf' || $file->getClientOriginalExtension() === 'pdf') {
            return $this->extractPdf($file, $filename);
        }

        if (in_array($mime, ['text/plain', 'text/csv', 'text/html', 'application/json'])) {
            return $this->extractText($file, $filename);
        }

        throw new \Exception("Type de fichier non supporté : {$mime}");
    }

    // ── IMAGE ──────────────────────────────────────────────────────────────
    private function extractImage(UploadedFile $file, string $filename): array
    {
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $mime   = $file->getMimeType();

        return [
            'type'     => 'image',
            'filename' => $filename,
            'base64'   => "data:{$mime};base64,{$base64}",
            'size'     => $file->getSize(),
        ];
    }

    // ── PDF ────────────────────────────────────────────────────────────────
    private function extractPdf(UploadedFile $file, string $filename): array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($file->getRealPath());
            $pages  = $pdf->getPages();
            $total  = count($pages);
            $text   = '';

            foreach ($pages as $page) {
                $text .= $page->getText() . "\n\n";
                if (mb_strlen($text) >= self::MAX_CHARS) {
                    $text = mb_substr($text, 0, self::MAX_CHARS);
                    break;
                }
            }

            $text      = trim($text);
            $chars     = mb_strlen($text);
            $truncated = $chars >= self::MAX_CHARS;

            if (empty($text)) {
                throw new \Exception("Le PDF ne contient pas de texte extractible (PDF scanné ou protégé).");
            }

            return [
                'type'      => 'pdf',
                'filename'  => $filename,
                'content'   => $text,
                'pages'     => $total,
                'chars'     => $chars,
                'truncated' => $truncated,
                'size'      => $file->getSize(),
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'extractible')) {
                throw $e;
            }
            throw new \Exception("Impossible de lire ce PDF : " . $e->getMessage());
        }
    }

    // ── TEXTE BRUT ─────────────────────────────────────────────────────────
    private function extractText(UploadedFile $file, string $filename): array
    {
        $content   = file_get_contents($file->getRealPath());
        $truncated = mb_strlen($content) > self::MAX_CHARS;
        $content   = mb_substr($content, 0, self::MAX_CHARS);

        return [
            'type'      => 'text',
            'filename'  => $filename,
            'content'   => $content,
            'chars'     => mb_strlen($content),
            'truncated' => $truncated,
            'size'      => $file->getSize(),
        ];
    }

    // ── HELPERS ────────────────────────────────────────────────────────────
    private function isImage(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
    }

    public static function formatForContext(array $extracted): string
    {
        if ($extracted['type'] === 'image') {
            return ''; // Les images sont envoyées directement via vision, pas en texte
        }

        $header = match($extracted['type']) {
            'pdf'  => "📄 Document PDF : **{$extracted['filename']}** ({$extracted['pages']} page(s), {$extracted['chars']} caractères)",
            'text' => "📄 Fichier texte : **{$extracted['filename']}** ({$extracted['chars']} caractères)",
            default => "📄 Fichier : **{$extracted['filename']}**",
        };

        $truncationNote = !empty($extracted['truncated'])
            ? "\n⚠️ *Contenu tronqué à 50 000 caractères (document très long).*"
            : '';

        return "{$header}{$truncationNote}\n\n---\n{$extracted['content']}\n---";
    }
}
