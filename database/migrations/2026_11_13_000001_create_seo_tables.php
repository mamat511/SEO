<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jetons OAuth Google (un compte Google par utilisateur).
        Schema::create('google_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url'); // URL de départ du site, ex. https://example.com/
            $table->string('gsc_property')->nullable(); // ex. sc-domain:example.com ou https://example.com/
            $table->timestamp('gsc_synced_at')->nullable();
            $table->timestamps();
        });

        // Totaux quotidiens du site (Search Console).
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->float('ctr')->default(0);
            $table->float('position')->nullable();
            $table->unique(['project_id', 'date']);
        });

        // Mots-clés suivis.
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->timestamps();
            $table->unique(['project_id', 'query']);
        });

        // Historique quotidien par mot-clé (Search Console).
        Schema::create('keyword_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->float('position')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->string('top_page')->nullable();
            $table->unique(['keyword_id', 'date']);
        });

        // Imports CSV du rapport « Liens » de la Search Console.
        Schema::create('backlink_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // domains | links
            $table->string('filename');
            $table->unsignedInteger('rows_count')->default(0);
            $table->timestamps();
        });

        Schema::create('backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlink_import_id')->constrained()->cascadeOnDelete();
            $table->string('source'); // domaine référent ou URL de la page source
            $table->string('domain')->index();
            $table->unsignedInteger('linking_pages')->nullable();
            $table->unsignedInteger('target_pages')->nullable();
            $table->date('last_crawled')->nullable();
            $table->unique(['backlink_import_id', 'source']);
        });

        // Audits techniques (crawls).
        Schema::create('crawls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | running | done | failed
            $table->unsignedInteger('max_pages')->default(500);
            $table->unsignedInteger('pages_count')->default(0);
            $table->unsignedInteger('issues_count')->default(0);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crawl_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('redirect_to', 2048)->nullable();
            $table->string('title', 1024)->nullable();
            $table->string('meta_description', 2048)->nullable();
            $table->unsignedSmallInteger('h1_count')->nullable();
            $table->string('canonical', 2048)->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('response_ms')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->json('issues')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['crawl_pages', 'crawls', 'backlinks', 'backlink_imports', 'keyword_metrics', 'keywords', 'daily_metrics', 'projects', 'google_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
