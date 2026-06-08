<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the type ENUM to include meeting, task, other which the frontend offers
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM(
            'call','email','visit','whatsapp','note','complaint',
            'follow_up','sale','return','meeting','task','other'
        ) DEFAULT 'note'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM(
            'call','email','visit','whatsapp','note','complaint',
            'follow_up','sale','return'
        ) DEFAULT 'note'");
    }
};
