document.querySelectorAll('.booking-form').forEach(form => {
  const dateInput = form.querySelector('[name="booking_date"]');
  const timeInput = form.querySelector('[name="booking_time"]');
  const hoursInput = form.querySelector('[name="hours"]');
  const startInput = form.querySelector('[name="start_at"]');
  const endInput = form.querySelector('[name="end_at"]');
  const note = form.querySelector('.availability-note');
  const estimate = form.querySelector('.price-estimate');
  const button = form.querySelector('button[type="submit"]');
  const apiRoot = document.querySelector('meta[name="availability-url"]').content;
  let requestNumber = 0;

  function padded(number) { return String(number).padStart(2, '0'); }
  function selectedWindow() {
    if (!dateInput.value || !timeInput.value) return null;
    const start = new Date(`${dateInput.value}T${timeInput.value}:00`);
    const end = new Date(start.getTime() + Number(hoursInput.value) * 3600000);
    const format = value => `${value.getFullYear()}-${padded(value.getMonth()+1)}-${padded(value.getDate())} ${padded(value.getHours())}:${padded(value.getMinutes())}:00`;
    return { start, end, startValue: format(start), endValue: format(end), startMinute: format(start).slice(0,16).replace(' ','T'), endMinute: format(end).slice(0,16).replace(' ','T') };
  }

  async function refreshAvailability() {
    const current = ++requestNumber;
    button.disabled = true;
    const window = selectedWindow();
    if (!window) { note.textContent = 'Choose a date and start time.'; return; }
    startInput.value = window.startValue;
    endInput.value = window.endValue;
    estimate.textContent = `Estimated total · ₱${(Number(form.dataset.rate) * Number(hoursInput.value)).toLocaleString('en-PH',{minimumFractionDigits:2})}`;
    note.className = 'availability-note col-span-3 rounded bg-stone-50 px-3 py-2 text-xs text-slate-500';
    note.textContent = 'Checking availability…';
    try {
      const response = await fetch(`${apiRoot}/${form.dataset.court}/availability?date=${encodeURIComponent(dateInput.value)}`, { headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (current !== requestNumber) return;
      if (!response.ok) throw new Error(result.message || 'Availability check failed.');
      const occupied = [...result.data.bookings, ...result.data.maintenance].some(slot => {
        const start = slot.start_at.replace('T',' ').slice(0,16).replace(' ','T');
        const end = slot.end_at.replace('T',' ').slice(0,16).replace(' ','T');
        return start < window.endMinute && end > window.startMinute;
      });
      const endTime = `${padded(window.end.getHours())}:${padded(window.end.getMinutes())}`;
      const inHours = timeInput.value >= result.data.court.opens_at.slice(0,5) && endTime <= result.data.court.closes_at.slice(0,5) && window.end.toDateString() === window.start.toDateString();
      if (!inHours) { note.textContent = `Choose a time within ${result.data.court.opens_at.slice(0,5)}–${result.data.court.closes_at.slice(0,5)}.`; note.classList.add('notice-bad'); }
      else if (occupied) { note.textContent = 'That time is already booked or blocked. Choose another slot.'; note.classList.add('notice-bad'); }
      else if (window.start <= new Date()) { note.textContent = 'Choose a future start time.'; note.classList.add('notice-bad'); }
      else { note.textContent = 'This time looks available. It will be checked again when you submit.'; note.classList.add('notice-good'); button.disabled = false; }
    } catch (error) {
      if (current === requestNumber) { note.textContent = 'Could not check availability. The server will verify your selection when you submit.'; note.classList.add('notice-bad'); button.disabled = false; }
    }
  }

  [dateInput,timeInput,hoursInput].forEach(input => input.addEventListener('change', refreshAvailability));
  form.addEventListener('submit', event => {
    const window = selectedWindow();
    if (!window) { event.preventDefault(); return; }
    startInput.value = window.startValue;
    endInput.value = window.endValue;
  });
  refreshAvailability();
});
