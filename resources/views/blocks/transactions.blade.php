@foreach ($transactions as $transaction)
	<tr @if ($transaction->occurred_at->timestamp > now()->timestamp) class="future" @endif>
		<td><x-datetime :datetime="$transaction->occurred_at" /></td>
		<td><x-user :user="$transaction->otherUser" /></td>
		<td>
			@if ($transaction->deleted_at !== null)
				<del>${{ number_format($transaction->credit, 2) }}</del>
			@else
				${{ number_format($transaction->credit, 2) }}
			@endif
		</td>
		<td><x-car :car="$transaction->car" /></td>
		<td>
			@if ($transaction->memo != null)
				{{ $transaction->memo }}
			@else
				<i>@t('no memo')</i>
			@endif
		</td>
		<td>
			<a class="inline-block" href="/t/{{ $transaction->id }}">
				@can('update', $transaction)
					@t('edit')
				@else
					@t('view')
				@endcan
			</a>
			<a href="/t/new?clone={{ $transaction->id }}">@t('clone')</a>
		</td>
	</tr>
@endforeach

@if ($transactions->count() < 50)
	<tr class="end">
		<td colspan="6"><i>@t("You've reached the end")</i></td>
	</tr>
@endif
