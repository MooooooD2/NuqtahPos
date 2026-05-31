<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard every table/column — this migration may be re-run on tenant databases
        // that already have these objects from 100005 running on the landlord DB.

        if (! Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->enum('type', ['call','email','visit','whatsapp','note','complaint','follow_up','sale','return'])->default('note');
                $table->string('subject', 200)->nullable();
                $table->text('notes')->nullable();
                $table->enum('outcome', ['positive','neutral','negative','pending'])->default('neutral');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('customer_segments')) {
            Schema::create('customer_segments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->json('rules')->nullable();
                $table->integer('customer_count')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'segment_id')) {
                $table->unsignedBigInteger('segment_id')->nullable()->after('customer_group_id');
            }
            if (! Schema::hasColumn('customers', 'lifecycle_stage')) {
                $table->enum('lifecycle_stage', ['lead','prospect','customer','loyal','at_risk','churned'])
                      ->default('customer')->after('segment_id');
            }
            if (! Schema::hasColumn('customers', 'last_purchase_at')) {
                $table->timestamp('last_purchase_at')->nullable()->after('lifecycle_stage');
            }
            if (! Schema::hasColumn('customers', 'purchase_count')) {
                $table->integer('purchase_count')->default(0)->after('last_purchase_at');
            }
            if (! Schema::hasColumn('customers', 'lifetime_value')) {
                $table->decimal('lifetime_value', 15, 2)->default(0)->after('purchase_count');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('crm_activities');
    }
};
