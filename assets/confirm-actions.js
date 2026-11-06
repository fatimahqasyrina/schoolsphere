document.addEventListener('click', function (event) {
  var link = event.target.closest('a[href]');
  if (!link) return;
  var url = new URL(link.href, window.location.href);
  if (url.pathname.endsWith('/logout.php') && !window.confirm('Are you sure you want to log out?')) {
    event.preventDefault(); event.stopImmediatePropagation(); return;
  }
  if ((url.searchParams.has('edit') || link.matches('[data-edit-confirm]')) && /edit/i.test(link.textContent) && !window.confirm('Do you want to edit this item?')) {
    event.preventDefault(); event.stopImmediatePropagation();
  }
}, true);

document.addEventListener('click', function (event) {
  var summary = event.target.closest('details > summary');
  if (summary && /edit/i.test(summary.textContent.trim()) && !window.confirm('Do you want to edit this item?')) {
    event.preventDefault(); event.stopImmediatePropagation();
  }
}, true);

document.addEventListener('submit', function (event) {
  var form = event.target;
  var button = event.submitter;
  if (!form || !button || (form.getAttribute('onsubmit') || '').includes('confirm(') || (button.getAttribute('onclick') || '').includes('confirm(')) return;
  var name = (button.name || '').toLowerCase();
  var text = (button.textContent || button.value || '').trim().toLowerCase();
  var deleting = name.startsWith('delete_') || name.startsWith('cancel_') || /delete|remove account|remove mark|cancel event/.test(text) || form.classList.contains('delete-account-form');
  if (deleting) {
    if (!window.confirm('Are you sure you want to delete this?')) { event.preventDefault(); event.stopImmediatePropagation(); }
    return;
  }
  var editing = name.startsWith('edit_') || name.startsWith('save_') || name.startsWith('update_') || ['status','check_in','mark_absent','verify_payment','review_report','change_principal'].includes(name) || /^(save|save changes|save profile|save task|save review and notify teacher|save attendance|update|update profile|update and resend|mark present|mark absent)$/.test(text) || (form.querySelector('input[name="eventID"]') && form.querySelector('input[name="eventID"]').value && /save|update/i.test(text));
  if (editing && !window.confirm('Are you sure you want to save these changes?')) { event.preventDefault(); event.stopImmediatePropagation(); }
}, true);
