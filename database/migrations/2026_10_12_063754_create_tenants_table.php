<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade'); // Terhubung ke events
            $table->enum('tenant_type', ['direct_rent', 'curated_call']);      // Sewa langsung vs Kurasi EO
            $table->unsignedBigInteger('price')->nullable();                    // Rp 1.500.000 (jika ada)
            $table->text('requirements')->nullable();                           // "Khusus F&B, Tenda 2x2m"
            $table->string('registration_link')->nullable();                    // Link GForm di Bio IG
            $table->string('instagram_post_url')->nullable();                   // Link postingan IG sumber scraper
            $table->dateTime('deadline_date')->nullable();                      // Batas akhir pendaftaran
            $table->string('contact_person')->nullable();                       // No WA/Contact EO
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_tenants');
    }
};
