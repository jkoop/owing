<?php

namespace App\Repositories;

use App\Models\CarFuelType;
use App\Models\FuelPrice;
use App\Models\Transaction;
use Carbon\Carbon;

class FuelPriceRepository {
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
			$time = Carbon::parse(Carbon::parse($time)->format("Y-m-d") . " 12:00:00 America/Winnipeg");
		}

		return FuelPrice::where("fuel_type", $type)
			->where("created_at", "<", $time->timestamp)
			->orderByDesc("created_at")
			->firstOrFail();
	}

	public static function refreshFuelPrices(): void {
		exec(
			<<<'SHELL'
			curl 'https://www.gasbuddy.com/graphql' -H 'apollo-require-preflight: true' -H 'content-type: application/json' -H 'gbcsrf: 1.AhjjyJxIObKWI0Vv' -H 'origin: https://www.gasbuddy.com' -H 'referer: https://www.gasbuddy.com/station/50979' --data-raw '{"operationName":"GetStationPrices","variables":{"id":"50979"},"query":"query GetStationPrices($id: ID\u0021) { station(id: $id) { id prices { cash { price } credit { price } fuelProduct } } }"}'
			SHELL
			,
			$data,
		);

		$data = implode("\n", $data);
		$data = json_decode($data);

		// {
		//   "data": {
		//     "station": {
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

		$data = $data->data->station;

		foreach ($data->prices as $fuel) {
			$price = $fuel->credit ?? $fuel->cash;
			$price = $price->price; // cents
			$price /= 100;

			$type = $fuel->fuelProduct; // regular_gas, premium_gas, diesel
			$type = match ($type) {
				"regular_gas" => "gasoline",
				"diesel" => "diesel",
				default => null,
			};
			if ($type == null) {
				continue;
			}

			FuelPrice::create([
				"price" => $price,
				"fuel_type" => $type,
			]);

			Transaction::with("car")
				->where("kind", "drivetrak")
				->where("occurred_at", ">", now()->timestamp)
				->get()
				->map(fn($transaction) => $transaction->recalculate());
		}

		self::$prices = [];
	}
}
