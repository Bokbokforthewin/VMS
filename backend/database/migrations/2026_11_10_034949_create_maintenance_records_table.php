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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->enum('type', ['preventive', 'repair']);
            $table->foreignId('service_id')->nullable()->constrained('maintenance_records')->nullOnDelete();
            $table->date('service_date');
            $table->string('shop_name')->nullable();
            $table->string('part_name')->nullable();
            $table->enum('part_action', ['installed', 'removed'])->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'service_date']);
            $table->index('next_due_date');
        });
 
        // part rows must have the part columns; service rows must not
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement(
                "ALTER TABLE maintenance_records ADD CONSTRAINT maintenance_part_columns CHECK ("
                . "(type = 'part' AND part_name IS NOT NULL AND part_action IS NOT NULL AND quantity IS NOT NULL) OR "
                . "(type IN ('preventive', 'repair') AND part_name IS NULL AND part_action IS NULL AND quantity IS NULL))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
