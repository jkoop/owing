<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller {
	public function view(Request $request) {
		$request->validate([
			"user_id" => "nullable|int|exists:users,id",
		]);

		$cars = DB::select(
			<<<SQL
				SELECT DISTINCT "car_id"
				FROM "transactions"
				WHERE "from_user_id" = :0
					OR "to_user_id" = :1
			SQL
			,
			[Auth::id(), Auth::id()],
		);
		$cars = collect($cars)->pluck("car_id");
		$cars = Car::withTrashed()->whereIn("id", $cars->toArray())->orderBy("name")->get();

		$users = DB::select(
			<<<SQL
				SELECT DISTINCT "from_user_id", "to_user_id"
				FROM "transactions"
				WHERE "from_user_id" = :0
					OR "to_user_id" = :1
			SQL
			,
			[Auth::id(), Auth::id()],
		);
		$users = collect($users)
			->map(fn($a) => [$a->from_user_id, $a->to_user_id])
			->flatten()
			->filter(fn($a) => $a != Auth::id())
			->unique();
		$users = User::withTrashed()->whereIn("id", $users->toArray())->orderBy("name")->get();

		return view("pages.dashboard", compact("cars", "users"));
	}
}
