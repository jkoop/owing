<ul>
	@foreach ($users as $user)
		<li class="opacity-50"><x-user :user="$user" /> <x-spinner /></li>
	@endforeach
</ul>
