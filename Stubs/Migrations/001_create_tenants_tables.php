<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTenantsTables extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tenants', function (Blueprint $table) {
			$table->increments('id');
			$table->string('company_name')->nullable();
			$table->timestamps();
		});

		// Create table to assign all users assigned to a tenant
		Schema::create('tenant_user', function (Blueprint $table) {
			$table->integer('tenant_id')->unsigned()->index();
			$table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
			$table->integer('user_id')->unsigned()->index();
			$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			$table->primary(['tenant_id', 'user_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tenant_user');
		Schema::drop('tenants');
	}
}
