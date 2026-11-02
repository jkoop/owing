<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::table("transactions", function (Blueprint $table) {
			$table->date("occurred_at")->change();
		});

		DB::table("transactions")
			->orderBy("id")
			->each(function (object $transaction) {
				DB::table("transactions")
					->where("id", $transaction->id)
					->update([
						"occurred_at" => Carbon::createFromTimestamp((int) $transaction->occurred_at, "America/Winnipeg")->toDateString(),
					]);
			});
	}

	public function down(): void {
		Schema::table("transactions", function (Blueprint $table) {
			$table->dateTime("occurred_at")->change();
		});

		DB::table("transactions")
			->orderBy("id")
			->each(function (object $transaction) {
				DB::table("transactions")
					->where("id", $transaction->id)
					->update([
						"occurred_at" => Carbon::parse($transaction->occurred_at . " 12:00", "America/Winnipeg")->timestamp,
					]);
			});
	}
};
