<?php

namespace App\Repositories;

use App\Models\CarFuelType;
use App\Models\FuelPrice;
use App\Models\Transaction;
use Carbon\Carbon;

class FuelPriceRepository {
	/** GasBuddy station IDs whose posted prices are averaged. */
	private const STATION_IDS = ["50979", "19217", "22797"];

	private static $prices = [];

	private static function getTypes(): array {
		return array_keys(CarFuelType::FUEL_TYPES);
	}

	public static function getAllFuelPrices(): object {
		$prices = [];

		foreach (self::getTypes() as $type) {
			$prices[$type] = self::getFuelPrice($type);
		}

		return (object) $prices;
	}

	public static function getFuelPrice(string $type): FuelPrice {
		return self::$prices[$type] ??= (function () use ($type) {
			return self::getFuelPriceAtTime($type, now());
		})();
	}

	public static function getFuelPriceAtTime(string $type, string|Carbon $time): FuelPrice {
		if (!in_array($type, self::getTypes())) {
			throw new \InvalidArgumentException('Invalid $type');
		}
		if (!$time instanceof Carbon) {
			$time = Carbon::parse($time);
		}
		$time = Carbon::parse($time->format("Y-m-d") . " 12:00:00 America/Winnipeg");

		return FuelPrice::where("fuel_type", $type)
			->where("created_at", "<", $time->timestamp)
			->orderByDesc("created_at")
			->firstOrFail();
	}

	public static function refreshFuelPrices(): void {
		$stationQueries = array_map(
			fn($stationId) => "station{$stationId}: station(id: \"{$stationId}\") { ...prices }",
			self::STATION_IDS,
		);
		$body = json_encode([
			"operationName" => "GetStationPrices",
			"query" =>
				"query GetStationPrices { " .
				implode(" ", $stationQueries) .
				" } fragment prices on Station { id prices { cash { price } credit { price } fuelProduct } }",
		]);
		$referer = "https://www.gasbuddy.com/station/" . self::STATION_IDS[0];

		exec(
			"curl 'https://www.gasbuddy.com/graphql' -H 'apollo-require-preflight: true' -H 'content-type: application/json' -H 'gbcsrf: 1.AhjjyJxIObKWI0Vv' -H 'origin: https://www.gasbuddy.com' -H " .
				escapeshellarg("referer: {$referer}") .
				" --data-raw " .
				escapeshellarg($body),
			$data,
		);

		$data = implode("\n", $data);
		$data = json_decode($data);

		// Unposted prices come back as "price": 0. Each station has the same shape as station50979.
		// {
		//   "data": {
		//     "station50979": {
		//       "id": "50979",
		//       "prices": [
		//         {
		//           "cash": null,
		//           "credit": {
		//             "price": 169.9
		//           },
		//           "fuelProduct": "regular_gas"
		//         },
		//         {
		//           "cash": null,
		//           "credit": {
		//             "price": 184.9
		//           },
		//           "fuelProduct": "midgrade_gas"
		//         },
		//         {
		//           "cash": null,
		//           "credit": {
		//             "price": 194.9
		//           },
		//           "fuelProduct": "premium_gas"
		//         },
		//         {
		//           "cash": null,
		//           "credit": {
		//             "price": 285.9
		//           },
		//           "fuelProduct": "diesel"
		//         }
		//       ]
		//     }
		//   }
		// }

		$stations = array_values((array) $data->data);
		$prices = [];

		foreach ($stations as $station) {
			foreach ($station->prices as $fuel) {
				$type = $fuel->fuelProduct; // regular_gas, premium_gas, diesel
				$type = match ($type) {
					"regular_gas" => "gasoline",
					"diesel" => "diesel",
					default => null,
				};
				if ($type == null) {
					continue;
				}

				$price = $fuel->credit ?? $fuel->cash;
				$price = $price?->price ?? 0; // cents
				if ($price > 0) {
					$prices[$type][] = $price / 100;
				}
			}
		}

		foreach (["gasoline", "diesel"] as $type) {
			if (!isset($prices[$type])) {
				$stationIds = implode(", ", array_map(fn($station) => $station->id, $stations));
				throw new \RuntimeException("GasBuddy has no posted {$type} price at any of stations {$stationIds}");
			}
		}

		foreach ($prices as $type => $stationPrices) {
			$price = round(array_sum($stationPrices) / count($stationPrices), 3);

			FuelPrice::create([
				"price" => $price,
				"fuel_type" => $type,
			]);

			Transaction::with("car")
				->where("kind", "drivetrak")
				->where("occurred_at", ">", now("America/Winnipeg")->toDateString())
				->get()
				->map(fn($transaction) => $transaction->recalculate());
		}

		self::$prices = [];
	}
}
