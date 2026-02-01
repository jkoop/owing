<?php

namespace App\Http\Controllers;

use App\Models\Change;
use App\Models\FriendCircle;
use App\Models\User;
use App\Rules\UniqueCi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

final class FriendCircleController extends Controller {
	public function new() {
		return view("pages.friend-circle", ["friendCircle" => new FriendCircle()]);
	}

	public function create(Request $request) {
		return $this->update(new FriendCircle(), $request);
	}

	public function view(FriendCircle $friendCircle) {
		return view("pages.friend-circle", compact("friendCircle"));
	}

	public function update(FriendCircle $friendCircle, Request $request) {
		$request->validate([
			"name" => [
				"required",
				"string",
				"ascii",
				new UniqueCi("friend_circles", ignoreRowId: $friendCircle->id ?? []),
			],
			"user_ids" => "nullable|array",
			"user_ids.*" => "integer|exists:users,id",
		]);

		$wasNew = !$friendCircle->exists;

		$friendCircle->fill([
			"name" => $request->name,
		]);
		$friendCircle->save();

		$userIds = $request->input("user_ids", []);
		$oldUserIds = $friendCircle->users->pluck("id")->toArray();
		$friendCircle->users()->sync($userIds);

		// Track user changes
		if ($friendCircle->exists) {
			$added = array_diff($userIds, $oldUserIds);
			$removed = array_diff($oldUserIds, $userIds);

			if (!empty($added)) {
				$addedNames = User::whereIn("id", $added)->pluck("name")->toArray();
				Change::record($friendCircle, "added users: " . implode(", ", $addedNames));
			}

			if (!empty($removed)) {
				$removedNames = User::whereIn("id", $removed)->pluck("name")->toArray();
				Change::record($friendCircle, "removed users: " . implode(", ", $removedNames));
			}
		}

		if ($request->has("delete")) {
			$friendCircle->delete();
		}

		if ($request->has("restore")) {
			$friendCircle->restore();
		}

		if ($wasNew) {
			return Redirect::to("/fc/" . $friendCircle->id)->with("success", t("Saved"));
		} else {
			return Redirect::back()->with("success", t("Saved"));
		}
	}
}
