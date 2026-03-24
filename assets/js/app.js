document.addEventListener("DOMContentLoaded", () => {
  const fileGrid = document.getElementById("fileGrid");
  const currentFolderName = document.getElementById("currentFolderName");
  const breadcrumb = document.getElementById("breadcrumb");
  const searchInput = document.getElementById("searchInput");
  const backBtn = document.getElementById("backBtn");

  let currentPath = "";
  let currentViewMode = "grid"; // default
  let pathHistory = [];

  // Handle Back Button
  if (backBtn) {
      backBtn.onclick = () => {
          if (pathHistory.length > 1) {
              pathHistory.pop(); // Remove current path
              const previousPath = pathHistory.pop(); // Get previous path
              loadFolder(previousPath);
          }
      };
  }

  // Load folder contents
  async function loadFolder(path = "") {
    currentPath = path;
    
    // Add to history if it's a new path
    if (pathHistory.length === 0 || pathHistory[pathHistory.length - 1] !== path) {
        pathHistory.push(path);
    }
    
    // Toggle back button visibility
    if (backBtn) {
        backBtn.style.display = pathHistory.length > 1 ? 'flex' : 'none';
    }

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

  function renderFiles(items, isSearch = false) {
    fileGrid.innerHTML = "";

    // If path is empty (Home) AND we are NOT searching, prepend the Welcome Screen content
    if (currentPath === "" && !isSearch) {
        const welcomeWrapper = document.createElement("div");
        welcomeWrapper.innerHTML = getWelcomeHTML();
        welcomeWrapper.className = "welcome-wrapper";
        // Ensure controls are visible for the items below
        document.querySelector('.view-controls').style.display = 'block';
        fileGrid.appendChild(welcomeWrapper);
    }

    if (items.length === 0 && (currentPath !== "" || isSearch)) {
      const isEmptyDir = !isSearch;
      const noResults = document.createElement("div");
      noResults.style.gridColumn = "1 / -1";
      noResults.style.textAlign = "center";
      noResults.style.padding = "4rem 2rem";
      noResults.innerHTML = `
            <i class="fas ${isEmptyDir ? 'fa-folder-open' : 'fa-search'}" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
            <h3 style="color: var(--text-main); font-size: 1.25rem;">${isEmptyDir ? 'This folder is empty' : 'No results found'}</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">${isEmptyDir ? 'There are no files or subfolders here yet.' : 'Try adjusting your search keywords or browsing the collections.'}</p>
      `;
      fileGrid.appendChild(noResults);
      return;
    }

    // Render the actual files/folders below the welcome hero
    const itemsContainer = document.createElement("div");
    itemsContainer.className = `items-container view-${currentViewMode}`;
    
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
      itemsContainer.appendChild(div);
    });
    
    fileGrid.appendChild(itemsContainer);
  }

  function getWelcomeHTML() {
      // Current timestamp to break cache for images
      const t = new Date().getTime();
      return `
        <div class="welcome-container" style="margin-bottom: 2rem;">
            <div class="welcome-hero">
                <h2>Welcome to PGIM Digital Library</h2>
                <p>Your centralized hub for academic resources, research papers, and clinical journals.</p>
            </div>
            <div class="welcome-slider-container">
                <div class="welcome-slider">
                    <div class="welcome-slide" onclick="document.querySelector('[data-folder=\\'E Books & Jounals/Books\\']').click()">
                        <i class="fas fa-book"></i>
                        <h3>Books</h3>
                        <p>Access thousands of medical texts</p>
                    </div>
                    <div class="welcome-slide" onclick="document.querySelector('[data-folder=\\'E Books & Jounals/Journals\\']').click()">
                        <i class="fas fa-address-card"></i>
                        <h3>Journals</h3>
                        <p>Stay updated with latest research</p>
                    </div>
                    <div class="welcome-slide" onclick="document.querySelector('[data-folder=\\'PGIM Academic Publication\\']').click()">
                        <i class="fas fa-user-graduate"></i>
                        <h3>PGIM Academic Publications</h3>
                        <p>Explore postgraduate work</p>
                    </div>
                </div>
            </div>
            <div class="welcome-info-grid">
                <div class="info-card instructions">
                    <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                    <ul>
                        <li>Use the sidebar to navigate through main collections.</li>
                        <li>Search bar allows keyword searching across all documents.</li>
                        <li>Click on any PDF to securely view it in the locked browser.</li>
                        <li>Downloading and printing are strictly prohibited.</li>
                    </ul>
                </div>
                
                <div class="info-card privacy">
                    <h3><i class="fas fa-shield-alt"></i> Privacy & Security Notice</h3>
                    <p>This system is monitored. Your IP address and session activity are recorded. Unauthorized distribution of materials found here is a violation of PGIM policy and may result in disciplinary action. <strong>Please log out when you are finished.</strong></p>
                </div>
            </div>
            
        </div>
      `;
  }

  // Remove the old renderWelcomeScreen function that overrode styles completely



  function updateBreadcrumb(path) {
    const parts = path.split("/").filter((p) => p);
    breadcrumb.innerHTML = "<span onclick=\"loadFolder('')\"></span>";

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
  document.querySelectorAll(".sidebar-nav li[data-folder]").forEach((li) => {
    li.onclick = () => {
      document
        .querySelectorAll(".sidebar-nav li")
        .forEach((l) => l.classList.remove("active"));
      li.classList.add("active");
      
      // Clear history when explicitly using the main sidebar
      pathHistory = [];
      
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

      fileGrid.innerHTML = '<div class="loader"><i class="fas fa-circle-notch fa-spin"></i>Searching the library...</div>';
      const response = await fetch(
        `index.php?action=search&query=${encodeURIComponent(query)}`,
      );
      const data = await response.json();
      renderFiles(data, true); // Pass true to indicate this is a search result
    }, 500);
  };

  // View Switching Logic
  const gridViewBtn = document.getElementById("gridViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");

  function setViewMode(mode) {
    currentViewMode = mode;
    fileGrid.setAttribute("data-view", mode); // Keeps old compatibility if someone uses it
    
    // Specifically target the new items-container if it exists
    const itemsContainer = fileGrid.querySelector('.items-container');
    if (itemsContainer) {
        itemsContainer.className = `items-container view-${mode}`;
    }

    document.querySelector('.view-controls').style.display = 'block'; // Ensure controls are shown for grid/list
    
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
