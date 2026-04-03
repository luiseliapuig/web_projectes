(function () {
  /* ========= Preloader ======== */
  const preloader = document.querySelectorAll('#preloader')

  window.addEventListener('load', function () {
    if (preloader.length) {
      this.document.getElementById('preloader').style.display = 'none'
    }
  })



 /* ========= sidebar toggle ======== */
const sidebarNavWrapper = document.querySelector(".sidebar-nav-wrapper");
const mainWrapper = document.querySelector(".main-wrapper");
const menuToggleButton = document.querySelector("#menu-toggle");
const menuToggleButtonIcon = document.querySelector("#menu-toggle i");
const overlay = document.querySelector(".overlay");

if (menuToggleButton) {
  menuToggleButton.addEventListener("click", () => {
    if (sidebarNavWrapper) sidebarNavWrapper.classList.toggle("active");
    if (overlay) overlay.classList.add("active");
    if (mainWrapper) mainWrapper.classList.toggle("active");

    if (!menuToggleButtonIcon) return;

    if (document.body.clientWidth > 1200) {
      if (menuToggleButtonIcon.classList.contains("lni-chevron-left")) {
        menuToggleButtonIcon.classList.remove("lni-chevron-left");
        menuToggleButtonIcon.classList.add("lni-menu");
      } else {
        menuToggleButtonIcon.classList.remove("lni-menu");
        menuToggleButtonIcon.classList.add("lni-chevron-left");
      }
    } else {
      if (menuToggleButtonIcon.classList.contains("lni-chevron-left")) {
        menuToggleButtonIcon.classList.remove("lni-chevron-left");
        menuToggleButtonIcon.classList.add("lni-menu");
      }
    }
  });
}

if (overlay) {
  overlay.addEventListener("click", () => {
    if (sidebarNavWrapper) sidebarNavWrapper.classList.remove("active");
    overlay.classList.remove("active");
    if (mainWrapper) mainWrapper.classList.remove("active");
  });
}
})();
