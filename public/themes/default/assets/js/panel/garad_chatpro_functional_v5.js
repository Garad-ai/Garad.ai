(() => {
  'use strict';
  if (window.__GARAD_CHATPRO_FUNCTIONAL_V5__) return;
  window.__GARAD_CHATPRO_FUNCTIONAL_V5__ = true;

  const boot = () => {
    document.documentElement.dataset.garadChatPro = 'functional-v5';
    const form = document.querySelector('#chat_form');
    if (form) form.dataset.garadFunctionalV5 = '1';

    const prompt = document.querySelector('#prompt');
    if (prompt && !prompt.dataset.garadAutosizeV5) {
      prompt.dataset.garadAutosizeV5 = '1';
      const resize = () => {
        prompt.style.height = 'auto';
        prompt.style.height = `${Math.min(Math.max(prompt.scrollHeight, 58), 200)}px`;
      };
      prompt.addEventListener('input', resize, { passive: true });
      resize();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
