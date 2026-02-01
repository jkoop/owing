@extends('layouts.default')
@section('title', t('System'))
@section('content')

	<h2><a name="cars"></a> Cars</h2>

	<table>
		<thead>
			<tr>
				<th class="font-normal" colspan="{{ request()->has('deleted') ? 5 : 4 }}">
					<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
						@if (request()->has('deleted'))
							<a href="/system#cars">@t('Hide deleted')</a>
						@else
							<a href="/system?deleted#cars">@t('Show deleted')</a>
						@endif
						<a class="ml-auto" href="/c/new">@t('New')</a>
					</div>
				</th>
			</tr>
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

	@can('isAdmin')
		<h2><a name="users"></a> Users</h2>

		<table>
			<thead>
				<tr>
					<th class="font-normal" colspan="{{ request()->has('deleted') ? 6 : 5 }}">
						<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
							@if (request()->has('deleted'))
								<a href="/system#users">@t('Hide deleted')</a>
							@else
								<a href="/system?deleted#users">@t('Show deleted')</a>
							@endif
							<a class="ml-auto" href="/u/new">@t('New')</a>
						</div>
					</th>
				</tr>
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

	<h2><a name="friend-circles"></a> Friend Circles</h2>

	<table>
		<thead>
			<tr>
				<th class="font-normal" colspan="{{ request()->has('deleted') ? 3 : 2 }}">
					@can('isAdmin')
						<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
							@if (request()->has('deleted'))
								<a href="/system#friend-circles">@t('Hide deleted')</a>
							@else
								<a href="/system?deleted#friend-circles">@t('Show deleted')</a>
							@endif
							<a class="ml-auto" href="/fc/new">@t('New')</a>
						</div>
					@else
						@if (request()->has('deleted'))
							<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
								<a href="/system#friend-circles">@t('Hide deleted')</a>
							</div>
						@endif
					@endcan
				</th>
			</tr>
			<tr>
				<th>@t('Name')</th>
				<th>@t('Users')</th>
				@if (request()->has('deleted'))
					<th>@t('Deleted')</th>
				@endif
			</tr>
		</thead>
		<tbody>
			@foreach ($friendCircles->sortBy('name') as $friendCircle)
				<tr>
					<td>
						@can('isAdmin')
							<a href="/fc/{{ $friendCircle->id }}">{{ $friendCircle->name }}</a>
						@else
							{{ $friendCircle->name }}
						@endcan
					</td>
					<td>
						@foreach ($friendCircle->users->sortBy('name') as $user)
							<x-user :user="$user" />
							@if (!$loop->last)
								,
							@endif
						@endforeach
						@if ($friendCircle->users->count() < 1)
							<i>@t('no users')</i>
						@endif
					</td>
					@if (request()->has('deleted'))
						<td><x-datetime :datetime="$friendCircle->deleted_at" relative /></td>
					@endif
				</tr>
			@endforeach
			@if ($friendCircles->count() < 1)
				<tr>
					<td colspan="{{ request()->has('deleted') ? 3 : 2 }}"><i>@t('no friend circles')</i></td>
				</tr>
			@endif
		</tbody>
	</table>

@endsection
