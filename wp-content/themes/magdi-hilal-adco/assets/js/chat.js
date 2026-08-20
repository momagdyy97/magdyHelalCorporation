(function () {
  "use strict";

  var cfg = window.mhaChat || {};
  var storageKey = "mha_chat_session";
  var panel;
  var logEl;
  var form;
  var input;
  var sendBtn;
  var toastEl;
  var agentEl;
  var toggleBtn;
  var lastFocus = null;
  var sending = false;

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function chipButtons() {
    return document.querySelectorAll("#mhaChatChips [data-chip]");
  }

  function sessionToken() {
    try {
      return window.sessionStorage.getItem(storageKey) || "";
    } catch (e) {
      return "";
    }
  }

  function saveSession(token) {
    if (!token) {
      return;
    }
    try {
      window.sessionStorage.setItem(storageKey, token);
    } catch (e) {
      /* ignore */
    }
  }

  function clearSession() {
    try {
      window.sessionStorage.removeItem(storageKey);
    } catch (e) {
      /* ignore */
    }
  }

  function showToast(text) {
    if (!toastEl) {
      return;
    }
    toastEl.hidden = false;
    toastEl.textContent = text;
    window.setTimeout(function () {
      toastEl.hidden = true;
      toastEl.textContent = "";
    }, 4200);
  }

  function el(tag, className) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    return node;
  }

  function fillText(node, text) {
    var parts = String(text || "").split("\n");
    parts.forEach(function (line, i) {
      if (i) {
        node.appendChild(document.createElement("br"));
      }
      node.appendChild(document.createTextNode(line));
    });
  }

  function clearLog() {
    if (!logEl) {
      return;
    }
    while (logEl.firstChild) {
      logEl.removeChild(logEl.firstChild);
    }
  }

  function sameOrigin(url) {
    try {
      var u = new URL(url, window.location.origin);
      return u.origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function addMessage(role, text, links) {
    if (!logEl) {
      return;
    }
    var row = el("div", "mha-chat-row mha-chat-row-" + role);
    if (role === "bot") {
      var meta = el("div", "mha-chat-bot-meta");
      var av = el("img");
      av.src = cfg.avatar || "";
      av.alt = "";
      av.width = 18;
      av.height = 18;
      var name = el("span");
      name.textContent = cfg.name || "مستشار M.H CORP";
      meta.appendChild(av);
      meta.appendChild(name);
      row.appendChild(meta);
    }

    var bubble = el("div", "mha-chat-bubble");
    var p = el("p");
    fillText(p, text);
    bubble.appendChild(p);

    if (links && links.length) {
      var list = el("ul", "mha-chat-links");
      links.forEach(function (item) {
        if (!item || !item.url || !item.title || !sameOrigin(item.url)) {
          return;
        }
        var li = el("li");
        var a = el("a");
        a.href = item.url;
        a.textContent = item.title;
        li.appendChild(a);
        list.appendChild(li);
      });
      if (list.childNodes.length) {
        bubble.appendChild(list);
      }
    }

    row.appendChild(bubble);
    logEl.appendChild(row);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function setWelcome() {
    addMessage(
      "bot",
      cfg.welcome ||
        "أهلاً بكم في مستشار مكتب مجدي هلال — M.H CORP. يمكن السؤال عن الضرائب، المراجعة، الفاتورة الإلكترونية، أو خدمات المكتب."
    );
  }

  function setTyping(on) {
    var existing = qs(".mha-chat-typing", logEl);
    if (existing) {
      existing.remove();
    }
    if (!on) {
      return;
    }
    var row = el("div", "mha-chat-row mha-chat-row-bot mha-chat-typing");
    row.setAttribute("aria-label", "جاري الكتابة");
    var meta = el("div", "mha-chat-bot-meta");
    var av = el("img");
    av.src = cfg.avatar || "";
    av.alt = "";
    av.width = 18;
    av.height = 18;
    var name = el("span");
    name.textContent = cfg.name || "مستشار M.H CORP";
    meta.appendChild(av);
    meta.appendChild(name);
    var bubble = el("div", "mha-chat-bubble");
    var dots = el("span", "mha-chat-dots");
    dots.appendChild(el("i"));
    dots.appendChild(el("i"));
    dots.appendChild(el("i"));
    bubble.appendChild(dots);
    row.appendChild(meta);
    row.appendChild(bubble);
    logEl.appendChild(row);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function setBusy(on) {
    sending = !!on;
    if (sendBtn) {
      sendBtn.disabled = sending;
    }
    if (form) {
      form.setAttribute("aria-busy", sending ? "true" : "false");
    }
    chipButtons().forEach(function (btn) {
      btn.disabled = sending;
    });
  }

  function isOpen() {
    return panel && panel.classList.contains("is-open");
  }

  function openPanel() {
    if (!panel) {
      return;
    }
    lastFocus = document.activeElement;
    panel.classList.add("is-open");
    panel.setAttribute("aria-hidden", "false");
    document.body.classList.add("mha-chat-open");
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-expanded", "true");
    }
    window.setTimeout(function () {
      if (input) {
        input.focus();
      }
    }, 40);
  }

  function closePanel() {
    if (!panel) {
      return;
    }
    panel.classList.remove("is-open");
    panel.setAttribute("aria-hidden", "true");
    document.body.classList.remove("mha-chat-open");
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-expanded", "false");
      toggleBtn.focus();
    } else if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }

  function togglePanel() {
    if (isOpen()) {
      closePanel();
    } else {
      openPanel();
    }
  }

  function resetChat() {
    clearSession();
    clearLog();
    setBusy(false);
    if (agentEl) {
      agentEl.textContent = "مرشد الموقع";
    }
    setWelcome();
  }

  function send(text) {
    var message = String(text || "").trim();
    if (!message || sending) {
      return;
    }
    var maxLen = cfg.maxLen || 1000;
    if (message.length > maxLen) {
      message = message.slice(0, maxLen);
    }
    setBusy(true);
    addMessage("user", message);
    setTyping(true);
    if (input) {
      input.value = "";
    }

    var headers = {
      "Content-Type": "application/json",
      Accept: "application/json",
    };
    if (cfg.nonce) {
      headers["X-WP-Nonce"] = cfg.nonce;
    }

    window
      .fetch(cfg.rest, {
        method: "POST",
        credentials: "same-origin",
        headers: headers,
        body: JSON.stringify({
          session: sessionToken(),
          message: message,
        }),
      })
      .then(function (res) {
        return res.json().then(function (body) {
          return { ok: res.ok, status: res.status, body: body };
        });
      })
      .then(function (result) {
        setTyping(false);
        if (!result.ok) {
          var err =
            (result.body && (result.body.message || (result.body.data && result.body.data.message))) ||
            "تعذر إرسال الرسالة. حاولوا مرة أخرى.";
          showToast(err);
          addMessage("bot", "تعذر إكمال الرد الآن. يمكن التواصل هاتفياً أو من صفحة تواصل معنا.");
          return;
        }
        var data = result.body || {};
        saveSession(data.session);
        if (data.agent_label && agentEl) {
          agentEl.textContent = data.agent_label;
        }
        addMessage("bot", data.reply || "", data.links || []);
      })
      .catch(function () {
        setTyping(false);
        showToast("انقطع الاتصال. تحققوا من الشبكة ثم أعيدوا المحاولة.");
      })
      .then(function () {
        setBusy(false);
        if (input && isOpen()) {
          input.focus();
        }
      });
  }

  function bind() {
    panel = qs("#mhaChatPanel");
    logEl = qs("#mhaChatLog");
    form = qs("#mhaChatForm");
    input = qs("#mhaChatInput");
    sendBtn = qs("#mhaChatSend");
    toastEl = qs("#mhaChatToast");
    agentEl = qs("#mhaChatAgent");
    toggleBtn = qs("#mhaChatToggle");
    var closeBtn = qs("#mhaChatClose");
    var refreshBtn = qs("#mhaChatRefresh");

    if (!panel || !cfg.rest) {
      return;
    }

    setWelcome();

    if (toggleBtn) {
      toggleBtn.addEventListener("click", togglePanel);
    }
    if (closeBtn) {
      closeBtn.addEventListener("click", closePanel);
    }
    if (refreshBtn) {
      refreshBtn.addEventListener("click", resetChat);
    }

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && isOpen()) {
        closePanel();
      }
    });

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        send(input ? input.value : "");
      });
    }

    chipButtons().forEach(function (btn) {
      btn.addEventListener("click", function () {
        var chip = btn.getAttribute("data-chip") || "";
        if (!isOpen()) {
          openPanel();
        }
        send(chip);
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bind);
  } else {
    bind();
  }
})();
