<?php $title = 'Sign in'; ?>

<section class="mx-auto grid max-w-5xl gap-10 px-5 py-14 md:grid-cols-2 md:items-center">
	<div>
		<p class="eyebrow">WELCOME BACK</p>
		<h1 class="mt-5 font-display text-6xl font-extrabold tracking-tight">
			Your next<br>
			<em class="font-serif font-normal">game awaits.</em>
		</h1>
	</div>

	<form method="post" action="<?= h(url('login')) ?>" class="form-card">
		<?= csrf_field() ?>
		<p class="eyebrow">MEMBER SIGN IN</p>
		<h2 class="font-display text-2xl font-bold">Good to see you.</h2>

		<label class="field-label">
			Email address
			<input
				name="email"
				type="email"
				value="<?= h($email ?? '') ?>"
				required
				autocomplete="email"
			>
		</label>
		<label class="field-label">
			Password
			<input
				name="password"
				type="password"
				required
				autocomplete="current-password"
			>
		</label>

		<button class="primary-button">Sign in <span>→</span></button>
		<a class="text-center text-sm text-lime-800 underline" href="<?= h(url('forgot')) ?>">
			Forgot your password?
		</a>
		<p class="text-center text-xs text-slate-500">
			New to Reserve-it?
			<a class="text-lime-800 underline" href="<?= h(url('register')) ?>">Create an account</a>
		</p>
	</form>
</section>
