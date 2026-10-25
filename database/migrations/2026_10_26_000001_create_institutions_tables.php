<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D: institutions (tadika, sekolah agama, KAFA, tahfiz), their yearly or
 * pilot licences set up by the ASUH admin, and e-mail invitations for the
 * institution's own administrator and teachers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);
            $table->string('state', 50);
            $table->string('district', 100)->nullable();
            $table->foreignId('pilot_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // pilot or paid
            $table->unsignedInteger('max_students');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'ends_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('role', 30);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('institutions');
    }
};
