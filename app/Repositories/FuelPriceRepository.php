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
			cat file | grep -o '"prices":\s*\[[^]]*]'
			SHELL
			,
			$data,
		);

		$data = $data[0];
		$data = "{" . $data . "}";
		$data = json_decode($data);

		//  "prices": [
		//    {
		//      "__typename": "PriceReport",
		//      "cash": null,
		//      "credit": {
		//        "__typename": "FuelPrice",
		//        "nickname": "aikitazz",
		//        "postedTime": "2026-01-16T13:10:28.434Z",
		//        "price": 123.9,
		//        "formattedPrice": "123.9¢"
		//      },
		//      "fuelProduct": "regular_gas",
		//      "longName": "Regular"
		//    },
		//    {
		//      "__typename": "PriceReport",
		//      "cash": null,
		//      "credit": {
		//        "__typename": "FuelPrice",
		//        "nickname": "Buddy_5c3e4mj3",
		//        "postedTime": "2026-01-16T02:35:52.599Z",
		//        "price": 143.9,
		//        "formattedPrice": "143.9¢"
		//      },
		//      "fuelProduct": "premium_gas",
		//      "longName": "Premium"
		//    },
		//    {
		//      "__typename": "PriceReport",
		//      "cash": null,
		//      "credit": {
		//        "__typename": "FuelPrice",
		//        "nickname": "aikitazz",
		//        "postedTime": "2026-01-16T13:10:28.441Z",
		//        "price": 146.9,
		//        "formattedPrice": "146.9¢"
		//      },
		//      "fuelProduct": "diesel",
		//      "longName": "Diesel"
		//    }
		//  ]

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
