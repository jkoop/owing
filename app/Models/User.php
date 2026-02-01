<?php

namespace App\Models;

use App\Traits\Changeable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

	public function friendCircles(): BelongsToMany {
		return $this->belongsToMany(FriendCircle::class);
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

	/**
	 * Get users that this user can see (share a friend circle with, or owe each other money)
	 */
	public function getVisibleUsers(): Builder {
		// Get users from shared friend circles
		$friendCircleUserIds = $this->friendCircles()
			->with("users")
			->get()
			->flatMap(fn($circle) => $circle->users->pluck("id"))
			->unique()
			->filter(fn($id) => $id != $this->id)
			->toArray();

		// Also include users that owe each other money (have transactions together)
		$transactionUserIds = Transaction::where(function ($query) {
			$query->where("from_user_id", $this->id)->orWhere("to_user_id", $this->id);
		})
			->select("from_user_id", "to_user_id")
			->get()
			->flatMap(fn($transaction) => [$transaction->from_user_id, $transaction->to_user_id])
			->unique()
			->filter(fn($id) => $id != $this->id)
			->toArray();

		$allVisibleIds = array_unique(array_merge($friendCircleUserIds, $transactionUserIds));

		if (empty($allVisibleIds)) {
			return User::whereRaw("1 = 0"); // Return empty result set
		}

		return User::whereIn("id", $allVisibleIds);
	}
}
