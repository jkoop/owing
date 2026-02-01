<?php

namespace App\Http\Controllers;

use App\Exceptions\ImpossibleStateException;
use App\Models\Car;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

final class TransactionController extends Controller {
	public function index(Request $request) {
		$request->validate([
			"offset" => "required|int|min:0",
			"user_id" => "nullable|int|min:1",
			"order_by" => "required|in:occurred_at,updated_at",
			"memo" => "nullable|string",
		]);

		$transactions = Auth::user()
			->transactions()
			->with(["userFrom", "userTo"])
			->orderByDesc($request->order_by)
			->orderByDesc("occurred_at")
			->offset($request->offset)
			->limit(50);

		if ($request->has("deleted")) {
			$transactions = $transactions->withTrashed();
		}
		if ($request->user_id != null) {
			$transactions = $transactions->where(function ($query) use ($request) {
				$query->where("from_user_id", $request->user_id)->orWhere("to_user_id", $request->user_id);
			});
		}
		if (trim($request->memo) != "") {
			$memo = trim($request->memo);
			$transactions = $transactions->where("memo", "like", "%$memo%");
		}
		$transactions = $transactions->get();

		return view("blocks.transactions", compact("transactions"));
	}

	public function new(Request $request) {
		$request->validate([
			"clone" => "nullable|int|exists:transactions,id",
		]);

		if ($request->clone) {
			$transaction = Transaction::findOrPanic($request->clone);
			Gate::authorize("view", $transaction);
			$transaction->id = null;
		} else {
			$transaction = new Transaction();
		}

		$friendCircleUserIds = Auth::user()->getVisibleUsers()->pluck("id")->toArray();

		$users = User::withTrashed()
			->whereNot("id", Auth::id())
			->orderBy("name")
			->get()
			->filter(
				fn($a) => $a->deleted_at == null && in_array($a->id, $friendCircleUserIds) or
					$a->getOwing(Auth::user()),
			);

		return view("pages.transaction", compact("transaction", "users"));
	}

	public function create(Request $request) {
		return $this->update(new Transaction(), $request);
	}

	public function view(Transaction $transaction) {
		$friendCircleUserIds = Auth::user()->getVisibleUsers()->pluck("id")->toArray();

		$users = User::withTrashed()
			->whereNot("id", Auth::id())
			->orderBy("name")
			->get()
			->filter(
				fn($a) => $a->deleted_at == null && in_array($a->id, $friendCircleUserIds) or
					$a->getOwing(Auth::user()),
			);

		return view("pages.transaction", compact("transaction", "users"));
	}

	public function update(Transaction $transaction, Request $request) {
		DB::beginTransaction(); // prevent races

		$request->validate([
			"kind" => "required|in:owing,payment,drivetrak",
			"from_to" => "nullable|required_if:kind,owing,payment|in:from,to",
			"car_id" => "nullable|required_if:kind,drivetrak|integer|exists:cars,id",
			"other_user_id" => "nullable|required_if:kind,owing,payment|integer|exists:users,id|not_in:" . Auth::id(),
			"distance" => "nullable|required_if:kind,drivetrak|numeric",
			"ratio" => "nullable|required_if:kind,drivetrak|numeric",
			"amount" => "nullable|required_if:kind,owing,payment|numeric",
			"occurred_at" => "required|date",
			"memo" => "present",
		]);

		$car = null;
		if ($request->car_id != null) {
			$car = Car::find($request->car_id) ?? throw new ImpossibleStateException();
		}

		if ($car != null) {
			$request->validate([
				"kind" => "required|in:drivetrak",
				"distance" => "required|numeric|min:0.01",
				"ratio" => "numeric|min:0.01|max:1.0",
			]);

			if ($car->owner_id == Auth::id()) {
				$request->validate([
					"other_user_id" => "required",
				]);
			}
		} else {
			$request->validate([
				"amount" => "required|numeric|min:0.01",
			]);
		}

		$fromTo = $request->from_to;
		if ($car != null) {
			if ($car->owner_id == Auth::id()) {
				$fromTo = "from";
			} else {
				$fromTo = "to";
			}
		}

		if ($request->other_user_id != null) {
			$otherUser = User::withTrashed()->find($request->other_user_id) ?? throw new ImpossibleStateException();
		} else {
			$otherUser = null;
		}

		/**
		 * | Description of transfer | From  | To    |
		 * | ----------------------- | ----- | ----- |
		 * | Alice pays Bob          | Alice | Bob   |
		 * | Alice owes Bob          | Bob   | Alice |
		 * | Alice drives Bob's car  | Bob   | Alice |
		 */

		if ($car == null) {
			$fromUser = $fromTo != "to" ? Auth::user() : $otherUser;
			$toUser = $fromTo == "to" ? Auth::user() : $otherUser;
		} else {
			if ($car->owner_id == Auth::id()) {
				$fromUser = Auth::user();
				$toUser = $otherUser;
			} else {
				$fromUser = $car->owner;
				$toUser = Auth::user();
			}
		}

		$kind = $request->kind;
		$occurredAt = Carbon::parse(strtotime($request->occurred_at . " 12:00 America/Winnipeg"));
		$distance = $car == null ? null : round($request->distance, 2);
		$ratio = $car == null ? null : (float) $request->ratio;
		$amount =
			$car == null
				? round($request->amount, 2)
				: CalculatorController::getAmountForDriveTrak($car, $distance, $ratio, $occurredAt);
		$memo = trim($request->memo ?? "");

		$transaction->fill([
			"kind" => $kind,
			"from_user_id" => $fromUser->id,
			"to_user_id" => $toUser->id,
			"amount" => $amount,
			"is_confirmed" => $fromUser->id == Auth::id(),
			"car_id" => $car?->id,
			"distance" => $distance,
			"ratio" => $ratio,
			"memo" => $memo,
			"occurred_at" => $occurredAt,
		]);
		$transaction->save();

		if ($request->has("delete")) {
			$transaction->delete();
		}

		if ($request->has("restore")) {
			$transaction->restore();
		}

		DB::commit();

		return Redirect::to("/")->with("success", t("Saved"));
	}
}
