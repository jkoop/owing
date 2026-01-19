<ul>
	@foreach (App\Models\User::where('id', '!=', Auth::id())->orderBy('name')->get() as $user)
		<li class="opacity-50"><x-user :user="$user" /> <x-spinner /></li>
	@endforeach
</ul>
