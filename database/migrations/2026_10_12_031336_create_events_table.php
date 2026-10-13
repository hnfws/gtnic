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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');                   // Misal: "Pestapora 2026"
            $table->enum('category', ['music', 'bazaar', 'exhibition', 'campus', 'sports']);
            $table->string('location_name');           // Misal: "Gambir Expo Kemayoran"
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('source_url')->nullable();  // Link website/loket
            $table->timestamps();
        });

        // Menambahkan kolom PostGIS Point untuk perhitungan jarak GPS
        DB::statement('ALTER TABLE events ADD COLUMN location geography(Point, 4326)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
