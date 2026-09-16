<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return;
        }

        $columns = [
            'category' => 'string',
            'status' => 'integer',
            'title' => 'string',
            'content' => 'mediumText',
            'tags' => 'string',
            'sub_institute_id' => 'integer',
        ];

        foreach ($columns as $name => $type) {
            if (! Schema::hasColumn('knowledge_base_detail', $name)) {
                Schema::table('knowledge_base_detail', function (Blueprint $table) use ($name, $type) {
                    if ($type === 'string') {
                        $table->string($name)->nullable();
                    } elseif ($type === 'integer') {
                        $table->integer($name)->default(1);
                    } elseif ($type === 'mediumText') {
                        $table->mediumText($name)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return;
        }

        Schema::table('knowledge_base_detail', function (Blueprint $table) {
            $table->dropColumn(['category', 'status', 'title', 'content', 'tags']);
        });
    }
};
