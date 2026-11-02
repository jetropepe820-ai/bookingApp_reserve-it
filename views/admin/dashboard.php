<?php $title = 'Admin dashboard'; ?>

<section class="mx-auto max-w-7xl px-5 py-12">
	<div class="mb-9 flex flex-wrap items-center justify-between gap-4">
		<div>
			<p class="eyebrow">RESERVE-IT CONTROL ROOM</p>
			<h1 class="mt-2 font-display text-4xl font-extrabold">Facility overview.</h1>
			<p class="mt-2 text-sm text-slate-500">Bookings, schedules, and today at a glance.</p>
		</div>
		<div class="flex gap-2">
			<a class="primary-button !mt-0" href="<?= h(url('admin.courts')) ?>">
				Manage courts ↗
			</a>
			<a class="rounded border border-slate-300 px-4 py-3 text-sm font-bold" href="<?= h(url('admin.users')) ?>">
				Manage users
			</a>
		</div>
	</div>

	<div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
		<?php foreach ([
			['TODAY', $metrics['today']],
			['PENDING', $metrics['pending']],
			['CONFIRMED', $metrics['confirmed']],
			['BOOKED REVENUE', '₱' . number_format($metrics['revenue'], 0)],
		] as [$label, $value]): ?>
			<article class="border border-stone-200 bg-white p-5">
				<p class="eyebrow"><?= h($label) ?></p>
				<p class="mt-3 font-display text-3xl font-bold"><?= h($value) ?></p>
				<small class="mt-2 block text-xs text-slate-400"></small>
			</article>
		<?php endforeach; ?>
	</div>

	<form class="mb-5 flex flex-wrap gap-2" method="get" action="<?= h(url('admin')) ?>">
		<input class="control" type="date" name="date" value="<?= h($_GET['date'] ?? '') ?>">
		<select class="control" name="status">
			<option value="">All statuses</option>
			<?php foreach (['pending', 'confirmed', 'rejected', 'cancelled', 'completed'] as $status): ?>
				<option
					value="<?= h($status) ?>"
					<?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>
				><?= h(ucfirst($status)) ?></option>
			<?php endforeach; ?>
		</select>
		<input
			class="control"
			name="search"
			placeholder="Reference or customer"
			value="<?= h($_GET['search'] ?? '') ?>"
		>
		<button class="primary-button !mt-0">Filter</button>
		<a class="self-center px-3 text-xs underline" href="<?= h(url('admin')) ?>">Clear</a>
	</form>

	<div class="overflow-x-auto border border-stone-200 bg-white">
		<table class="w-full min-w-[900px] text-left text-xs">
			<thead class="bg-stone-100 text-[10px] tracking-wider text-slate-500">
				<tr>
					<?php foreach (['REFERENCE', 'CUSTOMER', 'COURT', 'DATE & TIME', 'TOTAL', 'STATUS / ACTION'] as $heading): ?>
						<th class="px-4 py-3"><?= h($heading) ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($bookings as $booking): ?>
					<tr class="border-t border-stone-100">
						<td class="px-4 py-4 font-bold"><?= h($booking['reference_code']) ?></td>
						<td class="px-4 py-4">
							<?= h($booking['customer_name']) ?>
							<small class="mt-1 block text-slate-400">
								<?= h($booking['customer_email']) ?>
							</small>
						</td>
						<td class="px-4 py-4"><?= h($booking['court_name']) ?></td>
						<td class="px-4 py-4">
							<?= h(format_datetime($booking['start_at'], 'M j, Y')) ?>
							<small class="mt-1 block text-slate-400">
								<?= h(format_datetime($booking['start_at'], 'g:i A')) ?>
								–
								<?= h(format_datetime($booking['end_at'], 'g:i A')) ?>
							</small>
						</td>
						<td class="px-4 py-4">
							₱<?= number_format((float) $booking['total_price'], 2) ?>
						</td>
						<td class="space-x-1 px-4 py-4">
							<?= active_status($booking['status']) ?>
							<?php foreach (
								(
									$booking['status'] === 'pending'
									? ['confirmed' => 'Approve', 'rejected' => 'Reject', 'cancelled' => 'Cancel']
									: ($booking['status'] === 'confirmed'
										? ['cancelled' => 'Cancel', 'completed' => 'Complete']
										: [])
								) as $next => $label
							): ?>
								<form
									class="inline"
									method="post"
									action="<?= h(url('admin.booking.status')) ?>"
									<?= $next === 'cancelled'
										? 'onsubmit="return confirm(\'Cancel this reservation?\')"'
										: '' ?>
								>
									<?= csrf_field() ?>
									<input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
									<input type="hidden" name="status" value="<?= h($next) ?>">
									<button
										class="rounded border px-2 py-1 <?= $next === 'rejected' || $next === 'cancelled'
											? 'border-rose-200 text-rose-700'
											: 'border-lime-200 text-lime-800' ?>"
									><?= h($label) ?></button>
								</form>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if (! $bookings): ?>
					<tr>
						<td colspan="6" class="p-12 text-center text-slate-500">
							No bookings found for these filters.
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>
