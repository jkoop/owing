<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create("friend_circles", function (Blueprint $table) {
			$table->id();
			$table->string("name")->unique();
			$table->timestamps();
			$table->softDeletes();
		});

		Schema::create("friend_circle_user", function (Blueprint $table) {
			$table->id();
			$table->foreignId("friend_circle_id");
			$table->foreignIdFor(User::class);
			$table->timestamps();

			$table->foreign("friend_circle_id")->references("id")->on("friend_circles")->onDelete("cascade");
			$table->foreign("user_id")->references("id")->on("users")->onDelete("cascade");
			$table->unique(["friend_circle_id", "user_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists("friend_circle_user");
		Schema::dropIfExists("friend_circles");
	}
};
