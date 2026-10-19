// SPDX-License-Identifier: GPL-3.0-or-later
'use strict';

/** @returns {void} */
function updateQuizTotal() {
  const target = document.querySelector('#quiz-total');
  if (!target) return;
  const total = Array.from(document.querySelectorAll('#quiz-questions .question-points')).reduce((sum, field) => sum + Number(field.value || 0), 0);
  target.textContent = `${total} / 30 points`;
}

/** @returns {void} */
function setupQuizEditor() {
  const holder = document.querySelector('#quiz-questions');
  if (!holder) return;
  let next = holder.children.length;
  document.querySelector('#add-question').addEventListener('click', () => {
    const template = document.querySelector('#question-template');
    holder.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
    updateQuizTotal();
  });
  holder.addEventListener('click', event => {
    if (!event.target.closest('.remove-question')) return;
    event.target.closest('.question-editor').remove();
    updateQuizTotal();
  });
  holder.addEventListener('input', updateQuizTotal);
  updateQuizTotal();
}

/** @returns {void} */
function setupVideoUpload() {
  const input = document.querySelector('#video-upload');
  if (!input) return;
  input.addEventListener('change', () => {
    const file = input.files[0];
    input.setCustomValidity('');
    if (!file) return;
    if (file.size > Number(input.dataset.maxBytes)) {
      input.setCustomValidity('La vidéo doit faire 1 Go maximum.');
      input.reportValidity();
      return;
    }
    const url = URL.createObjectURL(file);
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      document.querySelector('#video-duration').value = video.duration.toFixed(3);
      URL.revokeObjectURL(url);
    };
    video.onerror = () => { URL.revokeObjectURL(url); input.setCustomValidity('Ce fichier vidéo ne peut pas être lu.'); };
    input.setCustomValidity('');
    video.src = url;
  });
}

/** @returns {void} */
function setupSignature() {
  const canvas = document.querySelector('#signature');
  if (!canvas) return;
  const context = canvas.getContext('2d');
  context.lineWidth = 3; context.lineCap = 'round'; context.strokeStyle = '#293a34';
  /** @param {PointerEvent} event @returns {[number, number]} */
  const point = event => { const box = canvas.getBoundingClientRect(); return [(event.clientX - box.left) * canvas.width / box.width, (event.clientY - box.top) * canvas.height / box.height]; };
  let drawing = false;
  canvas.addEventListener('pointerdown', event => { drawing = true; canvas.setPointerCapture(event.pointerId); context.beginPath(); context.moveTo(...point(event)); });
  canvas.addEventListener('pointermove', event => { if (!drawing) return; context.lineTo(...point(event)); context.stroke(); document.querySelector('#signature-value').value = canvas.toDataURL('image/png'); });
  canvas.addEventListener('pointerup', () => { drawing = false; });
  canvas.addEventListener('pointercancel', () => { drawing = false; });
  document.querySelector('#clear-signature').addEventListener('click', () => { context.clearRect(0, 0, canvas.width, canvas.height); document.querySelector('#signature-value').value = ''; });
}

/** @param {HTMLVideoElement} video @param {HTMLElement} status @returns {Promise<void>} */
async function recordVideo(video, status) {
  const body = new URLSearchParams({ csrf: video.dataset.csrf, content_id: video.dataset.contentId, position: String(video.currentTime) });
  const response = await fetch('?view=video-tick', { method: 'POST', body, credentials: 'same-origin' });
  if (!response.ok) throw new Error('Le visionnage n’a pas pu être enregistré. Reprenez la lecture.');
  const result = await response.json();
  if (result.done) { document.querySelector('#video-next').disabled = false; status.textContent = 'Visionnage terminé.'; }
}

/** @returns {void} */
function setupWelcomeVideo() {
  const video = document.querySelector('#welcome-video');
  if (!video) return;
  let last = 0, busy = false;
  const status = document.querySelector('#video-status');
  /** @returns {Promise<void>} */
  const send = async () => {
    if (busy || document.hidden) return;
    busy = true;
    try {
      await recordVideo(video, status);
    } catch (error) { status.textContent = error instanceof Error ? error.message : 'La lecture doit être reprise.'; video.pause(); }
    finally { busy = false; }
  };
  video.addEventListener('play', () => { video.playbackRate = 1; void send(); });
  video.addEventListener('timeupdate', () => { if (video.seeking) return; if (video.currentTime < last + 2) last = Math.max(last, video.currentTime); });
  video.addEventListener('seeking', () => { if (video.currentTime > last + 1) video.currentTime = last; });
  video.addEventListener('ratechange', () => { if (video.playbackRate !== 1) video.playbackRate = 1; });
  video.addEventListener('ended', () => { void send(); });
  window.setInterval(() => { if (!video.paused && !video.ended) void send(); }, 1000);
}

setupQuizEditor();
setupVideoUpload();
setupSignature();
setupWelcomeVideo();
document.querySelector('#profile-selector')?.addEventListener('change', event => { event.target.form.requestSubmit(); });
