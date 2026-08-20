(function ($) {
  "use strict";

  function isRtl(el) {
    return getComputedStyle(el).direction === "rtl";
  }

  function slides(track) {
    return Array.prototype.slice.call(track.querySelectorAll(".mha-news-slide"));
  }

  function stepSize(track) {
    var card = track.querySelector(".mha-news-slide");
    if (!card) {
      return Math.max(240, track.clientWidth * 0.8);
    }
    var styles = getComputedStyle(track);
    var gap = parseFloat(styles.columnGap || styles.gap || "16") || 16;
    return card.getBoundingClientRect().width + gap;
  }

  function currentIndex(track) {
    var items = slides(track);
    if (!items.length) {
      return 0;
    }
    var rect = track.getBoundingClientRect();
    var rtl = isRtl(track);
    var best = 0;
    var bestDist = Infinity;
    items.forEach(function (card, i) {
      var r = card.getBoundingClientRect();
      var dist = rtl
        ? Math.abs(r.right - rect.right)
        : Math.abs(r.left - rect.left);
      if (dist < bestDist) {
        bestDist = dist;
        best = i;
      }
    });
    return best;
  }

  function scrollToIndex(track, index) {
    var items = slides(track);
    if (!items.length) {
      return;
    }
    index = Math.max(0, Math.min(items.length - 1, index));
    var card = items[index];
    var trackRect = track.getBoundingClientRect();
    var cardRect = card.getBoundingClientRect();
    var delta = isRtl(track)
      ? cardRect.right - trackRect.right
      : cardRect.left - trackRect.left;
    track.scrollBy({ left: delta, behavior: "smooth" });
  }

  function go(track, dir) {
    var items = slides(track);
    if (!items.length) {
      return;
    }
    var delta = dir === "next" ? 1 : -1;
    scrollToIndex(track, currentIndex(track) + delta);
  }

  function updateButtons(root) {
    var track = root.querySelector(".mha-news-track");
    var prev = root.querySelector(".mha-news-prev");
    var next = root.querySelector(".mha-news-next");
    if (!track || !prev || !next) {
      return;
    }
    var items = slides(track);
    if (items.length <= 1) {
      prev.disabled = true;
      next.disabled = true;
      return;
    }
    var i = currentIndex(track);
    var max = items.length - 1;
    var slop = 12;
    var atStart = i <= 0;
    var atEnd = i >= max;
    if (track.scrollWidth - track.clientWidth <= slop) {
      atStart = true;
      atEnd = true;
    }
    prev.disabled = atStart;
    next.disabled = atEnd;
  }

  function bind(root) {
    var track = root.querySelector(".mha-news-track");
    if (!track || track.dataset.mhaBound === "1") {
      return;
    }
    track.dataset.mhaBound = "1";

    root.querySelectorAll("[data-news-dir]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        go(track, btn.getAttribute("data-news-dir") === "prev" ? "prev" : "next");
      });
    });

    track.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        go(track, isRtl(track) ? "next" : "prev");
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        go(track, isRtl(track) ? "prev" : "next");
      } else if (e.key === "Home") {
        e.preventDefault();
        scrollToIndex(track, 0);
      } else if (e.key === "End") {
        e.preventDefault();
        scrollToIndex(track, slides(track).length - 1);
      }
    });

    var wheelLock = 0;
    track.addEventListener(
      "wheel",
      function (e) {
        if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
          return;
        }
        if (track.scrollWidth <= track.clientWidth + 4) {
          return;
        }
        e.preventDefault();
        var now = Date.now();
        if (now - wheelLock < 320) {
          return;
        }
        wheelLock = now;
        go(track, e.deltaX > 0 ? (isRtl(track) ? "prev" : "next") : isRtl(track) ? "next" : "prev");
      },
      { passive: false }
    );

    track.addEventListener("scroll", function () {
      window.requestAnimationFrame(function () {
        updateButtons(root);
      });
    });

    window.addEventListener("resize", function () {
      updateButtons(root);
    });

    updateButtons(root);
  }

  function init() {
    document.querySelectorAll(".mha-news").forEach(bind);
  }

  $(init);
})(jQuery);
