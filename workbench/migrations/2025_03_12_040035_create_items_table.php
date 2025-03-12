<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->integer('organization_id')->nullable();
            $table->integer('department_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
