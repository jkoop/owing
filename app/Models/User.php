<?php

namespace App\Models;

use App\Traits\Changeable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract {
	use Authenticatable, Authorizable, Changeable, SoftDeletes;

	protected $hidden = ["password"];
	protected $casts = [
		"password" => "hashed",
		"is_admin" => "boolean",
	];

	public function cars(): HasMany {
		return $this->hasMany(Car::class, "owner_id");
	}

	public function changes(): HasMany {
		return $this->hasMany(Change::class, "user_id");
	}

	public function transactions(): Builder {
		return Transaction::where(function ($query) {
			$query->where("from_user_id", $this->id)->orWhere("to_user_id", $this->id);
		});
	}

	public function transactionsFrom(): HasMany {
		return $this->hasMany(Transaction::class, "from_user_id")->where("occurred_at", "<=", now()->timestamp);
	}

	public function transactionsTo(): HasMany {
		return $this->hasMany(Transaction::class, "to_user_id")->where("occurred_at", "<=", now()->timestamp);
	}

	public function getBalanceAttribute(User $otherGuy = null): float {
		$to = $this->transactionsTo();
		$from = $this->transactionsFrom();

		if ($otherGuy != null) {
			$to = $to->where("from_user_id", $otherGuy->id);
			$from = $from->where("to_user_id", $otherGuy->id);
		}

		return round(
			$to->selectRaw('SUM("amount") as "amount"')->firstOrFail()->amount -
				$from->selectRaw('SUM("amount") as "amount"')->firstOrFail()->amount,
			2,
		);
	}

	private array $owingMemo = [];

	public function getOwing(User $user): float {
		if (isset($this->owingMemo[$user->id])) {
			return $this->owingMemo[$user->id];
		}

		return $this->owingMemo[$user->id] = (function () use ($user) {
			$owing = $this->getBalanceAttribute($user);
			if ($owing == 0) {
				return 0;
			} // avoids returning negative zero
			return $owing;
		})();
	}
}
