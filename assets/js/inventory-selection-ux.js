(function () {
  function getInteractiveTarget(target) {
    return target.closest("input, select, textarea, button, a, label");
  }

  function getPanelMode(panel) {
    return panel.getAttribute("data-selection-mode") || "checkbox";
  }

  function getVisibleRows(panel) {
    return Array.from(panel.querySelectorAll("[data-selection-row]")).filter(
      function (row) {
        return !row.classList.contains("d-none");
      },
    );
  }

  function updatePanel(panel) {
    var searchField = panel.querySelector("[data-selection-search]");
    var categoryField = panel.querySelector("[data-selection-category]");
    var locationField = panel.querySelector("[data-selection-location]");
    var statusField = panel.querySelector("[data-selection-status]");
    var counter = panel.querySelector("[data-selection-counter]");
    if (!counter) {
      var card = panel.closest(".card");
      counter = card ? card.querySelector("[data-selection-counter]") : null;
    }
    var emptyRow = panel.querySelector("[data-selection-empty-row]");
    var mode = getPanelMode(panel);

    var searchValue = ((searchField && searchField.value) || "")
      .trim()
      .toLowerCase();
    var categoryValue = ((categoryField && categoryField.value) || "")
      .trim()
      .toLowerCase();
    var locationValue = ((locationField && locationField.value) || "")
      .trim()
      .toLowerCase();
    var statusValue = ((statusField && statusField.value) || "")
      .trim()
      .toLowerCase();

    var visibleCount = 0;
    var selectedCount = 0;

    Array.from(panel.querySelectorAll("[data-selection-row]")).forEach(
      function (row) {
        var searchText = (
          row.getAttribute("data-search-text") || ""
        ).toLowerCase();
        var rowCategory = (
          row.getAttribute("data-category") || ""
        ).toLowerCase();
        var rowLocation = (
          row.getAttribute("data-location") || ""
        ).toLowerCase();
        var rowStatus = (row.getAttribute("data-status") || "").toLowerCase();

        var matches =
          (!searchValue || searchText.indexOf(searchValue) !== -1) &&
          (!categoryValue || rowCategory === categoryValue) &&
          (!locationValue || rowLocation === locationValue) &&
          (!statusValue || rowStatus === statusValue);

        row.classList.toggle("d-none", !matches);
        if (!matches) {
          return;
        }

        visibleCount += 1;
        if (mode === "qty") {
          var qtyInput = row.querySelector("[data-selection-qty]");
          if (qtyInput && parseInt(qtyInput.value || "0", 10) > 0) {
            selectedCount += 1;
          }
        } else {
          var checkbox = row.querySelector("[data-selection-checkbox]");
          if (checkbox && checkbox.checked) {
            selectedCount += 1;
          }
        }
      },
    );

    if (counter) {
      counter.textContent =
        mode === "qty"
          ? selectedCount + " item aktif dari " + visibleCount + " item"
          : selectedCount + " unit dipilih dari " + visibleCount + " unit";
    }

    if (emptyRow) {
      emptyRow.classList.toggle("d-none", visibleCount > 0);
    }
  }

  function selectAllVisible(panel) {
    var mode = getPanelMode(panel);
    getVisibleRows(panel).forEach(function (row) {
      if (mode === "qty") {
        var qtyInput = row.querySelector("[data-selection-qty]");
        if (qtyInput) {
          var maxValue = parseInt(qtyInput.getAttribute("max") || "0", 10);
          qtyInput.value = maxValue > 0 ? maxValue : 1;
          qtyInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
      } else {
        var checkbox = row.querySelector("[data-selection-checkbox]");
        if (checkbox) {
          checkbox.checked = true;
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    });

    updatePanel(panel);
  }

  function resetVisible(panel) {
    var mode = getPanelMode(panel);
    getVisibleRows(panel).forEach(function (row) {
      if (mode === "qty") {
        var qtyInput = row.querySelector("[data-selection-qty]");
        if (qtyInput) {
          qtyInput.value = 0;
          qtyInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
      } else {
        var checkbox = row.querySelector("[data-selection-checkbox]");
        if (checkbox) {
          checkbox.checked = false;
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    });

    updatePanel(panel);
  }

  function wirePanel(panel) {
    Array.from(
      panel.querySelectorAll(
        "[data-selection-search], [data-selection-category], [data-selection-location], [data-selection-status]",
      ),
    ).forEach(function (element) {
      element.addEventListener("input", function () {
        updatePanel(panel);
      });
      element.addEventListener("change", function () {
        updatePanel(panel);
      });
    });

    Array.from(
      panel.querySelectorAll("[data-selection-qty], [data-selection-checkbox]"),
    ).forEach(function (element) {
      element.addEventListener("input", function () {
        updatePanel(panel);
      });
      element.addEventListener("change", function () {
        updatePanel(panel);
      });
    });

    var selectAllButton = panel.querySelector("[data-selection-select-all]");
    if (selectAllButton) {
      selectAllButton.addEventListener("click", function () {
        selectAllVisible(panel);
      });
    }

    var resetButton = panel.querySelector("[data-selection-reset]");
    if (resetButton) {
      resetButton.addEventListener("click", function () {
        resetVisible(panel);
      });
    }

    if (getPanelMode(panel) === "checkbox") {
      Array.from(panel.querySelectorAll("[data-selection-row]")).forEach(
        function (row) {
          row.addEventListener("click", function (event) {
            if (getInteractiveTarget(event.target)) {
              return;
            }

            var checkbox = row.querySelector("[data-selection-checkbox]");
            if (
              !checkbox ||
              checkbox.disabled ||
              row.classList.contains("d-none")
            ) {
              return;
            }

            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event("change", { bubbles: true }));
          });
        },
      );
    }

    updatePanel(panel);
  }

  document.addEventListener("DOMContentLoaded", function () {
    Array.from(document.querySelectorAll("[data-selection-panel]")).forEach(
      function (panel) {
        wirePanel(panel);
      },
    );
  });
})();
