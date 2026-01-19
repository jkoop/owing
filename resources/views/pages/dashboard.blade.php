@extends('layouts.default')
@section('title', t('Dashboard'))
@section('content')

	@vite('resources/js/dashboard.js')
	<livewire:owing-totals />

	<h2>@t('Ledger') <a class="text-base decoration-dotted" href="https://github.com/jkoop/owing/wiki/Ledger"
			target="_blank">(?)</a></h2>

	<table>
		<thead>
			<tr>
				<th class="text-normal" colspan="6">
					<div class="mb-4 flex flex-row flex-wrap gap-4 bg-blue-100 p-2">
						<label>
							<input name="deleted" type="checkbox" onchange="resetTable()" />
							@t('Show deleted')
						</label>
						<label>
							@t('Sort by')
							<select name="order_by" onchange="resetTable()">
								<option value="occurred_at" selected>@t('Occurred at')</option>
								<option value="updated_at">@t('Updated at')</option>
							</select>
						</label>
						<label>
							@t('Filter by user')
							<select name="user_id" onchange="resetTable()">
								<option selected></option>
								@foreach ($users as $user)
									<option value="{{ $user->id }}">{{ $user->name }}</option>
								@endforeach
							</select>
						</label>
						<label>
							@t('Filter by memo')
							<input name="memo" oninput="resetTable()" />
						</label>
						<a class="ml-auto" href="/t/new">@t('New')</a>
					</div>
				</th>
			</tr>
			<tr>
				<th>@t('Occurred at')</th>
				<th>@t('User')</th>
				<th>@t('Credit')</th>
				<th>@t('Car')</th>
				<th>@t('Memo')</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<tr class="loading">
				<td colspan=5><x-spinner /></td>
			</tr>
		</tbody>
	</table>

@endsection
