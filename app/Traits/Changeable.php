<?php

namespace App\Traits;

use App\Models\Change;
use App\Models\Model;

trait Changeable {
	private static $attributesNotShown = ["id", "remember_token", "created_at", "updated_at", "deleted_at"];

	/**
	 * The "booted" method of the Model.
	 */
	protected static function booted(): void {
		static::deleted(fn(Model $model) => Change::record($model, "deleted"));
		static::restored(fn(Model $model) => Change::record($model, "restored"));

		static::created(function (Model $model) {
			$data = $model->toArray();
			foreach (self::$attributesNotShown as $attributeNotShown) {
				unset($data[$attributeNotShown]);
			}
			foreach ($data as $key => &$value) {
				$cast = $model->getCasts()[$key] ?? null;
				if (in_array($cast, ["datetime", "date"]) and $value != null) {
					$value = date("c", strtotime($value));
				}
			}
			unset($data["password"]);
			$description = "created with data " . json_encode($data);
			if (isset($model->password) and $model->password != null) {
				$description .= " and a password";
			}
			Change::record($model, $description);
		});

		static::saved(function (Model $model) {
			foreach ($model->changes as $key => $newValue) {
				if (in_array($key, self::$attributesNotShown)) {
					continue;
				}
				if ($key == "password") {
					Change::record($model, "changed password");
				} else {
					if ($newValue == $model->getOriginal($key)) {
						continue;
					}
					if (in_array($model->getCasts()[$key] ?? null, ["datetime", "date"]) and $newValue != null) {
						$newValue = date("c", is_numeric($newValue) ? (int) $newValue : strtotime($newValue));
					} else {
						$newValue = json_encode($newValue);
					}
					Change::record($model, "changed $key to $newValue");
				}
			}
		});
	}
}
