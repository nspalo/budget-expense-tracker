<?php

declare(strict_types=1);

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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_group_id')->nullable();
            $table->string('name', 50);
            $table->string('type', 10);
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('category_group_id')
                ->references('id')
                ->on('category_groups')
                ->nullOnDelete();

            $table->unique(
                ['user_id', 'name', 'type', 'deleted_at'],
                'uq_categories_user_name_type_deleted'
            );

            $table->index(
                ['user_id', 'category_group_id'],
                'idx_categories_user_category_group'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
