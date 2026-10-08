<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // testbench serve also runs its own default users migration; the workbench owns this table.
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status')->default('todo');
            $table->unsignedInteger('position')->nullable();
            $table->boolean('locked')->default(false);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('status')->default('new');
            $table->unsignedInteger('sort')->nullable();
            $table->timestamps();
        });

        Schema::create('stages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('stage_id')->constrained('stages');
            $table->unsignedInteger('sort')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status')->default('open');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
    }
};
