<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ExportController extends Controller {
	public function view() {
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

		return view("pages.export", compact("users"));
	}

	public function export(Request $request) {
		$request->validate([ "user_id" => "required|integer" ]);
		$fp = fopen("php://memory", "r+");
		fputcsv($fp, ["txn_number", "date", "payee", "credit", "memo"], "\t");

		foreach (
		$transactions = Transaction::where(function ($query): void {
			$query
				->where("from_user_id", Auth::id())
				->orWhere("to_user_id", Auth::id());
		})
			->when(
				$request->user_id != 0,
				function ($query) use ($request): void {
					$query->where(function ($query) use ($request): void {
						$query
							->where("from_user_id", $request->user_id)
							->orWhere("to_user_id", $request->user_id);
					});
				},
			)
			->when(
				$request->has("deleted"),
				function ($query): void {
					$query->withTrashed();
				},
			)
			->with("userFrom", "userTo", "car")
			->cursor()
		as $transaction) {
			$txnNumber = $transaction->getKey();
			$date = $transaction->created_at->format("Y-m-d");
			$payee = $transaction->otherUser->name;
			$credit = $transaction->amount * ($transaction->from_user_id == Auth::id() ? 1 : -1);
			$memo = $transaction->memo;

			if ($transaction->car != null) {
				$memo = "[" . $transaction->car->name . "] " . $memo;
			}

			if ($transaction->deleted_at != null) {
				$credit = "$" . number_format($credit, 2);
				$memo = "[DELETED $credit] " . $memo;
				$credit = 0;
			}

			fputcsv($fp, [ $txnNumber, $date, $payee, $credit, $memo ], "\t");
		}

		rewind($fp);

		$appName = config("app.name");
		$time = now()->timestamp;
		$user = User::find($request->user_id)?->name ?? "All";

		return response(stream_get_contents($fp), headers: [
			"Content-Type" => "text/csv",
			"Content-Disposition" => 'attachment; filename="' . Str::slug("$appName $time $user") . '.csv"',
		]);
	}
}
