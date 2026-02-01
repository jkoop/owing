<?php

namespace App\Models;

use App\Traits\Changeable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FriendCircle extends Model {
	use Changeable, SoftDeletes;

	public function users(): BelongsToMany {
		return $this->belongsToMany(User::class);
	}

	public function changes(): HasMany {
		return $this->hasMany(Change::class, "friend_circle_id");
	}
}
