<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categories = DB::table('categories')->whereRaw('LENGTH(name) > 25')->get();
        foreach ($categories as $cat) {
            DB::table('categories')->where('id', $cat->id)->update([
                'name' => substr($cat->name, 0, 25),
            ]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name', 25)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
