(() => {
  const modal = document.createElement('dialog');
  modal.className = 'proof-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-label', 'Submitted proof');
  modal.innerHTML = '<div class="proof-modal-dialog"><header class="proof-modal-header"><strong>Submitted proof</strong><button type="button" class="proof-modal-close" aria-label="Close proof">×</button></header><div class="proof-modal-content"></div></div>';
  document.body.append(modal);
  const content = modal.querySelector('.proof-modal-content');
  const close = () => { if (modal.open) modal.close(); };
  modal.querySelector('.proof-modal-close').addEventListener('click', close);
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
  modal.addEventListener('close', () => content.replaceChildren());
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-proof-viewer]');
    if (!link) return;
    event.preventDefault();
    const url = link.href;
    const type = link.dataset.proofType || 'image';
    modal.querySelector('.proof-modal-header strong').textContent = link.dataset.proofTitle || 'Submitted proof';
    const loading = document.createElement('p');
    loading.className = 'proof-loading';
    loading.textContent = type === 'pdf' ? 'Loading proof…' : 'Loading image…';
    content.replaceChildren(loading);
    if (type === 'pdf') {
      const frame = document.createElement('iframe');
      frame.title = 'Payment proof PDF';
      frame.src = url;
      frame.addEventListener('load', () => loading.remove(), { once: true });
      content.append(frame);
    } else {
      const image = document.createElement('img');
      image.alt = 'Submitted proof';
      image.src = url;
      image.addEventListener('load', () => loading.remove(), { once: true });
      image.addEventListener('error', () => {
        const message = document.createElement('p');
        message.textContent = 'This proof image could not be displayed. The saved file may be missing or unreadable.';
        const open = document.createElement('a');
        open.href = url;
        open.target = '_blank';
        open.rel = 'noopener';
        open.textContent = 'Try opening the proof in a new tab';
        content.replaceChildren(message, open);
      }, { once: true });
      content.append(image);
    }
    modal.showModal();
    modal.querySelector('.proof-modal-close').focus();
  });
})();
