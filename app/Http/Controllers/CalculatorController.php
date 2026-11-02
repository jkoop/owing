<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Repositories\FuelPriceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CalculatorController extends Controller {
	public function tripPrice(Request $request) {
		$request->validate([
			"car_id" => "required|int|exists:cars,id",
			"distance" => "required|numeric|min:0.01",
			"ratio" => "required|numeric|min:0.01|max:1",
			"date" => "nullable",
		]);

		$dateString = $request->date;

		if (strtotime($request->date) >= 1000000000) {
			$dateString = $dateString . " 12:00 America/Winnipeg";
			$date = new Carbon(date("r", strtotime($dateString)));
		} else {
			$date = now("America/Winnipeg");
		}

		$car = Car::findOrPanic($request->car_id);
		$answer = self::getAmountForDriveTrak($car, $request->distance, $request->ratio, $date);
		$answer = '$' . number_format($answer, 2);

		if (strtotime($dateString) < 1000000000) {
			$answer = t(":price (assuming today)", ["price" => $answer]);
		} elseif (
			$date->format("Y-m-d") > now("America/Winnipeg")->format("Y-m-d")
		) {
			$answer = t(":price (transactions in the future can change without warning)", ["price" => $answer]);
		}

		return response(
			$answer,
			headers: [
				"Content-Type" => "text/plain",
			],
		);
	}

	public static function getAmountForDriveTrak(Car $car, float $distance, float $ratio, Carbon $date): float {
		$distance = round($distance, 2);
		$fuelPrice = FuelPriceRepository::getFuelPriceAtTime($car->fuelType->fuel_type, $date);

		return round($car->efficiency->efficiency * $fuelPrice->price * $distance * $ratio, 2);
	}
}
