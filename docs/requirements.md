# Requirements and behavior

- **Visitors:** browse active courts, rates, photos, operating hours, and date-specific availability.
- **Customers:** register and sign in; create pending reservations; view their own reservation history; cancel future pending/confirmed bookings; request password reset.
- **Administrators:** view booking totals and filtered reservation lists; approve/reject pending bookings; cancel or complete confirmed bookings; create/update/deactivate courts; upload court photos; create/remove maintenance blocks; change customer roles or disable accounts.
- **Availability:** pending and confirmed reservations and maintenance blocks prevent overlapping bookings. Cancelled/rejected/completed reservations no longer block time. Intervals are start-inclusive/end-exclusive. The server repeats the conflict check under a transaction while locking the court row.
- **Price:** hourly rate is copied onto a booking at creation. Total is the snapshotted rate times the number of whole hours.
- **Notifications:** submission, decision, cancellation, and password reset messages are created. Local mode logs them; configured PHP mail may deliver them.
- **Stack:** PHP 8.1+, PDO/MySQL, simple PHP views and controllers, session authentication for web requests, random expiring bearer tokens for the REST API, Tailwind Play CDN for local styling.
