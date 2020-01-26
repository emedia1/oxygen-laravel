<?php

use Illuminate\Database\Migrations\Migration;

class UpdateAuthTablesMultiTenant extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('abilities', function ($table) {
			$table->integer('tenant_id')->unsigned();
			$table->foreign('tenant_id')->references('id')->on('tenants');

			$table->dropUnique('abilities_unique_index');
			$table->unique(['name', 'entity_id', 'entity_type', 'only_owned', 'tenant_id'], 'abilities_unique_index');
		});

		Schema::table('roles', function ($table) {
			$table->integer('tenant_id')->unsigned();
			$table->foreign('tenant_id')->references('id')->on('tenants');

			$table->dropUnique('roles_name_unique');
			$table->unique(['name', 'tenant_id']);
		});

		Schema::table('invitations', function ($table) {
			$table->integer('tenant_id')->unsigned();
			$table->foreign('tenant_id')->references('id')->on('tenants');
		});

		/*
		Schema::table('permissions', function ($table) {
			$table->integer('tenant_id')->unsigned();
			$table->foreign('tenant_id')->references('id')->on('tenants');
		});
		*/

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		/*
		Schema::table('permissions', function ($table) {
			$table->dropColumn('tenant_id');
		});
		*/

		Schema::table('invitations', function ($table) {
			$table->dropForeign('invitations_tenant_id_foreign');
			$table->dropColumn('tenant_id');
		});

		Schema::table('abilities', function ($table) {
			$table->dropUnique('abilities_unique_index');

			// the following may not be enforceable because of duplicate data
			// $table->unique(['name', 'entity_id', 'entity_type']);

			$table->dropForeign('abilities_tenant_id_foreign');
			$table->dropColumn('tenant_id');
		});

		Schema::table('roles', function ($table) {
			$table->dropUnique('roles_name_tenant_id_unique');
			// the following may not be enforceable because of duplicate data
			// $table->unique(['name']);

			$table->dropForeign('roles_tenant_id_foreign');
			$table->dropColumn('tenant_id');
		});

	}
}
