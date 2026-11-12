<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products used to store the category as free text. This moves it into a
     * categories table and keeps every existing product linked to its category.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('description')
                ->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->after('category_id')
                ->constrained('brands')->restrictOnDelete();
        });

        $now = now();

        foreach (DB::table('products')->distinct()->pluck('category') as $name) {
            if ($name === null || trim($name) === '') {
                continue;
            }

            $id = DB::table('categories')->where('name', $name)->value('id')
                ?? DB::table('categories')->insertGetId([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('products')->where('category', $name)->update(['category_id' => $id]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->default('')->after('description');
        });

        foreach (DB::table('categories')->get() as $category) {
            DB::table('products')->where('category_id', $category->id)->update(['category' => $category->name]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
