(function ($) {
  "use strict";

  function animateCounters() {
    $(".mha-count").each(function () {
      var $el = $(this);
      if ($el.data("done")) {
        return;
      }
      var target = parseInt($el.data("count"), 10) || 0;
      $({ n: 0 }).animate(
        { n: target },
        {
          duration: 1200,
          easing: "swing",
          step: function (now) {
            $el.text(Math.floor(now));
          },
          complete: function () {
            $el.text(target);
            $el.data("done", true);
          },
        }
      );
    });
  }

  function countersInView() {
    var $stats = $(".mha-stats");
    if (!$stats.length) {
      return;
    }
    var top = $stats.offset().top;
    if ($(window).scrollTop() + $(window).height() > top + 40) {
      animateCounters();
    }
  }

  $(window).on("scroll", function () {
    $("#mhaTop").toggleClass("is-on", $(window).scrollTop() > 400);
    countersInView();
  });

  $("#mhaTop").on("click", function () {
    $("html, body").animate({ scrollTop: 0 }, 400);
  });

  countersInView();

  $(".navbar-collapse").on("click", ".nav-link", function () {
    if (window.matchMedia("(max-width: 1199.98px)").matches) {
      $(".navbar-collapse").collapse("hide");
    }
  });
})(jQuery);
