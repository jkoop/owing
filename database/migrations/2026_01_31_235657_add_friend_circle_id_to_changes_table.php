<?php

use App\Models\FriendCircle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table("changes", function (Blueprint $table) {
			$table->foreignId("friend_circle_id")->nullable()->after("user_id");

			$table->foreign("friend_circle_id")->references("id")->on("friend_circles")->onDelete("cascade");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table("changes", function (Blueprint $table) {
			$table->dropForeign(["friend_circle_id"]);
			$table->dropColumn("friend_circle_id");
		});
	}
};
