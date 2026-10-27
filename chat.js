/**
 * Chat de suporte — cliente e admin
 */
(function (global) {
  'use strict';

  function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text == null ? '' : String(text);
    return d.innerHTML;
  }

  function formatMultiline(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
  }

  function scrollToBottom(el) {
    if (el) el.scrollTop = el.scrollHeight;
  }

  function showToast(msg) {
    let t = document.getElementById('chatToast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'chatToast';
      t.className = 'chat-toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('is-visible');
    setTimeout(() => t.classList.remove('is-visible'), 4000);
  }

  function createChatUI(config) {
    const {
      role,
      threadId,
      messagesEl,
      composerForm,
      inputEl,
      sendBtn,
      pollInterval = 2500,
    } = config;

    let ultimaId = config.initialLastId || 0;
    let sending = false;
    let lastDateLabel = '';

    function isOutgoing(remetente) {
      if (role === 'user') return remetente === 'user';
      return remetente === 'admin';
    }

    function appendMessage(msg, animate) {
      if (!messagesEl || msg.id <= ultimaId) return;
      ultimaId = Math.max(ultimaId, msg.id);

      const wrap = document.createElement('div');
      const out = isOutgoing(msg.remetente);
      wrap.className = 'chat-bubble-wrap ' + (out ? 'chat-bubble-wrap--out' : 'chat-bubble-wrap--in');
      if (animate) wrap.style.animation = 'none';

      const dateLabel = msg.data || '';
      if (dateLabel && dateLabel !== lastDateLabel) {
        lastDateLabel = dateLabel;
        const sep = document.createElement('div');
        sep.className = 'chat-day-sep';
        sep.textContent = dateLabel;
        messagesEl.appendChild(sep);
      }

      wrap.innerHTML =
        '<span class="chat-bubble__sender">' + escapeHtml(msg.nome) + '</span>' +
        '<div class="chat-bubble">' + formatMultiline(msg.texto) + '</div>' +
        '<span class="chat-bubble__time">' + escapeHtml(msg.hora) + '</span>';
      messagesEl.appendChild(wrap);
    }

    function clearEmptyState() {
      const empty = messagesEl && messagesEl.querySelector('.chat-empty-state');
      if (empty) empty.remove();
    }

    function renderMessages(list, replace) {
      if (!messagesEl) return;
      if (replace) {
        messagesEl.innerHTML = '';
        ultimaId = 0;
        lastDateLabel = '';
      }
      if (!list || !list.length) {
        if (!messagesEl.children.length) {
          messagesEl.innerHTML =
            '<div class="chat-empty-state">' +
            '<i class="bi bi-chat-heart"></i>' +
            '<p>Ainda não há mensagens. Escreva abaixo para começar.</p></div>';
        }
        return;
      }
      clearEmptyState();
      list.forEach((m) => appendMessage(m, false));
      scrollToBottom(messagesEl);
    }

    function fetchMessages(sinceId) {
      const params = new URLSearchParams({
        action: 'messages',
        since_id: String(sinceId || 0),
      });
      if (role === 'admin' && threadId) {
        params.set('thread_id', String(threadId));
      }
      return fetch('chat_api.php?' + params.toString(), { credentials: 'same-origin' })
        .then((r) => r.json())
        .catch(() => ({ error: 'Erro de rede' }));
    }

    function poll() {
      if (role === 'admin' && !threadId) return;
      fetchMessages(ultimaId).then((data) => {
        if (data.error) return;
        if (data.messages && data.messages.length) {
          clearEmptyState();
          data.messages.forEach((m) => appendMessage(m, true));
          scrollToBottom(messagesEl);
        }
      });
    }

    function sendMessage() {
      if (sending || !inputEl) return;
      const text = inputEl.value.trim();
      if (!text) return;
      if (role === 'admin' && !threadId) {
        showToast('Selecione uma conversa primeiro.');
        return;
      }

      sending = true;
      if (sendBtn) sendBtn.disabled = true;

      const body = new URLSearchParams({
        action: 'send',
        mensagem: text,
      });
      if (role === 'admin') {
        body.set('para_usuario_id', String(threadId));
      }

      fetch('chat_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin',
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            inputEl.value = '';
            inputEl.style.height = 'auto';
            return fetchMessages(ultimaId);
          }
          showToast(data.error || 'Não foi possível enviar.');
          return null;
        })
        .then((data) => {
          if (data && data.messages) {
            clearEmptyState();
            data.messages.forEach((m) => appendMessage(m, true));
            scrollToBottom(messagesEl);
          }
        })
        .catch(() => showToast('Erro de ligação. Tente novamente.'))
        .finally(() => {
          sending = false;
          if (sendBtn) sendBtn.disabled = false;
          inputEl.focus();
        });
    }

    if (composerForm) {
      composerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage();
      });
    }
    if (inputEl) {
      inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
      inputEl.addEventListener('input', () => {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
      });
    }

    const pollTimer = setInterval(poll, pollInterval);
    window.addEventListener('beforeunload', () => clearInterval(pollTimer));

    return {
      renderMessages,
      poll,
      sendMessage,
      setThreadId(id) {
        config.threadId = id;
      },
      getUltimaId: () => ultimaId,
    };
  }

  global.ChatSupport = {
    escapeHtml,
    createChatUI,
    showToast,
    scrollToBottom,
  };
})(window);
