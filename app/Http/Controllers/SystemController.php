<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\FriendCircle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SystemController extends Controller {
	public function view(Request $request) {
		$friendCirclesQuery = Auth::user()->can("isAdmin") ? FriendCircle::query() : Auth::user()->friendCircles();

		if ($request->has("deleted")) {
			$friendCircles = $friendCirclesQuery->withTrashed()->get();
		} else {
			$friendCircles = $friendCirclesQuery->get();
		}

		if ($request->has("deleted")) {
			return view("pages.system", [
				"cars" => Car::withTrashed()->get(),
				"users" => Auth::user()->can("isAdmin") ? User::withTrashed()->get() : collect([]),
				"friendCircles" => $friendCircles,
			]);
		}

		return view("pages.system", [
			"cars" => Car::all(),
			"users" => Auth::user()->can("isAdmin") ? User::all() : collect([]),
			"friendCircles" => $friendCircles,
		]);
	}
}
