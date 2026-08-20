(function () {
  "use strict";

  var cfg = window.mhaChat || {};
  var storageKey = "mha_chat_session";
  var root;
  var panel;
  var logEl;
  var form;
  var input;
  var toastEl;
  var agentEl;
  var toggleBtn;
  var fileInput;
  var attachPreview;
  var attachThumb;
  var attachName;
  var micBtn;
  var lastFocus = null;
  var sending = false;
  var pendingImage = null;
  var recognition = null;
  var listening = false;
  var canSpeak = typeof window.speechSynthesis !== "undefined";
  var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
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

  function safeDataUrl(url) {
    return /^data:image\/(jpeg|jpg|png|webp|gif);base64,/i.test(String(url || ""));
  }

  function speakText(text) {
    if (!canSpeak) {
      return;
    }
    try {
      window.speechSynthesis.cancel();
      var u = new window.SpeechSynthesisUtterance(String(text || ""));
      u.lang = "ar-EG";
      window.speechSynthesis.speak(u);
    } catch (e) {
      /* ignore */
    }
  }

  function addMessage(role, text, links, imageUrl) {
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
    if (imageUrl && safeDataUrl(imageUrl)) {
      var img = el("img", "mha-chat-preview");
      img.src = imageUrl;
      img.alt = "صورة مرفقة";
      bubble.appendChild(img);
    }
    var p = el("p");
    fillText(p, text);
    bubble.appendChild(p);

    if (role === "bot" && canSpeak && text) {
      var speakBtn = el("button", "mha-chat-speak");
      speakBtn.type = "button";
      speakBtn.setAttribute("aria-label", "استماع");
      speakBtn.appendChild(speakerIcon());
      speakBtn.addEventListener("click", function () {
        speakText(text);
      });
      bubble.appendChild(speakBtn);
    }

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

  function speakerIcon() {
    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("aria-hidden", "true");
    var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute("fill", "currentColor");
    path.setAttribute(
      "d",
      "M3 10v4h4l5 5V5L7 10H3zm13.5 2a4.5 4.5 0 00-2.5-4v8a4.5 4.5 0 002.5-4z"
    );
    svg.appendChild(path);
    return svg;
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
    stopMic();
    if (canSpeak) {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {
        /* ignore */
      }
    }
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
    clearAttach();
    if (agentEl) {
      agentEl.textContent = "مرشد الموقع";
    }
    setWelcome();
  }

  function clearAttach() {
    pendingImage = null;
    if (fileInput) {
      fileInput.value = "";
    }
    if (attachPreview) {
      attachPreview.hidden = true;
      attachPreview.classList.remove("is-on");
    }
    if (attachThumb) {
      attachThumb.removeAttribute("src");
    }
    if (attachName) {
      attachName.textContent = "";
    }
  }

  function send(text) {
    var message = String(text || "").trim();
    var imageUrl = pendingImage ? pendingImage.dataUrl : "";
    if (!message && imageUrl) {
      message = "أرفق صورة";
    }
    if (!message || sending) {
      return;
    }
    var maxLen = cfg.maxLen || 1000;
    if (message.length > maxLen) {
      message = message.slice(0, maxLen);
    }
    sending = true;
    addMessage("user", message, [], imageUrl);
    clearAttach();
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
        sending = false;
      });
  }

  function onFile(file) {
    if (!file) {
      return;
    }
    var max = cfg.maxImage || 512000;
    if (file.size > max) {
      showToast("الصورة أكبر من الحد المسموح (500 كيلوبايت).");
      return;
    }
    if (!/^image\/(jpeg|jpg|png|webp|gif)$/i.test(file.type || "")) {
      showToast("يُسمح بصور JPEG أو PNG أو WebP أو GIF فقط.");
      return;
    }
    var reader = new FileReader();
    reader.onload = function () {
      var url = String(reader.result || "");
      if (!safeDataUrl(url)) {
        showToast("تعذر قراءة الصورة.");
        return;
      }
      pendingImage = { dataUrl: url, name: file.name || "صورة" };
      if (attachThumb) {
        attachThumb.src = url;
      }
      if (attachName) {
        attachName.textContent = pendingImage.name;
      }
      if (attachPreview) {
        attachPreview.hidden = false;
        attachPreview.classList.add("is-on");
      }
    };
    reader.onerror = function () {
      showToast("تعذر قراءة الصورة.");
    };
    reader.readAsDataURL(file);
  }

  function stopMic() {
    listening = false;
    if (recognition) {
      try {
        recognition.stop();
      } catch (e) {
        /* ignore */
      }
    }
    if (micBtn) {
      micBtn.classList.remove("is-on");
      micBtn.setAttribute("aria-pressed", "false");
    }
  }

  function toggleMic() {
    if (!SpeechRec || !recognition) {
      return;
    }
    if (listening) {
      stopMic();
      return;
    }
    try {
      recognition.lang = "ar-EG";
      recognition.start();
      listening = true;
      if (micBtn) {
        micBtn.classList.add("is-on");
        micBtn.setAttribute("aria-pressed", "true");
      }
    } catch (e) {
      try {
        recognition.lang = "en-US";
        recognition.start();
        listening = true;
      } catch (err) {
        showToast("تعذر تشغيل الميكروفون في هذا المتصفح.");
        stopMic();
      }
    }
  }

  function bind() {
    root = qs("#mhaChatRoot");
    panel = qs("#mhaChatPanel");
    logEl = qs("#mhaChatLog");
    form = qs("#mhaChatForm");
    input = qs("#mhaChatInput");
    toastEl = qs("#mhaChatToast");
    agentEl = qs("#mhaChatAgent");
    toggleBtn = qs("#mhaChatToggle");
    fileInput = qs("#mhaChatFile");
    attachPreview = qs("#mhaChatAttachPreview");
    attachThumb = qs("#mhaChatAttachThumb");
    attachName = qs("#mhaChatAttachName");
    micBtn = qs("#mhaChatMic");
    var closeBtn = qs("#mhaChatClose");
    var refreshBtn = qs("#mhaChatRefresh");
    var imageBtn = qs("#mhaChatImage");
    var attachClear = qs("#mhaChatAttachClear");

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

    if (imageBtn && fileInput) {
      imageBtn.addEventListener("click", function () {
        fileInput.click();
      });
      fileInput.addEventListener("change", function () {
        onFile(fileInput.files && fileInput.files[0]);
      });
    }
    if (attachClear) {
      attachClear.addEventListener("click", clearAttach);
    }

    if (micBtn) {
      if (!SpeechRec) {
        micBtn.disabled = true;
        micBtn.title = "الإدخال الصوتي غير مدعوم في هذا المتصفح";
      } else {
        try {
          recognition = new SpeechRec();
          recognition.lang = "ar-EG";
          recognition.interimResults = false;
          recognition.maxAlternatives = 1;
          recognition.onresult = function (event) {
            var text = "";
            if (event.results && event.results[0] && event.results[0][0]) {
              text = event.results[0][0].transcript || "";
            }
            if (input && text) {
              input.value = (input.value ? input.value + " " : "") + text;
            }
            stopMic();
          };
          recognition.onerror = function () {
            stopMic();
          };
          recognition.onend = function () {
            listening = false;
            if (micBtn) {
              micBtn.classList.remove("is-on");
              micBtn.setAttribute("aria-pressed", "false");
            }
          };
          micBtn.addEventListener("click", toggleMic);
        } catch (e) {
          micBtn.disabled = true;
          micBtn.title = "الإدخال الصوتي غير مدعوم في هذا المتصفح";
        }
      }
    }

    document.querySelectorAll("#mhaChatChips [data-chip]").forEach(function (btn) {
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
