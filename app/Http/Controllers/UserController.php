<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\UniqueCi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

final class UserController extends Controller {
	public function new() {
		return view("pages.user", ["user" => new User(), "profile" => false]);
	}

	public function create(Request $request) {
		return $this->update(new User(), $request);
	}

	public function view(User $user) {
		$profile = false;
		return view("pages.user", compact("user", "profile"));
	}

	public function update(User $user, Request $request) {
		$request->validate([
			"username" => [
				"required",
				"string",
				"ascii",
				"lowercase",
				'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i',
				new UniqueCi("users", ignoreRowId: $user->id ?? []),
			],
			"name" => ["required", "string", "ascii", new UniqueCi("users", ignoreRowId: $user->id ?? [])],
			"password" => "nullable|string|min:8",
			"locale" => "required|string|in:en_CA,de",
		]);

		$data = [
			"username" => strtolower($request->username),
			"name" => $request->name,
			"is_admin" => $request->has("is_admin"),
			"must_change_password" => $request->has("must_change_password"),
			"locale" => $request->locale,
		];

		if ($request->password != null) {
			$data["password"] = $request->password;
		}

		if ($user->id) {
			$user->update($data);
			if ($request->has("delete")) {
				$user->delete();
			}
			if ($request->has("restore")) {
				$user->restore();
			}
			return Redirect::back()->with("success", t("Saved"));
		} else {
			$data["must_change_password"] = true;
			$user->fill($data);
			$user->save();
			return Redirect::to("/u/" . $user->id)->with("success", t("Saved"));
		}
	}
}
