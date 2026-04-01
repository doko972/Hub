<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'intégration : fusionne la base de Chatbot-api dans dashboard_general.
 *
 * - Étend la table users avec les colonnes chatbot (is_admin, google tokens)
 * - Crée la table personal_access_tokens (Sanctum, utilisée par Chatbot-api)
 * - Crée toutes les tables métier du chatbot
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // 1. Étendre la table users
        // ----------------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            // is_admin : dérivé du champ role de dashboard-general
            $table->boolean('is_admin')->default(false)->after('role');

            // Tokens Google OAuth
            $table->string('google_id')->nullable()->after('is_admin');
            $table->text('google_access_token')->nullable()->after('google_id');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->timestamp('google_token_expires_at')->nullable()->after('google_refresh_token');
        });

        // Synchroniser is_admin pour les utilisateurs existants
        DB::statement("UPDATE users SET is_admin = (role = 'admin')");

        // ----------------------------------------------------------------
        // 2. Sanctum : personal_access_tokens
        // ----------------------------------------------------------------
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 3. Dossiers de conversations
        // ----------------------------------------------------------------
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 4. Conversations
        // ----------------------------------------------------------------
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('folder_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 5. Messages
        // ----------------------------------------------------------------
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->boolean('has_image')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 6. Prompts système personnalisés
        // ----------------------------------------------------------------
        Schema::create('system_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('content');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 7. Abonnements Push (notifications web)
        // ----------------------------------------------------------------
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('endpoint');
            $table->string('public_key', 512)->nullable();
            $table->string('auth_token', 512)->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        // ----------------------------------------------------------------
        // 8. Mémoires utilisateur (contexte IA persistant)
        // ----------------------------------------------------------------
        Schema::create('user_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('key', 100);
            $table->text('value');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });

        // ----------------------------------------------------------------
        // 9. Rappels email (Google Calendar)
        // ----------------------------------------------------------------
        Schema::create('email_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('google_event_id');
            $table->string('event_title');
            $table->dateTime('event_start');
            $table->string('event_location')->nullable();
            $table->dateTime('remind_at');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // 10. Releases de l'app desktop
        // ----------------------------------------------------------------
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->string('platform');
            $table->text('changelog');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('signature')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();
            $table->unique(['version', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('email_reminders');
        Schema::dropIfExists('user_memories');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('system_prompts');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('personal_access_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'google_id',
                'google_access_token',
                'google_refresh_token',
                'google_token_expires_at',
            ]);
        });
    }
};
