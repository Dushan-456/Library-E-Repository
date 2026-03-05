document.addEventListener("DOMContentLoaded", () => {
  const fileGrid = document.getElementById("fileGrid");
  const currentFolderName = document.getElementById("currentFolderName");
  const breadcrumb = document.getElementById("breadcrumb");
  const searchInput = document.getElementById("searchInput");

  let currentPath = "";
  let currentViewMode = "grid"; // default

  // Load folder contents
  async function loadFolder(path = "") {
    currentPath = path;
    fileGrid.innerHTML =
      '<div class="loader"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    try {
      const response = await fetch(
        `index.php?action=get_folder&folder=${encodeURIComponent(path)}`,
      );
      const data = await response.json();

      if (data.error) {
        fileGrid.innerHTML = `<div class="loader">${data.error}</div>`;
        return;
      }

      renderFiles(data);
      updateBreadcrumb(path);
    } catch (error) {
      fileGrid.innerHTML = `<div class="loader">Error loading resources</div>`;
    }
  }

  function renderFiles(items) {
    fileGrid.innerHTML = "";

    if (items.length === 0) {
      fileGrid.innerHTML = '<div class="loader">No items found</div>';
      return;
    }

    items.forEach((item) => {
      const div = document.createElement("div");
      div.className = "file-item";
      div.innerHTML = `
                <div class="file-icon">
                    <i class="fas ${item.isDir ? "fa-folder icon-folder" : "fa-file-pdf icon-pdf"}"></i>
                </div>
                <div class="file-name" title="${item.name}">${item.name}</div>
            `;

      div.onclick = () => {
        if (item.isDir) {
          loadFolder(item.path);
        } else {
          openViewer(item.path, item.name);
        }
      };
      fileGrid.appendChild(div);
    });
  }

  function updateBreadcrumb(path) {
    const parts = path.split("/").filter((p) => p);
    breadcrumb.innerHTML = "<span onclick=\"loadFolder('')\">Library</span>";

    let cumulativePath = "";
    parts.forEach((part, index) => {
      cumulativePath += (index === 0 ? "" : "/") + part;
      const span = document.createElement("span");
      span.textContent = part;
      const currentPartPath = cumulativePath;
      span.onclick = () => loadFolder(currentPartPath);
      breadcrumb.appendChild(span);
    });

    currentFolderName.textContent =
      parts.length > 0 ? parts[parts.length - 1] : "All Resources";
  }

  function openViewer(path, name) {
    // Open viewer.php in a new tab
    const viewerUrl = `viewer.php?file=${encodeURIComponent(path)}`;
    window.open(viewerUrl, "_blank");
  }

  // Sidebar navigation
  document.querySelectorAll(".sidebar-nav li").forEach((li) => {
    li.onclick = () => {
      document
        .querySelectorAll(".sidebar-nav li")
        .forEach((l) => l.classList.remove("active"));
      li.classList.add("active");
      loadFolder(li.dataset.folder);
    };
  });

  // Search functionality
  let searchTimeout;
  searchInput.oninput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
      const query = searchInput.value.trim();
      if (query.length < 2) {
        if (query.length === 0) loadFolder(currentPath);
        return;
      }

      fileGrid.innerHTML = '<div class="loader">Searching...</div>';
      const response = await fetch(
        `index.php?action=search&query=${encodeURIComponent(query)}`,
      );
      const data = await response.json();
      renderFiles(data);
    }, 500);
  };

  // View Switching Logic
  const gridViewBtn = document.getElementById("gridViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");

  function setViewMode(mode) {
    currentViewMode = mode;
    fileGrid.setAttribute("data-view", mode);
    if (mode === "list") {
      listViewBtn.classList.add("active");
      gridViewBtn.classList.remove("active");
    } else {
      gridViewBtn.classList.add("active");
      listViewBtn.classList.remove("active");
    }
  }

  // Set initial view mode
  setViewMode(currentViewMode);

  gridViewBtn.onclick = () => setViewMode("grid");
  listViewBtn.onclick = () => setViewMode("list");

  // Initial load
  loadFolder("");
});
