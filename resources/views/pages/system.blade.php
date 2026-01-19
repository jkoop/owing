@extends('layouts.default')
@section('title', t('System'))
@section('content')

	<h2><a name="cars"></a> Cars</h2>

	<table>
		<thead>
			<tr><th colspan="{{ request()->has('deleted') ? 5 : 4 }}" class="text-normal">
					<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
						@if (request()->has('deleted'))
							<a href="/system#cars">@t('Hide deleted')</a>
						@else
							<a href="/system?deleted#cars">@t('Show deleted')</a>
						@endif
						<a class="ml-auto" href="/c/new">@t('New')</a>
					</div>
			</th></tr>
			<tr>
				<th>@t('Name')</th>
				<th>@t('Efficiency')</th>
				<th>@t('Fuel Type')</th>
				<th>@t('Owner')</th>
				@if (request()->has('deleted'))
					<th>@t('Deleted')</th>
				@endif
			</tr>
		</thead>
		<tbody>
			@foreach ($cars->sortBy('name') as $car)
				<tr>
					<td><x-car :car="$car" /></td>
					<td>{{ number_format($car->efficiency->efficiency, 4) }}L/km; <x-dollar-efficiency :car="$car" /></td>
					<td>@t(App\Models\CarFuelType::FUEL_TYPES[$car->fuelType->fuel_type])</td>
					<td><x-user :user="$car->owner" /></td>
					@if (request()->has('deleted'))
						<td><x-datetime :datetime="$car->deleted_at" relative /></td>
					@endif
				</tr>
			@endforeach
			@if ($cars->count() < 1)
				<tr>
					<td colspan="3"><i>@t('no cars')</i></td>
				</tr>
			@endif
		</tbody>
	</table>

	@can("isAdmin")
<h2><a name="users"></a> Users</h2>

	<table>
		<thead>
			<tr><th colspan="{{ request()->has('deleted') ? 6 : 5 }}" class="text-normal">
					<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
	@if (request()->has('deleted'))
			<a href="/system#users">@t('Hide deleted')</a>
		@else
			<a href="/system?deleted#users">@t('Show deleted')</a>
		@endif
		<a class="ml-auto" href="/u/new">@t('New')</a>
					</div>
			</th></tr>
			<tr>
				<th>@t('Name')</th>
				<th>@t('Username')</th>
				<th>@t('Balance')</th>
				<th>@t('Last Transaction')</th>
				<th>@t('Is Admin?')</th>
				@if (request()->has('deleted'))
					<th>@t('Deleted')</th>
				@endif
			</tr>
		</thead>
		<tbody>
			@foreach ($users->sortBy('name') as $user)
				<tr>
					<td><x-user :user="$user" /></td>
					<td>{{ $user->username }}</td>
					<td>${{ number_format($user->balance, 2) }}</td>
					<td><x-datetime :datetime="$user->transactions()->orderByDesc('occurred_at')->first()?->occurred_at" relative /></td>
					<td>@t($user->is_admin ? 'true' : 'false')</td>
					@if (request()->has('deleted'))
						<td><x-datetime :datetime="$user->deleted_at" relative /></td>
					@endif
				</tr>
			@endforeach
			@if ($users->count() < 1)
				<tr>
					<td colspan="3"><i>@t('no users')</i></td>
				</tr>
			@endif
		</tbody>
	</table>
	@endcan

@endsection
