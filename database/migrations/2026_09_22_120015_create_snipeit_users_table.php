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
        if (! Schema::hasTable('snipeit_users')) {
            Schema::create('snipeit_users', function (Blueprint $table) {
                $table->id();
                $table->string('snipeit_id')->unique();
                $table->string('name');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('jobtitle')->nullable();
                $table->string('employee_num')->nullable();
                $table->unsignedBigInteger('manager_id')->nullable();
                $table->string('manager_name')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->string('location_name')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('department')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('company')->nullable();
                $table->boolean('activated')->default(false);
                $table->string('avatar')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamp('snipeit_created_at')->nullable();
                $table->timestamp('snipeit_updated_at')->nullable();
                $table->timestamps();

                $table->index('username');
                $table->index('email');
                $table->index('name');
                $table->index('company_id');
                $table->index('location_id');
                $table->index('department_id');
                $table->index(['company', 'department']);
            });

            return;
        }

        $hasSnipeitId = Schema::hasColumn('snipeit_users', 'snipeit_id');

        Schema::table('snipeit_users', function (Blueprint $table) use ($hasSnipeitId): void {
            if (! $hasSnipeitId) {
                $table->string('snipeit_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('snipeit_users', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'username')) {
                $table->string('username')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'jobtitle')) {
                $table->string('jobtitle')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'employee_num')) {
                $table->string('employee_num')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'manager_name')) {
                $table->string('manager_name')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'location_name')) {
                $table->string('location_name')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'department')) {
                $table->string('department')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'company')) {
                $table->string('company')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'activated')) {
                $table->boolean('activated')->default(false);
            }
            if (! Schema::hasColumn('snipeit_users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'raw_data')) {
                $table->json('raw_data')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'snipeit_created_at')) {
                $table->timestamp('snipeit_created_at')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'snipeit_updated_at')) {
                $table->timestamp('snipeit_updated_at')->nullable();
            }
            if (! Schema::hasColumn('snipeit_users', 'synced_at')) {
                $table->timestamp('synced_at')->nullable();
            }
        });

        if (! $hasSnipeitId) {
            DB::table('snipeit_users')
                ->whereNull('snipeit_id')
                ->update(['snipeit_id' => DB::raw('CAST(id AS CHAR)')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snipeit_users');
    }
};
