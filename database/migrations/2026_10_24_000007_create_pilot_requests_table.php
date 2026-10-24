<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_requests', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->string('institution_type');
            $table->string('state');
            $table->string('district');
            $table->string('contact_name');
            $table->string('contact_position');
            $table->string('email');
            $table->string('phone', 30);
            $table->unsignedInteger('student_count');
            $table->string('age_group');
            $table->text('notes')->nullable();
            $table->string('status')->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_requests');
    }
};
