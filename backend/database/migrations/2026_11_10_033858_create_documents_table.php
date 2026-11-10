<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->enum('doc_type', ['vehicle', 'driver']);
            $table->unsignedBigInteger('owner_id');
            $table->enum('type', ['registration', 'insurance', 'license', 'other']);
            $table->boolean('renewal')->default(false);
            $table->date('expires_at');
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['doc_type', 'owner_id', 'type']);
            $table->index('expires_at');
        });
 
        // license is for drivers; registration and insurance are for vehicles
        if (in_array(DB::getDriverName(), ['mysql'])) {
            DB::statement(
                "ALTER TABLE documents ADD CONSTRAINT documents_type_matches_doc_type CHECK ("
                . "(doc_type = 'driver' AND type = 'license') OR "
                . "(doc_type = 'vehicle' AND type IN ('registration', 'insurance')))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
