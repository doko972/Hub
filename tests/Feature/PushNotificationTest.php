<?php

namespace Tests\Feature;

use App\Jobs\NotifyNewDiscussionMessage;
use App\Models\Discussion;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webpush.public_key'  => 'cle-publique-de-test',
            'services.webpush.private_key' => 'cle-privee-de-test',
            'services.webpush.subject'     => 'http://localhost',
        ]);
    }

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

        return [$alice, $bob, Discussion::findOrCreateDirect($alice->id, $bob->id)];
    }

    public function test_l_envoi_d_un_message_declenche_la_notification(): void
    {
        Bus::fake();

        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), ['body' => 'Bonjour'])
            ->assertStatus(201);

        // Dispatché après la réponse : l'envoi ne retarde pas l'affichage.
        Bus::assertDispatchedAfterResponse(NotifyNewDiscussionMessage::class);
    }

    public function test_le_destinataire_est_notifie_mais_pas_l_auteur(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        $message = $discussion->messages()->create([
            'user_id' => $alice->id,
            'body'    => 'Peux-tu relire le document ?',
        ]);

        $push = Mockery::mock(PushService::class);
        $push->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function ($userId, $titre, $corps, $url, $options) use ($bob, $discussion) {
                return $userId === $bob->id
                    && $titre === 'Alice'
                    && str_contains($corps, 'relire le document')
                    && $url === "/messages/{$discussion->id}"
                    && $options['tag'] === "discussion-{$discussion->id}";
            });

        (new NotifyNewDiscussionMessage($message->id))->handle($push);
    }

    public function test_en_groupe_le_titre_est_celui_du_fil_et_le_corps_nomme_l_auteur(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

        $groupe = Discussion::create([
            'name'       => 'Équipe support',
            'is_group'   => true,
            'created_by' => $alice->id,
        ]);
        $groupe->participants()->attach([$alice->id, $bob->id]);

        $message = $groupe->messages()->create(['user_id' => $alice->id, 'body' => 'Réunion à 14h']);

        $push = Mockery::mock(PushService::class);
        $push->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn ($userId, $titre, $corps) =>
                $userId === $bob->id
                && $titre === 'Équipe support'
                && $corps === 'Alice : Réunion à 14h');

        (new NotifyNewDiscussionMessage($message->id))->handle($push);
    }

    public function test_aucun_envoi_sans_cles_vapid(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        [$alice, , $discussion] = $this->conversation();
        $message = $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Test']);

        $push = Mockery::mock(PushService::class);
        $push->shouldNotReceive('sendToUser');

        (new NotifyNewDiscussionMessage($message->id))->handle($push);

        $this->assertFalse(PushService::isConfigured());
    }

    public function test_un_message_supprime_entre_temps_n_interrompt_rien(): void
    {
        $push = Mockery::mock(PushService::class);
        $push->shouldNotReceive('sendToUser');

        (new NotifyNewDiscussionMessage(999999))->handle($push);

        $this->addToAssertionCount(1);
    }

    // ---- Abonnement depuis une page authentifiée par session ----

    public function test_la_cle_publique_est_exposee_a_un_utilisateur_connecte(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->getJson(route('push.vapid'))
            ->assertStatus(200)
            ->assertJson(['public_key' => 'cle-publique-de-test']);
    }

    public function test_un_abonnement_s_enregistre_puis_se_supprime(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $abonnement = [
            'endpoint'   => 'https://fcm.googleapis.com/fcm/send/abc123',
            'public_key' => 'p256dh-de-test',
            'auth_token' => 'auth-de-test',
        ];

        $this->actingAs($user)->postJson(route('push.subscribe'), $abonnement)->assertStatus(200);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id'  => $user->id,
            'endpoint' => $abonnement['endpoint'],
        ]);

        $this->actingAs($user)
            ->postJson(route('push.unsubscribe'), ['endpoint' => $abonnement['endpoint']])
            ->assertStatus(200);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_on_ne_supprime_pas_l_abonnement_d_un_autre(): void
    {
        $proprietaire = User::factory()->create(['is_active' => true]);
        $intrus       = User::factory()->create(['is_active' => true]);

        PushSubscription::create([
            'user_id'    => $proprietaire->id,
            'endpoint'   => 'https://fcm.googleapis.com/fcm/send/xyz',
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        $this->actingAs($intrus)
            ->postJson(route('push.unsubscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz'])
            ->assertStatus(200);

        // La requête aboutit, mais ne touche pas l'abonnement d'autrui.
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_les_routes_push_sont_fermees_aux_visiteurs_anonymes(): void
    {
        $this->get(route('push.vapid'))->assertRedirect('/login');
        $this->post(route('push.subscribe'))->assertRedirect('/login');
    }
}
