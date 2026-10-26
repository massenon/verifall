@push('styles')
  <style>
    .fc { --fc-border-color: #E3E8E4; --fc-today-bg-color: #e8f8ef; --fc-page-bg-color: transparent; --fc-neutral-bg-color: #F6F8F5; font-size: 13px; }
    .dark .fc { --fc-border-color: #22352B; --fc-today-bg-color: rgba(9,184,80,.12); --fc-neutral-bg-color: #0A1510; --fc-list-event-hover-bg-color: #0A1510; }
    .fc .fc-toolbar-title { font-family: "Bricolage Grotesque", system-ui, sans-serif; font-size: 18px; font-weight: 600; }
    .fc .fc-button { background: transparent; border: 1px solid var(--fc-border-color); color: inherit; border-radius: 8px; padding: .35rem .7rem; font-size: 13px; text-transform: none; box-shadow: none !important; }
    .fc .fc-button:hover { background: var(--fc-neutral-bg-color); color: inherit; }
    .fc .fc-button-primary:not(:disabled).fc-button-active { background: #0E2A1E; border-color: #0E2A1E; color: #fff; }
    .dark .fc .fc-button-primary:not(:disabled).fc-button-active { background: #09B850; border-color: #09B850; color: #0E2A1E; }
    .fc .fc-event { border-radius: 6px; border: 0; padding: 1px 4px; font-weight: 500; }
    .fc .fc-daygrid-day-number, .fc .fc-col-header-cell-cushion { color: inherit; text-decoration: none; }
    .evt-live { background: #09B850; color: #0E2A1E; }
    .evt-examen { background: #0E2A1E; color: #fff; }
    .evt-maintenance { background: #fab219; color: #3b2a00; }
  </style>
@endpush

<div>
  <x-premium.entete titre="Calendrier" sous="Les lives, les sessions d'examen et les maintenances, au même endroit. Cliquez un événement pour ouvrir son écran." />

  <div class="flex flex-wrap items-center gap-4 mb-4 text-sm texte-2" aria-label="Légende">
    <span class="inline-flex items-center gap-1.5"><i class="w-3 h-3 rounded-sm" style="background:#09B850"></i>Live</span>
    <span class="inline-flex items-center gap-1.5"><i class="w-3 h-3 rounded-sm" style="background:#0E2A1E"></i>Examen</span>
    <span class="inline-flex items-center gap-1.5"><i class="w-3 h-3 rounded-sm" style="background:#fab219"></i>Maintenance</span>
  </div>

  <div class="carte p-4" wire:ignore>
    <div id="calendrier" data-source="{{ route('staff.calendar.data') }}" aria-label="Calendrier des événements"></div>
  </div>

  @push('scripts')
    <script src="{{ asset('assets/vendor/fullcalendar/fullcalendar.min.js') }}"></script>
    <script>
      (function () {
        var el = document.getElementById('calendrier');
        if (!el || !window.FullCalendar) { return; }
        var petit = window.matchMedia('(max-width: 640px)').matches;
        new FullCalendar.Calendar(el, {
          initialView: petit ? 'listMonth' : 'dayGridMonth',
          locale: 'fr', firstDay: 1, height: 'auto', nowIndicator: true,
          headerToolbar: { left: 'prev,next today', center: 'title', right: petit ? '' : 'dayGridMonth,timeGridWeek,listMonth' },
          buttonText: { today: "Aujourd'hui", month: 'Mois', week: 'Semaine', list: 'Liste' },
          events: { url: el.getAttribute('data-source'), failure: function () { el.insertAdjacentHTML('beforebegin', '<p class="text-sm text-critique mb-2" role="alert">Les événements n’ont pas pu être chargés.</p>'); } },
          eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false }
        }).render();
      })();
    </script>
  @endpush
</div>
