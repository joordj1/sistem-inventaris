(function () {
  "use strict";

  function setText(el, text) {
    if (el) {
      el.textContent = text;
    }
  }

  function hideImage(imgEl) {
    if (!imgEl) {
      return;
    }

    imgEl.classList.add("d-none");
    imgEl.removeAttribute("src");
  }

  function showImage(imgEl, dataUrl) {
    if (!imgEl) {
      return;
    }

    imgEl.src = dataUrl;
    imgEl.classList.remove("d-none");
  }

  function isImageFile(file) {
    if (!file) {
      return false;
    }

    if (
      typeof file.type === "string" &&
      file.type.toLowerCase().indexOf("image/") === 0
    ) {
      return true;
    }

    return /\.(jpe?g|png|webp|gif|bmp)$/i.test(file.name || "");
  }

  function bindFilePreview(inputEl) {
    var nameId = inputEl.getAttribute("data-preview-name-id");
    var imageId = inputEl.getAttribute("data-preview-image-id");

    if (!nameId || !imageId) {
      return;
    }

    var nameEl = document.getElementById(nameId);
    var imageEl = document.getElementById(imageId);

    inputEl.addEventListener("change", function () {
      var file =
        inputEl.files && inputEl.files.length > 0 ? inputEl.files[0] : null;

      if (!file) {
        setText(nameEl, "Belum ada file dipilih.");
        hideImage(imageEl);
        return;
      }

      setText(nameEl, "File dipilih: " + file.name);

      if (!isImageFile(file)) {
        hideImage(imageEl);
        return;
      }

      var reader = new FileReader();
      reader.onload = function (event) {
        if (event && event.target && typeof event.target.result === "string") {
          showImage(imageEl, event.target.result);
        }
      };
      reader.onerror = function () {
        hideImage(imageEl);
      };
      reader.readAsDataURL(file);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var inputs = document.querySelectorAll("[data-file-preview-input]");
    inputs.forEach(bindFilePreview);
  });
})();
