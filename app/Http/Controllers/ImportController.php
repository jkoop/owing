<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarEfficiency;
use App\Models\CarFuelType;
use App\Models\File;
use App\Models\FuelPrice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

final class ImportController extends Controller {
	public function view() {
		return view("pages.import");
	}

	public function import(Request $request) {
		$request->validate([
			"file" => "required|file:*.drivetrak",
		]);

		if (File::exists()) {
			abort(403, "There are rows in the files table");
		}

		Auth::logout();

		DB::beginTransaction();
		Artisan::call("migrate:fresh", ["--force" => true]);

		$carOwners = [];
		$invoiceClients = [];

		$imported = [
			"cars" => 0,
			"fuelPrices" => 0,
			"transactions" => 0,
			"users" => 0,
		];

		$rowNumber = 0;
		$in = fopen($request->file("file")->getRealPath(), "r");
		while (($row = fgetcsv($in)) !== false) {
			$rowNumber++;
			if ($row[0][0] == "#") {
				continue; // comments
			}
			switch ($row[0]) {
				case "car":
					Car::create([
						"id" => (int) $row[1],
						"name" => $row[2],
					]);
					CarEfficiency::create([
						"car_id" => (int) $row[1],
						"efficiency" => (float) $row[3],
					]);
					CarFuelType::create([
						"car_id" => (int) $row[1],
						"fuel_type" => $row[4],
					]);
					$imported["cars"]++;
					break;
				case "car_user":
					$carOwners[(int) $row[1]] ??= [];
					$carOwners[(int) $row[1]][] = (int) $row[2];
					$car = Car::findOrPanic((int) $row[1]);
					if ($car->owner_id == null) {
						$car->update([
							"owner_id" => (int) $row[2],
						]);
					}
					break;
				case "fuel_price":
					FuelPrice::create([
						"fuel_type" => $row[1],
						"price" => (float) $row[2],
						"created_at" => $row[3],
						"updated_at" => $row[3],
					]);
					$imported["fuelPrices"]++;
					break;
				case "invoice":
					if ($row[4] == "") {
						break; // not paid
					}
					$fromUserId = Str::of($row[3])->explode(",")->filter(fn($a) => $a != $row[2])->reverse()->first();
					if ($fromUserId == null) {
						break;
					}
					$invoiceClients[(int) $row[1]] = explode(",", $row[3]);
					Transaction::create([
						"kind" => "payment",
						"memo" => sprintf(
							"From DriveTrak invoice #%d; original vendor ID: %d; original client IDs: %s; reference number: %s",
							$row[1],
							$row[2],
							$row[3],
							$row[6],
						),
						"to_user_id" => (int) $row[2],
						"from_user_id" => (int) $fromUserId,
						"occurred_at" => date("Y-m-d", (int) $row[4]),
						"is_confirmed" => $row[5] != "",
						"created_at" => (int) $row[9],
						"amount" => round((float) $row[10], 2),
					]);
					$imported["transactions"]++;
					break;
				case "trip":
					$car = Car::findOrPanic((int) $row[3]);
					$date = strtotime($row[5] . " 12:00:00 America/Winnipeg");

					Transaction::create([
						"kind" => "drivetrak",
						"memo" => "From DriveTrak trip #" . $row[1] . "; " . $row[6],
						"to_user_id" => (int) $row[2],
						"from_user_id" => $car->owner_id,
						"occurred_at" => date("Y-m-d", $date),
						"is_confirmed" => true,
						"car_id" => $car->id,
						"distance" => (float) $row[7],
						"ratio" => (float) eval("return " . $row[8] . ";"),
						"amount" => (float) $row[11],
					]);

					CarEfficiency::create([
						"car_id" => $car->id,
						"efficiency" => $row[10],
						"created_at" => $date,
						"updated_at" => $date,
					]);

					$imported["transactions"]++;
					break;
				case "user":
					User::create([
						"id" => (int) $row[1],
						"username" => $row[2],
						"password" => $row[3],
						"name" => $row[4],
					]);
					$imported["users"]++;
					break;
				default:
					return Redirect::to("/login")->withErrors(
						t("Invalid row in CSV near row :rowNumber", compact($rowNumber)),
					);
			}
		}
		User::first()->update(["is_admin" => true]);

		// collapse car change histories
		foreach (Car::all() as $car) {
			$lastEfficiency = -1;
			foreach (CarEfficiency::where("car_id", $car->id)->lazy() as $efficiency) {
				if ($efficiency->efficiency != $lastEfficiency) {
					$lastEfficiency = $efficiency->efficiency;
				} else {
					$efficiency->delete();
				}
			}
			$lastFuelType = "";
			foreach (CarFuelType::where("car_id", $car->id)->lazy() as $fuelType) {
				if ($fuelType->fuel_type != $lastFuelType) {
					$lastFuelType = $fuelType->fuel_type;
				} else {
					$fuelType->delete();
				}
			}
		}

		// delete transactions where from_user_id == to_user_id
		Transaction::whereRaw('"from_user_id" = "to_user_id"')
			->get() // we have to get them so change history will record it
			->map(fn($a) => $a->delete());

		// fix transaction with negative amount
		Transaction::where("amount", "<", 0)->get()->map(
			fn($a) => $a->update([
				"from_user_id" => $a->to_user_id,
				"to_user_id" => $a->from_user_id,
				"amount" => abs($a->amount),
			]),
		);

		// try to guess the correct of multiple clients for invoices
		foreach (
			Transaction::with(["userFrom", "userTo"])
				->where("memo", "like", "From DriveTrak invoice #%")
				->lazy()
			as $transaction
		) {
			sscanf(
				$transaction->memo,
				"From DriveTrak invoice #%d; original vendor ID: %d; original client IDs: %s; reference number: %s",
				$invoiceId,
				$vendorId,
				$clientIds,
				$referenceNumber,
			);
			if (count($invoiceClients[$invoiceId]) == 1) {
				continue;
			}

			$userFrom = $transaction->userFrom;

			$users = array_combine($invoiceClients[$invoiceId], $invoiceClients[$invoiceId]);
			$users = array_map(fn($a) => $userFrom->getOwing(User::findOrPanic($a)), $users);
			asort($users);
			$users = array_keys($users);

			$transaction->update([
				"from_user_id" => $users[0],
			]);
		}

		// try to guess the correct of multiple car owners for trips
		foreach (
			Transaction::with(["userFrom", "userTo"])
				->where("memo", "like", "From DriveTrak trip #%")
				->lazy()
			as $transaction
		) {
			if (!isset($carOwners[$transaction->car_id])) {
				continue;
			}
			if (count($carOwners[$transaction->car_id]) == 1) {
				continue;
			}

			$userFrom = $transaction->userFrom;
			$userTo = $transaction->userTo;
			$otherOwner = User::findOrPanic($carOwners[$transaction->car_id][1]);

			if ($userTo->getOwing($userFrom) < $userTo->getOwing($otherOwner)) {
				$transaction->update([
					"from_user_id" => $otherOwner->id,
				]);
			}
		}

		DB::commit();
		Artisan::call("db:optimize");

		Auth::login(User::first(), true);
		Session::put("success-important", true);
		return Redirect::to("/")->with(
			"success",
			t(
				"Successfully imported :cars cars, :fuelPrices fuel prices, :transactions transactions, :users users.",
				$imported,
			),
		);
	}
}
