<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// activity_logs is replaced by audit_trail (see plan.md, Module 1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    public function down(): void
    {
        // Not restored: audit_trail is the only audit log table.
    }
};
