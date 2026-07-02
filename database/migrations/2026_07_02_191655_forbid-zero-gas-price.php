<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
	public function up(): void {
		DB::unprepared(<<<'SQL'

		BEGIN;

		CREATE TABLE "fuel_prices2" (
			"id" integer primary key autoincrement not null,
			"fuel_type" varchar check ("fuel_type" in ('gasoline', 'diesel')) not null,
			"price" float check ("price" > 0) not null,
			"created_at" datetime,
			"updated_at" datetime
		);

		INSERT INTO fuel_prices2 SELECT * FROM fuel_prices where price > 0;
		DROP TABLE fuel_prices;
		ALTER TABLE fuel_prices2 RENAME TO fuel_prices;

		COMMIT;

		SQL);
	}
};
