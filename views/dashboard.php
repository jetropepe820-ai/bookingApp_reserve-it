<?php $title = 'My bookings'; ?>

<section class="mx-auto max-w-6xl px-5 py-12">
	<div class="mb-10 flex flex-wrap items-center justify-between gap-5">
		<div>
			<p class="eyebrow">YOUR RESERVE-IT</p>
			<h1 class="mt-2 font-display text-4xl font-extrabold">
				Hey, <?php echo h(explode(' ', $user['name'])[0]); ?>.
			</h1>
			<p class="mt-2 text-sm text-slate-500">All your game days, in one place.</p>
		</div>
		<a href="<?php echo h(url('home')); ?>#courts" class="primary-button !mt-0">
			Book a court <span>↗</span>
		</a>
	</div>

	<div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-4">
		<span class="eyebrow">RESERVATION HISTORY</span>
		<div class="flex gap-4 text-xs">
			<a href="<?php echo h(url('dashboard')); ?>">All</a>
			<?php foreach (['pending', 'confirmed', 'cancelled', 'completed'] as $filter): ?>
				<a
					href="<?php echo h(url('dashboard', ['status' => $filter])); ?>"
					class="capitalize hover:text-lime-800"
				><?php echo h($filter); ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if (! $bookings): ?>
		<div class="rounded-lg bg-white p-12 text-center text-sm text-slate-500">
			No reservations match this filter.
			<a class="text-lime-800 underline" href="<?php echo h(url('home')); ?>#courts">
				Find a court →
			</a>
		</div>
	<?php else: ?>
		<div class="divide-y divide-stone-200 bg-white">
			<?php foreach ($bookings as $booking): ?>
				<article class="flex flex-wrap items-center gap-4 p-5 sm:gap-6">
					<div class="grid h-16 w-16 shrink-0 content-center justify-items-center bg-lime-50">
						<b class="font-display text-2xl">
							<?php echo h(date('d', strtotime($booking['start_at']))); ?>
						</b>
						<span class="text-[10px] uppercase">
							<?php echo h(date('M Y', strtotime($booking['start_at']))); ?>
						</span>
					</div>
					<div class="min-w-48 flex-1">
						<p class="eyebrow"><?php echo h($booking['sport']); ?></p>
						<h2 class="mt-1 font-display font-bold">
							<?php echo h($booking['court_name']); ?>
						</h2>
						<p class="mt-1 text-xs text-slate-500">
							<?php echo h(format_datetime($booking['start_at'], 'l, g:i A')); ?>
							–
							<?php echo h(format_datetime($booking['end_at'], 'g:i A')); ?>
						</p>
						<small class="text-[10px] text-slate-400">
							REF <?php echo h($booking['reference_code']); ?>
						</small>
					</div>
					<div class="grid justify-items-end gap-2">
						<b class="font-display">
							₱<?php echo number_format((float) $booking['total_price'], 2); ?>
						</b>
						<?php echo active_status($booking['status']); ?>
					</div>
					<?php if (
						in_array($booking['status'], ['pending', 'confirmed'], true)
						&& strtotime($booking['start_at']) > time()
					): ?>
						<form
							method="post"
							action="<?php echo h(url('booking.cancel')); ?>"
							onsubmit="return confirm('Cancel this reservation?')"
						>
							<?php echo csrf_field(); ?>
							<input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
							<button class="text-xs text-rose-700 underline">Cancel</button>
						</form>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
