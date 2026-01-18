@extends('layouts.default')
@section('title', t('Export Transactions'))
@section('content')

	<form class="no-spinner" method="post" target="_blank">
		@csrf

		<select name="user_id" required>
			<option value="">@t('[pick a user]')</option>
			<option value="0">@t('[all users]')</option>
			@foreach ($users as $user)
				<option value="{{ $user->getKey() }}">
					{{ $user->name }}
				</option>
			@endforeach
		</select><br>

		<label>
			<input name="deleted" type="checkbox" />
			@t('include deleted')
		</label><br>

		<button>@t('Export')</button>
	</form>

@endsection
