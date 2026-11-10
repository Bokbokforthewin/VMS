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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('reported_by')->constrained('users');
 
            $table->dateTime('occurred_at');
            $table->enum('type', ['accident', 'breakdown', 'violation', 'other']);
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->string('location_note')->nullable();
            $table->text('description');
 
            $table->enum('status', ['reported', 'under_review', 'resolved'])
                ->default('reported');
            $table->text('resolution_notes')->nullable();
            $table->decimal('repair_cost', 10, 2)->nullable();
 
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['vehicle_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
