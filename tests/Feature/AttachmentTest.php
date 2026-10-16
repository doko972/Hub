<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DiscussionAttachment::DISK);
    }

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

        return [$alice, $bob, Discussion::findOrCreateDirect($alice->id, $bob->id)];
    }

    public function test_un_fichier_est_stocke_hors_du_dossier_public(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $response = $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'body'        => 'Voici le rapport',
            'attachments' => [UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf')],
        ]);

        $response->assertStatus(201)->assertJsonPath('message.attachments.0.name', 'rapport.pdf');

        $attachment = DiscussionAttachment::firstOrFail();

        Storage::disk(DiscussionAttachment::DISK)->assertExists($attachment->path);

        // Le nom d'origine ne doit jamais devenir le nom sur le disque.
        $this->assertStringNotContainsString('rapport', $attachment->path);
        $this->assertSame('rapport.pdf', $attachment->original_name);

        // Rien ne doit atterrir dans public/.
        $this->assertFileDoesNotExist(public_path('storage/' . $attachment->path));
    }

    public function test_un_fichier_de_configuration_est_accepte(): void
    {
        [$alice, , $discussion] = $this->conversation();

        // Un .conf est du texte : la vérification de contenu le voit comme tel,
        // et c'est la liste blanche d'extensions qui décide de l'accepter.
        $fichier = UploadedFile::fake()->createWithContent(
            'extensions.conf',
            "[general]\nstatic=yes\nwriteprotect=no\n"
        );

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), ['attachments' => [$fichier]])
            ->assertCreated();

        $this->assertDatabaseHas('discussion_attachments', ['original_name' => 'extensions.conf']);
    }

    public function test_un_message_peut_n_etre_qu_une_piece_jointe(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->image('photo.jpg')],
        ])->assertStatus(201);

        $this->assertDatabaseCount('discussion_attachments', 1);
    }

    public function test_un_message_totalement_vide_est_refuse(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    // ---- Contrôles de sécurité ----

    public function test_un_fichier_qui_s_annonce_image_doit_en_etre_une(): void
    {
        [$alice, , $discussion] = $this->conversation();

        // Un script renommé en .jpg s'afficherait comme une photo dans le fil :
        // c'est le seul cas où le contenu réel est confronté au nom annoncé.
        //
        // Vrai fichier, et non UploadedFile::fake() : sur un fichier factice le
        // type est déduit du NOM, ce qui ne prouverait rien ici — c'est
        // justement le nom qui ment.
        $chemin = tempnam(sys_get_temp_dir(), 'piece');
        file_put_contents($chemin, '<?php system($_GET["c"]); ?>');

        $this->actingAs($alice)->postJson(route('messages.send', $discussion), [
            'attachments' => [new UploadedFile($chemin, 'shell.jpg', null, null, true)],
        ])->assertStatus(422);

        // Le même déguisement, avec un type annoncé explicitement.
        $this->actingAs($alice)->postJson(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('shell.jpg', 10, 'application/x-php')],
        ])->assertStatus(422);

        $this->assertDatabaseCount('discussion_attachments', 0);

        @unlink($chemin);
    }

    public function test_les_fichiers_techniques_passent_et_restent_telecharges(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        // Rien de tout cela ne peut s'exécuter : les fichiers vivent hors du
        // dossier public et ne sont servis qu'en téléchargement.
        foreach (['notes.xml', 'donnees.json', 'script.js', 'page.html', 'logo.svg', 'requete.sql'] as $nom) {
            $this->actingAs($alice)
                ->postJson(route('messages.send', $discussion), [
                    'attachments' => [UploadedFile::fake()->createWithContent($nom, 'contenu')],
                ])
                ->assertCreated();
        }

        $this->assertDatabaseCount('discussion_attachments', 6);

        // Aucun n'est rendu par le navigateur, quel que soit son type.
        foreach (DiscussionAttachment::all() as $piece) {
            $reponse = $this->actingAs($bob)->get(route('messages.attachment', $piece));

            $this->assertStringContainsString(
                'attachment;',
                $reponse->headers->get('Content-Disposition'),
                "{$piece->original_name} devrait être téléchargé, jamais affiché."
            );
        }
    }

    public function test_un_executable_est_refuse(): void
    {
        [$alice, , $discussion] = $this->conversation();

        foreach (['installeur.exe', 'tache.bat', 'outil.ps1', 'archive.jar'] as $nom) {
            $this->actingAs($alice)
                ->postJson(route('messages.send', $discussion), [
                    'attachments' => [UploadedFile::fake()->createWithContent($nom, 'MZ...')],
                ])
                ->assertStatus(422);
        }

        // Seule la dernière extension compte : le déguisement classique.
        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), [
                'attachments' => [UploadedFile::fake()->createWithContent('facture.pdf.exe', 'MZ...')],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('discussion_attachments', 0);
    }

    public function test_les_limites_de_taille_et_de_nombre_sont_appliquees(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $maxKb    = config('messaging.attachments.max_size_kb');
        $maxFiles = config('messaging.attachments.max_files');

        $this->actingAs($alice)->postJson(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('enorme.pdf', $maxKb + 1, 'application/pdf')],
        ])->assertStatus(422);

        $trop = collect(range(1, $maxFiles + 1))
            ->map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg"))
            ->all();

        $this->actingAs($alice)->postJson(route('messages.send', $discussion), [
            'attachments' => $trop,
        ])->assertStatus(422);

        $this->assertDatabaseCount('discussion_attachments', 0);
    }

    // ---- Téléchargement ----

    public function test_un_participant_telecharge_la_piece_jointe(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('notes.txt', 5, 'text/plain')],
        ]);

        $attachment = DiscussionAttachment::firstOrFail();

        $response = $this->actingAs($bob)->get(route('messages.attachment', $attachment));

        $response->assertStatus(200);
        // Un fichier non-image est téléchargé, jamais rendu par le navigateur.
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('notes.txt', $response->headers->get('Content-Disposition'));
    }

    public function test_une_image_est_servie_pour_affichage(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $attachment = DiscussionAttachment::firstOrFail();

        $this->assertTrue($attachment->isInlineImage());

        $this->actingAs($alice)
            ->get(route('messages.attachment', $attachment))
            ->assertStatus(200)
            ->assertHeader('Content-Disposition', 'inline; filename="photo.jpg"');
    }

    public function test_un_tiers_ne_peut_pas_telecharger_la_piece_jointe(): void
    {
        [$alice, , $discussion] = $this->conversation();
        $intrus = User::factory()->create(['is_active' => true]);

        $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('confidentiel.pdf', 10, 'application/pdf')],
        ]);

        $attachment = DiscussionAttachment::firstOrFail();

        $this->actingAs($intrus)
            ->get(route('messages.attachment', $attachment))
            ->assertForbidden();
    }

    public function test_un_visiteur_anonyme_ne_peut_pas_telecharger_la_piece_jointe(): void
    {
        [$alice, , $discussion] = $this->conversation();

        // Pièce jointe créée sans passer par une requête, pour que le test
        // reste réellement non authentifié (actingAs persiste d'un appel à
        // l'autre au sein d'un même test).
        $message = $discussion->messages()->create(['user_id' => $alice->id, 'body' => '']);

        $attachment = $message->attachments()->create([
            'path'          => 'discussions/1/secret.pdf',
            'original_name' => 'secret.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 1024,
        ]);

        $this->get(route('messages.attachment', $attachment))->assertRedirect('/login');
    }

    public function test_supprimer_la_piece_jointe_efface_le_fichier(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('temporaire.txt', 5, 'text/plain')],
        ]);

        $attachment = DiscussionAttachment::firstOrFail();
        $chemin     = $attachment->path;

        $attachment->delete();

        Storage::disk(DiscussionAttachment::DISK)->assertMissing($chemin);
    }
}
