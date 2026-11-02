<?php

namespace App\Models;

use App\Repositories\FuelPriceRepository;
use App\Traits\Changeable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model {
	use Changeable, SoftDeletes;

	protected $casts = [
		"occurred_at" => "date",
	];

	/**
	 * Store as `Y-m-d` rather than the base model's Unix timestamp format, so `occurred_at` compares correctly against date strings.
	 */
	protected function occurredAt(): Attribute {
		return Attribute::make(set: fn($value) => $value == null ? null : $this->asDate($value)->toDateString());
	}

	public function changes(): HasMany {
		return $this->hasMany(Change::class, "transaction_id");
	}

	public function car(): BelongsTo {
		return $this->belongsTo(Car::class, "car_id")->withTrashed();
	}

	public function userFrom(): BelongsTo {
		return $this->belongsTo(User::class, "from_user_id")->withTrashed();
	}

	public function userTo(): BelongsTo {
		return $this->belongsTo(User::class, "to_user_id")->withTrashed();
	}

	public function otherUser(): BelongsTo {
		if ($this->from_user_id == Auth::id()) {
			return $this->userTo();
		} else {
			return $this->userFrom();
		}
	}

	public function getCreditAttribute(): float {
		if ($this->from_user_id == Auth::id()) {
			return $this->amount * -1;
		} else {
			return $this->amount;
		}
	}

	public function recalculate(): void {
		if ($this->kind != "drivetrak") {
			return;
		}
		$oldAuthorId = Change::$authorId;
		Change::$authorId = null; // system

		$fuelPrice = FuelPriceRepository::getFuelPriceAtTime($this->car->fuelType->fuel_type, $this->occurred_at);

		$this->update([
			"amount" => round($this->car->efficiency->efficiency * $fuelPrice->price * $this->distance, 2),
		]);

		Change::$authorId = $oldAuthorId;
	}
}
