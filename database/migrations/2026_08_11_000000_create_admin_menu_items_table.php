<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('admin_menu_items')
                ->nullOnDelete();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('link_type', 20)->default('none');
            $table->string('target')->nullable();
            $table->json('parameters')->nullable();
            $table->string('permission')->nullable();
            $table->string('target_window', 10)->default('_self');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_menu_items');
    }
};
