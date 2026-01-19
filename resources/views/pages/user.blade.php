@extends('layouts.default')
@section('title', $profile ? t('Profile') : ($user->id == null ? t('New User') : t(':user - User', ['user' =>
	$user->name])))
@section('content')

	<form id="impersonate" style="display:none" action="/u/{{ $user->id }}/impersonate" method="post">
		@csrf
	</form>

	<form method="post">
		@csrf
		<table>
			<tr>
				<td>@t('Username')</td>
				<td><x-input name="username" :value="$user->username" :autofocus="$user->id == null" required /></td>
				<td>@t('Not shown to others; used to log in')</td>
			</tr>
			<tr>
				<td>@t('Name')</td>
				<td><x-input name="name" :value="$user->name" required /></td>
				<td>@t('Shown to others')</td>
			</tr>
			<tr>
				<td>@t('New password')</td>
				<td><x-input name="password" type="password" :placeholder="t('leave blank to not change')" minlength="8" /></td>
				<td></td>
			</tr>
			<tr>
				<td>@t('Locale')</td>
				<td colspan="2"><x-select name="locale" :selected="$user->locale ?? 'en_CA'">
						@foreach (['en_CA', 'de'] as $locale)
							<x-select.option :value="$locale">@t('this_locale_name', locale: $locale)</x-select.option>
						@endforeach
					</x-select></td>
			</tr>

			{{-- if we're admin and this is not the profile page --}}
			@if (Auth::user()->is_admin and !$profile)
				<tr>
					<td></td>
					<td colspan="2"><label><x-input name="must_change_password" type="checkbox" :checked="$user->must_change_password" />
							@t('Must change password')</label>
					</td>
				</tr>
				<tr>
					<td></td>
					<td colspan="2"><label><x-input name="is_admin" type="checkbox" :checked="$user->is_admin" /> @t('Is admin')</label>
					</td>
				</tr>
			@endif
		</table>

		<button>Save</button>

		{{-- if we're admin and this is not the profile page --}}
		@if ($user->id and Auth::user()->is_admin and $user->id != Auth::id())
			@if ($user->deleted_at == null)
				<button name="delete" value="on">@t('Delete')</button>
				<button form="impersonate" type="submit">Impersonate</button>
			@else
				<button name="restore" value="on">@t('Restore')</button>
				@t('Deleted') <x-datetime :datetime="$user->deleted_at" relative />
			@endif
		@endif
	</form>

	@if ($user->id)
		<livewire:change-history :model="$user" />
	@endif

@endsection
