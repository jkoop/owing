<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarEfficiency;
use App\Models\CarFuelType;
use App\Models\Transaction;
use App\Rules\UniqueCi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

final class CarController extends Controller {
	public function new() {
		return view("pages.car", ["car" => new Car()]);
	}

	public function create(Request $request) {
		return $this->update(new Car(), $request);
	}

	public function view(Car $car) {
		return view("pages.car", compact("car"));
	}

	public function update(Car $car, Request $request) {
		DB::beginTransaction();

		$request->validate([
			"name" => ["required", "string", "ascii", new UniqueCi("cars", ignoreRowId: $car->id ?? [])],
			"efficiency" => "required|numeric|min:0.0001|max:10",
			"fuel_type" => "required|in:" . implode(",", array_keys(CarFuelType::FUEL_TYPES)),
			"owner_id" => "nullable|integer|exists:users,id",
		]);

		$data = [
			"name" => $request->name,
			"owner_id" => Auth::user()->is_admin ? $request->owner_id : Auth::id(),
		];

		if ($data["owner_id"] != null) {
			$data["owner_id"] = (int) $data["owner_id"];
		}

		$car->fill($data);
		$car->save();

		if (round($request->efficiency, 4) != $car->efficiency?->efficiency) {
			CarEfficiency::create([
				"car_id" => $car->id,
				"efficiency" => round($request->efficiency, 4),
				"author_id" => Auth::id(),
			]);

			if ($car->id) {
				Transaction::with("car")
					->where("occurred_at", ">", now()->timestamp)
					->where("car_id", $car->id)
					->get()
					->map(fn($transaction) => $transaction->recalculate());
			}
		}

		if ($request->fuel_type != $car->fuelType?->fuel_type) {
			CarFuelType::create([
				"car_id" => $car->id,
				"fuel_type" => $request->fuel_type,
				"author_id" => Auth::id(),
			]);

			if ($car->id) {
				Transaction::with("car")
					->where("occurred_at", ">", now()->timestamp)
					->where("car_id", $car->id)
					->get()
					->map(fn($transaction) => $transaction->recalculate());
			}
		}

		if ($car->id) {
			if ($request->has("delete")) {
				$car->delete();
			}
			if ($request->has("restore")) {
				$car->restore();
			}

			DB::commit();
			return Redirect::back()->with("success", t("Saved"));
		} else {
			DB::commit();
			return Redirect::to("/c/" . $car->id)->with("success", t("Saved"));
		}
	}
}
