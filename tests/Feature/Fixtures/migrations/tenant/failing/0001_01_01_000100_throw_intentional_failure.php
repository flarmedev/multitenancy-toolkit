<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('Intentional failing tenant migration.');
    }

    public function down(): void {}
};
