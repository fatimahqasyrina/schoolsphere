document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('details > summary').forEach(function (summary) {
    if (summary.textContent.trim() !== 'Edit') return;
    summary.addEventListener('click', function (event) {
      event.preventDefault();
      var form = summary.parentElement.querySelector('form');
      if (!form) return;
      var dialog = document.createElement('dialog');
      dialog.className = 'event-edit-dialog';
      var title = document.createElement('h2'); title.textContent = 'Edit details';
      var copy = form.cloneNode(true);
      var actions = document.createElement('div'); actions.className = 'event-edit-dialog-actions';
      var cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'small-button'; cancel.textContent = 'Cancel';
      cancel.addEventListener('click', function () { dialog.close(); dialog.remove(); });
      actions.appendChild(cancel); copy.appendChild(actions); dialog.append(title, copy); document.body.appendChild(dialog);
      dialog.addEventListener('click', function (e) { if (e.target === dialog) { dialog.close(); dialog.remove(); } });
      dialog.showModal();
    });
  });
});
