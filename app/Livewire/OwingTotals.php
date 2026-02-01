<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
final class OwingTotals extends Component {
	public function render() {
		$users = Auth::user()->getVisibleUsers()->withTrashed()->orderBy("name")->get();
		return view("livewire.owing-totals.index", compact("users"));
	}

	public function placeholder() {
		$users = Auth::user()->getVisibleUsers()->orderBy("name")->get();
		return view("livewire.owing-totals.placeholder", compact("users"));
	}
}
