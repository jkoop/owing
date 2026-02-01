<?php

namespace App\Models;

use App\Exceptions\ImpossibleStateException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Change extends Model {
	public function author(): BelongsTo {
		return $this->belongsTo(User::class, "author_id")->withTrashed();
	}

	public function car(): BelongsTo {
		return $this->belongsTo(Car::class, "car_id")->withTrashed();
	}

	public function transaction(): BelongsTo {
		return $this->belongsTo(Transaction::class, "transaction_id")->withTrashed();
	}

	public function user(): BelongsTo {
		return $this->belongsTo(User::class, "user_id")->withTrashed();
	}

	public function friendCircle(): BelongsTo {
		return $this->belongsTo(FriendCircle::class, "friend_circle_id")->withTrashed();
	}

	public function getTargetAttribute() {
		return $this->transaction ??
			($this->car ?? ($this->friendCircle ?? ($this->user ?? throw new ImpossibleStateException())));
	}

	/**
	 * @var int|null $id int for user, null for system, 0 for logged in user
	 */
	public static int|null $authorId = 0;

	public static function record(Model $target, string $description): void {
		$authorId = self::$authorId;
		if ($authorId === 0) {
			$authorId = Auth::id();
		}

		$details = ["author_id" => $authorId, "description" => $description];

		switch (get_class($target)) {
			case Car::class:
				$details["car_id"] = $target->id;
				self::create($details);
				break;
			case Transaction::class:
				$details["transaction_id"] = $target->id;
				self::create($details);
				break;
			case User::class:
				$details["user_id"] = $target->id;
				self::create($details);
				break;
			case FriendCircle::class:
				$details["friend_circle_id"] = $target->id;
				self::create($details);
				break;
			default:
				throw new ImpossibleStateException();
		}
	}
}
