document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.teacher-table, .portal-table').forEach((table) => {
    const scrollArea = table.closest('.teacher-table-wrap, .portal-table-scroll');
    const rows = [...table.querySelectorAll('tbody tr')];
    if (!scrollArea || !rows.some((row) => !row.querySelector('.empty-cell'))) return;

    const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    const searchableHeaders = headers.filter((header) => header && !/^(action|proof|view|teacher action)$/i.test(header));
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'table-search-control';
    const searchBy = searchableHeaders.length ? searchableHeaders.join(', ') : 'all visible details';
    search.placeholder = 'Type a name or keyword';
    search.setAttribute('aria-label', `Search by ${searchBy}`);
    search.autocomplete = 'off';

    const wrapper = document.createElement('div');
    wrapper.className = 'table-search-label';
    const caption = document.createElement('span');
    caption.className = 'table-search-caption';
    caption.textContent = 'Search records';
    wrapper.append(caption);
    const fields = document.createElement('span');
    fields.className = 'table-search-fields';
    fields.textContent = `Search by: ${searchBy}`;
    wrapper.append(fields);

    const controls = document.createElement('div');
    controls.className = 'table-search-controls';
    controls.append(search);

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'table-search-clear';
    clear.textContent = 'Clear';
    clear.addEventListener('click', () => { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); });
    controls.append(clear);
    wrapper.append(controls);
    scrollArea.parentNode.insertBefore(wrapper, scrollArea);

    const empty = document.createElement('p');
    empty.className = 'table-search-empty';
    empty.textContent = 'No matching records.';
    empty.hidden = true;
    scrollArea.parentNode.insertBefore(empty, scrollArea.nextSibling);

    search.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase();
      const searchableCells = [...table.querySelectorAll('thead th')].map((th, index) => ({
        index,
        name: th.textContent.trim(),
      })).filter(({ name }) => name && !/^(action|proof|view|teacher action)$/i.test(name)).map(({ index }) => index);
      let visibleRows = 0;
      rows.forEach((row) => {
        const isPlaceholder = row.querySelector('.empty-cell');
        const rowText = [...row.cells].filter((cell, index) => searchableCells.includes(index)).map((cell) => cell.textContent).join(' ');
        const matches = !query || (isPlaceholder ? false : rowText.toLocaleLowerCase().includes(query));
        row.hidden = !matches;
        if (matches && !isPlaceholder) visibleRows += 1;
      });
      empty.hidden = !query || visibleRows > 0;
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  if (window.location.pathname.endsWith('/teacher_registrations.php')) {
    document.querySelectorAll('.teacher-table tbody tr').forEach((row) => {
      const current = row.cells[3]?.textContent.trim();
      const form = row.cells[4]?.querySelector('form');
      if (!form || !['Approved', 'Rejected'].includes(current)) return;
      const csrf = form.querySelector('[name="csrf"]')?.cloneNode(true);
      const registration = form.querySelector('[name="registrationID"]')?.cloneNode(true);
      if (!csrf || !registration) return;
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'teacher-action-link';
      edit.textContent = 'Edit';
      const showEdit = () => { form.replaceChildren(csrf.cloneNode(true), registration.cloneNode(true), edit); };
      edit.addEventListener('click', () => {
        const choice = document.createElement('select');
        choice.name = 'status';
        [['Approved', 'Approve'], ['Rejected', 'Reject']].forEach(([value, label]) => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = label;
          option.selected = value === current;
          choice.append(option);
        });
        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'teacher-primary-button';
        save.textContent = 'Save decision';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'teacher-secondary-button';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', showEdit);
        form.replaceChildren(csrf.cloneNode(true), registration.cloneNode(true), choice, save, cancel);
      });
      showEdit();
    });
  }

  if (window.location.pathname.endsWith('/teacher_events.php')) {
    document.querySelectorAll('.teacher-event-card').forEach(async (card) => {
      if (!card.querySelector('.teacher-action-edit')) return;
      const details = card.querySelector('.teacher-event-committee');
      const eventLink = card.querySelector('a[href*="event_detail.php?eventID="]');
      const eventId = eventLink && new URL(eventLink.href).searchParams.get('eventID');
      if (!details || !eventId) return;
      try {
        const response = await fetch(`teacher_committee_proofs.php?eventID=${encodeURIComponent(eventId)}`, { credentials: 'same-origin' });
        if (!response.ok) return;
        const proofs = await response.json();
        if (!proofs.length) return;
        const proofList = document.createElement('div');
        proofList.className = 'committee-proof-links';
        const heading = document.createElement('strong');
        heading.textContent = 'Submitted proof';
        proofList.append(heading);
        proofs.forEach((proof) => {
          const item = document.createElement('p');
          const link = document.createElement('a');
          link.href = proof.url;
          link.dataset.proofViewer = '';
          link.dataset.proofType = 'image';
          link.textContent = `View ${proof.task} · ${proof.teacher}`;
          item.append(link);
          proofList.append(item);
        });
        details.append(proofList);
      } catch (_) { /* Keep event cards usable if proof details cannot load. */ }
    });
  }
});
