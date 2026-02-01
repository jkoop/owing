@extends('layouts.default')
@section('title', $friendCircle->id == null ? t('New Friend Circle') : t(':name - Friend Circle', ['name' =>
	$friendCircle->name]))
@section('content')

	@can('isAdmin')
		<form method="post">
			@csrf
			<table>
				<tr>
					<td>@t('Name')</td>
					<td><x-input name="name" :value="$friendCircle->name" :autofocus="$friendCircle->id == null" required /></td>
				</tr>
				<tr>
					<td>@t('Users')</td>
					<td>
						<select name="user_ids[]" multiple size="10">
							@foreach (App\Models\User::withTrashed()->orderBy('name')->get() as $user)
								<option value="{{ $user->id }}" @selected(in_array($user->id, old('user_ids', $friendCircle->users->pluck('id')->toArray())))>{{ $user->name }}</option>
							@endforeach
						</select>
					</td>
				</tr>
			</table>

			<button>@t('Save')</button>

			@if ($friendCircle->id)
				@if ($friendCircle->deleted_at == null)
					<button name="delete" value="on">@t('Delete')</button>
				@else
					<button name="restore" value="on">@t('Restore')</button>
					@t('Deleted') <x-datetime :datetime="$friendCircle->deleted_at" relative />
				@endif
			@endif
		</form>
	@else
		<table>
			<tr>
				<th>@t('Name')</th>
				<td>{{ $friendCircle->name }}</td>
			</tr>
			<tr>
				<th>@t('Users')</th>
				<td>
					@foreach ($friendCircle->users->sortBy('name') as $user)
						<x-user :user="$user" />
						@if (!$loop->last)
							,
						@endif
					@endforeach
				</td>
			</tr>
		</table>

		@if ($friendCircle->deleted_at != null)
			@t('Deleted :datetime', ['datetime' => c('datetime', ['datetime' => $friendCircle->deleted_at, 'relative' => true])])
		@endif
	@endcan

	@if ($friendCircle->id)
		<livewire:change-history :model="$friendCircle" />
	@endif

@endsection
