# Manual verification checklist

Use a separate local database and do not test destructive operations against valuable data.

1. Register a customer, sign out/in, and test an incorrect password.
2. Browse seeded courts; change date/time and confirm availability feedback updates.
3. Book a future slot. Verify pending status, unique reference, and hourly rate × duration.
4. Try overlapping same-time booking as another customer; expect a conflict. Cancel the first pending reservation; availability should omit it and the second customer should now book that time.
5. As admin, approve/reject reservations, cancel a confirmed reservation, and complete one. Check emails in the local outbox log.
6. Add/remove a maintenance block and verify its time is not bookable.
7. Add/edit/deactivate courts and upload an image. Confirm old bookings remain intact.
8. Search users, change a user's role, disable/enable their account. Verify disabled users cannot sign in.
9. Use Forgot Password. With `MAIL_MODE=log`, open `storage/mail/outbox.log`, use the reset link within one hour, then verify the link is single-use.
10. Use Postman or curl with `/api/v1/auth/register`, then send the returned bearer token to protected booking routes. Verify another customer cannot see or cancel that booking.

## Verification performed

On 2026-10-30, PHP lint passed for all 23 PHP files and `node --check` passed for the booking JavaScript. With XAMPP MySQL and the `courtly_raw_php` schema/seeds, local HTTP checks returned 200 for the home page, `/api/v1/courts`, and date availability. A live API workflow created a pending reservation, cancelled it, confirmed availability returned zero blocking reservations, then successfully rebooked the same slot as another customer. The web registration form created an account and rendered its signed-in dashboard. Admin promotion via `bin/make-admin.php` and the protected admin courts API returned all three seeded courts. The password-reset link was logged locally, accepted once, and the new password signed in successfully. Temporary verification accounts and bookings were removed after the checks.
